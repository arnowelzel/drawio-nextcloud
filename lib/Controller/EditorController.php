<?php
namespace OCA\Drawio\Controller;

use OCA\Drawio\AppConfig;
use OCA\Drawio\AppInfo\Application;
use OCA\Drawio\PersonalConfig;
use OCA\Drawio\Service\PublicShareAuth;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Constants;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\ForbiddenException;
use OCP\Files\GenericFileException;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\HintException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Util;
use Psr\Log\LoggerInterface;

class EditorController extends Controller
{
    private $userSession;
    private $root;
    private $urlGenerator;
    private $trans;
    private $logger;
    private $appConfig;
    private $personalConfig;

    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * @var PublicShareAuth
     */
    private $shareAuth;

    /**
	 * @var ILockingProvider
	 */
	protected $lockingProvider;

    /**
	 * @var IVersionManager|null
	 */
	protected $versionManager;

    /** @var IAppData */
    private $appData;

    /** @var IConfig */
    private $ncConfig;

    /** @var IL10NFactory */
    private $l10nFactory;

    /**
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param AppConfig $appConfig - app config
     */
    public function __construct(IRequest         $request,
                                IRootFolder      $root,
                                IUserSession     $userSession,
                                IURLGenerator    $urlGenerator,
                                IL10N            $trans,
                                LoggerInterface  $logger,
                                AppConfig        $appConfig,
                                PersonalConfig   $personalConfig,
                                IManager         $shareManager,
                                PublicShareAuth  $shareAuth,
                                ILockingProvider $lockingProvider,
                                IAppData         $appData,
                                IConfig          $ncConfig,
                                IL10NFactory     $l10nFactory,
                                ?IVersionManager $versionManager = null
                                )
    {
        parent::__construct(Application::APP_ID, $request);

        $this->userSession = $userSession;
        $this->root = $root;
        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->appConfig = $appConfig;
        $this->personalConfig = $personalConfig;
        $this->shareManager = $shareManager;
        $this->shareAuth = $shareAuth;
        $this->lockingProvider = $lockingProvider;
        $this->appData = $appData;
        $this->ncConfig = $ncConfig;
        $this->l10nFactory = $l10nFactory;
        $this->versionManager = $versionManager;
    }

    /**
     * @param string $fileId
     * @param string $revId
     * @return DataResponse
     */
    #[NoAdminRequired]
	public function loadFileVersion($fileId, $revId)
    {
        try {
            if (!isset($this->versionManager))
            {
                return new DataResponse(['message' => $this->trans->t('Versions plugin is not enabled')], Http::STATUS_BAD_REQUEST);
            }

			if (!empty($fileId) && !empty($revId))
            {
                $user = $this->userSession->getUser();
				/** @var File $file */
                $file = $this->getFileById($fileId);

				if ($file instanceof Folder)
                {
					return new DataResponse(['message' => $this->trans->t('You can not open a folder')], Http::STATUS_BAD_REQUEST);
				}

                return new DataResponse($this->versionManager->getVersionFile($user, $file, $revId)->getContent(),
                    Http::STATUS_OK
                );
			} else {
				return new DataResponse(['message' => $this->trans->t('Invalid fileId/revId supplied.')], Http::STATUS_BAD_REQUEST);
			}
        }
        catch (NotFoundException $e)
        {
            return $this->loadInternal($fileId, null, true);
		} catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ["message" => "Can't load file version: $fileId, $revId", "app" => $this->appName, 'exception' => $e]);
			$message = $this->trans->t('An internal server error occurred.');
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
    }

    /**
     * @param string $fileId
     * @return DataResponse
     */
    #[NoAdminRequired]
	public function getFileRevisions($fileId)
    {
        try {
            if (!isset($this->versionManager))
            {
                return new DataResponse(['message' => $this->trans->t('Versions plugin is not enabled')], Http::STATUS_BAD_REQUEST);
            }

			if (!empty($fileId))
            {
                $user = $this->userSession->getUser();
				/** @var File $file */
                $file = $this->getFileById($fileId);

				if ($file instanceof Folder)
                {
					return new DataResponse(['message' => $this->trans->t('You can not open a folder')], Http::STATUS_BAD_REQUEST);
				}

                $versions = $this->versionManager->getVersionsForFile($user, $file);

                return new DataResponse(
                    array_values(array_map(function (IVersion $version) {
                        return [
                            'revId' => $version->getRevisionId(),
                            'timestamp' => $version->getTimestamp()
                        ];
                    }, $versions)),
                    Http::STATUS_OK
                );
			} else {
				return new DataResponse(['message' => $this->trans->t('Invalid fileId supplied.')], Http::STATUS_BAD_REQUEST);
			}
		} catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ["message" => "Can't get file versions: $fileId", "app" => $this->appName, 'exception' => $e]);
			$message = $this->trans->t('An internal server error occurred.');
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
    }

    /**
     * @param string $fileId
     * @param string $shareToken
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
	public function load($fileId, $shareToken)
    {
        return $this->loadInternal($fileId, $shareToken, false);
    }

    private function loadInternal($fileId, $shareToken, $contentsOnly)
    {
        $locked = false;

		try
        {
            list ($file, $writeable, $relativePath) = $this->getFile($fileId, $shareToken);

            if ($file instanceof Folder)
            {
                return new DataResponse(['message' => $this->trans->t('You can not open a folder')], Http::STATUS_BAD_REQUEST);
            }

            // default of 100MB. TODO Review this
            $maxSize = 104857600;
            if ($file->getSize() > $maxSize)
            {
                return new DataResponse(['message' => $this->trans->t('This file is too big to be opened. Please download the file instead.')], Http::STATUS_BAD_REQUEST);
            }

            $fileId = $file->getId();
            $this->lockingProvider->acquireLock('drawio_'.$fileId, ILockingProvider::LOCK_SHARED);
            $locked = true;
            $fileContents = $file->getContent();

            if ($fileContents !== false)
            {
                return new DataResponse(
                    $contentsOnly? $fileContents: [
                        'xml' => $fileContents,
                        'id' => $fileId,
                        'size' => $file->getSize(),
                        'writeable' => $file->isUpdateable(),
                        'mime' => $file->getMimeType(),
                        'path' => $relativePath,
                        'name' => $file->getName(),
                        'owner' => $file->getOwner()->getUID(),
                        'etag' => $file->getEtag(),
                        'mtime' => $file->getMTime(),
                        'created' => $file->getCreationTime() ?: $file->getUploadTime(),
                        'shareToken' => $shareToken,
                        'versionsEnabled' => empty($shareToken) && isset($this->versionManager),
                        'ver' => 2,
                        'instanceId' => $this->ncConfig->getSystemValueString('instanceid', '')
                    ],
                    Http::STATUS_OK
                );
            }
            else
            {
                return new DataResponse(['message' => $this->trans->t('Cannot read the file.')], Http::STATUS_FORBIDDEN);
            }
        }
        catch (BadRequestException $e)
        {
            return new DataResponse(['message' => $this->trans->t('Invalid fileId/shareToken supplied.')], Http::STATUS_BAD_REQUEST);
        }
        catch (NotFoundException $e)
        {
            return new DataResponse(['message' => $this->trans->t('File not found.')], Http::STATUS_NOT_FOUND);
		} catch (LockedException $e) {
			$message = $this->trans->t('The file is locked.');
			return new DataResponse(['message' => $message], Http::STATUS_CONFLICT);
		} catch (ForbiddenException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (HintException $e) {
			$message = $e->getHint();
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ["message" => "Can't load file: $fileId , $shareToken", "app" => $this->appName, 'exception' => $e]);
			$message = $this->trans->t('An internal server error occurred.');
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
        finally
        {
            if ($locked)
            {
                $this->lockingProvider->releaseLock('drawio_'.$fileId, ILockingProvider::LOCK_SHARED);
            }
        }
	}

    /**
     * @param string $fileId
     * @param string $shareToken
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
	public function getFileInfo($fileId, $shareToken)
    {
		try
        {
            list ($file, $writeable, $relativePath) = $this->getFile($fileId, $shareToken);

            if ($file instanceof Folder) {
                return new DataResponse(['message' => $this->trans->t('You can not open a folder')], Http::STATUS_BAD_REQUEST);
            }

            return new DataResponse(
                [
                    'id' => $file->getId(),
                    'size' => $file->getSize(),
                    'writeable' => $writeable,
                    'mime' => $file->getMimeType(),
                    'path' => $relativePath,
                    'name' => $file->getName(),
                    'owner' => $file->getOwner()->getUID(),
                    'etag' => $file->getEtag(),
                    'mtime' => $file->getMTime(),
                    'created' => $file->getCreationTime() ?: $file->getUploadTime(),
                    'shareToken' => $shareToken,
                    'versionsEnabled' => empty($shareToken) && isset($this->versionManager),
                    'ver' => 2,
                    'instanceId' => $this->ncConfig->getSystemValueString('instanceid', '')
                ],
                Http::STATUS_OK
            );
        }
        catch (BadRequestException $e)
        {
            return new DataResponse(['message' => $this->trans->t('Invalid fileId/shareToken supplied.')], Http::STATUS_BAD_REQUEST);
        }
        catch (NotFoundException $e)
        {
            return new DataResponse(['message' => $this->trans->t('File not found.')], Http::STATUS_NOT_FOUND);
        } catch (LockedException $e) {
			$message = $this->trans->t('The file is locked.');
			return new DataResponse(['message' => $message], Http::STATUS_CONFLICT);
		} catch (ForbiddenException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (HintException $e) {
			$message = $e->getHint();
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ["message" => "Can't get file info: $fileId , $shareToken", "app" => $this->appName, 'exception' => $e]);
			$message = $this->trans->t('An internal server error occurred.');
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    /**
     * @param string $fileId
     * @param string $shareToken
     * @param string $fileContents
     * @param string $etag
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
	public function save($fileId, $shareToken, $fileContents, $etag)
    {
		try
        {
			if (!empty($fileContents) && !empty($etag))
            {
                list ($file, $writeable) = $this->getFile($fileId, $shareToken);

				if ($file instanceof Folder) {
					return new DataResponse(['message' => $this->trans->t('You can not write to a folder')], Http::STATUS_BAD_REQUEST);
				}

				if($writeable)
                {
                    $locked = false;
                    $fileId = $file->getId();

					try
                    {
                        // TODO Could not get the locking to work, two browsers can edit the same file at the same time
                        $this->lockingProvider->acquireLock('drawio_'.$fileId, ILockingProvider::LOCK_EXCLUSIVE);
                        $locked = true;

                        // Check if file was changed in the meantime
                        if ($etag != $file->getEtag())
                        {
                            return new DataResponse([ 'message' => $this->trans->t('The file you are working on was updated in the meantime.')], Http::STATUS_CONFLICT);
                        }

						try {
                            $file->putContent($fileContents);
                        } catch (LockedException $e) {
                            throw $e;
                        } catch (ForbiddenException $e) {
                            throw $e;
                        } catch (GenericFileException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            // Post-save hooks (e.g. Activity, Deck) may fail even though
                            // the file was written successfully. Check if the file was
                            // actually saved by comparing etags, and if so treat as success.
                            clearstatcache();
                            $newEtag = $file->getEtag();
                            if ($newEtag !== $etag) {
                                $this->logger->warning('Post-save hook error (file was saved successfully): ' . $e->getMessage(),
                                    ['app' => $this->appName, 'exception' => $e]);
                                $newSize = $file->getSize();
                                $newMtime = $file->getMTime();
                                return new DataResponse(['etag' => $newEtag, 'size' => $newSize, 'mtime' => $newMtime], Http::STATUS_OK);
                            }
                            throw $e;
                        }
                        // Clear statcache
                        clearstatcache();
                        // Get new eTag
                        $newEtag = $file->getEtag();
                        $newSize = $file->getSize();
                        $newMtime = $file->getMTime();
					}
                    catch (LockedException $e)
                    {
						$message = $this->trans->t('The file is locked.');
						return new DataResponse(['message' => $message], Http::STATUS_CONFLICT);
					}
                    catch (ForbiddenException $e)
                    {
						return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
					}
                    catch (GenericFileException $e)
                    {
						return new DataResponse(['message' => $this->trans->t('Could not write to file.')], Http::STATUS_INTERNAL_SERVER_ERROR);
					}
                    finally
                    {
                        if ($locked)
                        {
                            $this->lockingProvider->releaseLock('drawio_'.$fileId, ILockingProvider::LOCK_EXCLUSIVE);
                        }
                    }

					return new DataResponse(['etag' => $newEtag, 'size' => $newSize, 'mtime' => $newMtime], Http::STATUS_OK);
				} else {
					// Not writeable!
					$this->logger->error('User does not have permission to write to file: ' . $fileId . ', ' . $shareToken,
						['app' => $this->appName]);
					return new DataResponse([ 'message' => $this->trans->t('Insufficient permissions')],
						Http::STATUS_FORBIDDEN);
				}
			} else if (!empty($fileContents)) {
				$this->logger->error('No file etag supplied', ['app' => $this->appName]);
				return new DataResponse(['message' => $this->trans->t('File etag not supplied')], Http::STATUS_BAD_REQUEST);
			} else {
				$this->logger->error('No file content supplied', ['app' => $this->appName]);
				return new DataResponse(['message' => $this->trans->t('File content not supplied')], Http::STATUS_BAD_REQUEST);
			}
		}
        catch (BadRequestException $e)
        {
            return new DataResponse(['message' => $this->trans->t('Invalid fileId/shareToken supplied.')], Http::STATUS_BAD_REQUEST);
        }
        catch (NotFoundException $e)
        {
            return new DataResponse(['message' => $this->trans->t('File not found.')], Http::STATUS_NOT_FOUND);
		}
        catch (HintException $e)
        {
			$message = $e->getHint();
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ["message" => "Can't save file: $fileId , $shareToken", "app" => $this->appName, 'exception' => $e]);
			$message = $this->trans->t('An internal server error occurred.');
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    /**
     * @param string $fileId
     * @param string $shareToken
     * @param string $previewContents
     * @return DataResponse
     */
    #[NoAdminRequired]
    #[PublicPage]
    public function savePreview($fileId, $shareToken, $previewContents)
    {
        try
        {
			if (!empty($previewContents))
            {
                list ($file, $writeable) = $this->getFile($fileId, $shareToken);
                $this->logger->debug("Saving preview for file: $fileId , $shareToken", ["app" => $this->appName]);

                $prevFolder = null;

                try
                {
                    $prevFolder = $this->appData->getFolder('previews');
                }
                catch (NotFoundException $e)
                {
                    $prevFolder = $this->appData->newFolder('previews');
                }

                if ($file instanceof Folder || !$writeable)
                {
                    return new DataResponse(['message' => $this->trans->t('You cannot write to this path')], Http::STATUS_FORBIDDEN);
                }

                $prevFolder->newFile($file->getId() . '.png', base64_decode($previewContents));

                return new DataResponse('OK', Http::STATUS_OK);
			}
            else
            {
				$this->logger->error('Incorrect parameters for savePreview', ['app' => $this->appName]);
				return new DataResponse(['message' => $this->trans->t('Incorrect parameters')], Http::STATUS_BAD_REQUEST);
			}
        }
        catch (\Exception $e)
        {
            $this->logger->error($e->getMessage(), ["message" => "Can't save preview for file: $fileId , $shareToken", "app" => $this->appName, 'exception' => $e]);
			$message = $this->trans->t('An internal server error occurred.');
			return new DataResponse(['message' => $message], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
    }

    #[NoAdminRequired]
    #[PublicPage]
    public function create($name, $dirId, $shareToken)
    {
        list ($folder, $writeable) = $this->getDir($dirId, $shareToken);

        if ($folder === NULL)
        {
            $this->logger->info("Folder for file creation was not found: " . $dirId, ["app" => $this->appName]);
            return ["error" => $this->trans->t("The required folder was not found")];
        }

        if (!$writeable)
        {
            $this->logger->info("Folder for file creation without permission: " . $dirId, ["app" => $this->appName]);
            return ["error" => $this->trans->t("You don't have enough permission to create file")];
        }

        $name = $folder->getNonExistingName($name);

        $template = " "; //"space" - empty file for drawio

        try
        {
            $file = $folder->newFile($name, $template);
        }
        catch (NotPermittedException $e)
        {
            $this->logger->error($e->getMessage(), ["message" => "Can't create file: $name", "app" => $this->appName, 'exception' => $e]);
            return ["error" => $this->trans->t("Can't create file")];
        }

        return [
            'id' => $file->getId(),
            'parentId' => $file->getParent()->getId(),
            'mtime' => $file->getMTime() * 1000,
            'name' => $file->getName(),
            'permissions' => $file->getPermissions(),
            'mimetype' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $file->getType(),
            'etag' => $file->getEtag(),
        ];
    }

    /**
     *
     * @param integer $fileId - file identifier
     *
     * @return TemplateResponse|RedirectResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function index($fileId, $shareToken = NULL, $lightbox = false, $isWB = false)
    {
        if (empty($shareToken) && !$this->userSession->isLoggedIn())
        {
            $redirectUrl = $this->urlGenerator->linkToRoute("core.login.showLoginForm", [
                "redirect_url" => $this->request->getRequestUri()
            ]);
            return new RedirectResponse($redirectUrl);
        }

        $drawioUrl = $this->appConfig->GetDrawioUrl();
        $theme = $this->personalConfig->GetTheme();
        if ($theme === 'default') {
            $theme = $this->appConfig->GetTheme();
        }
        $darkMode = $this->personalConfig->GetDarkMode();
        if ($darkMode === 'auto') {
            $darkMode = $this->appConfig->GetDarkMode();
        }
	    $offlineMode = $this->appConfig->GetOfflineMode();
        $lang = $this->personalConfig->GetLang();
        if ($lang === 'auto') {
            $lang = $this->appConfig->GetLang();
        }
        $lang = trim(strtolower($lang));

        if ("auto" === $lang)
        {
            $lang = $this->l10nFactory->findLanguage();

            if (!empty($lang) && strpos($lang, "_"))
            {
                $lang = substr($lang, 0, strpos($lang, "_")); // Change to draw.io format
            }
        }

        $drawioUrlArray = explode("?",$drawioUrl);

        if (count($drawioUrlArray) > 1)
        {
            $drawioUrl = $drawioUrlArray[0];
            $drawioUrlArgs = $drawioUrlArray[1];
        }
        else
        {
            $drawioUrlArgs = "";
        }

        $params = [
            "drawioUrl" => $drawioUrl,
            "drawioUrlArgs" => $drawioUrlArgs,
            "drawioTheme" => $theme,
            "drawioDarkMode" => $darkMode,
            "drawioLang" => $lang,
            "drawioOfflineMode" => $offlineMode,
            "drawioAutosave" =>$this->appConfig->GetAutosave(),
            "drawioLibraries" =>$this->appConfig->GetLibraries(),
            "fileId" => $fileId,
            "shareToken" => $shareToken,
            "isWB" => $isWB,
            "drawioReadOnly" => $lightbox,
            "drawioPreviews" => $this->appConfig->GetPreviews(),
            "drawioConfig" => $this->appConfig->GetDrawioConfig(),
        ];

        Util::addScript(Application::APP_ID, "editor");
        Util::addStyle(Application::APP_ID, "editor");

        if ($this->userSession->getUser() !== null)
        {
            $response = new TemplateResponse($this->appName, "editor", $params);
        }
        else
        {
            $response = new PublicTemplateResponse($this->appName, "editor", $params);
            $response->setFooterVisible(false);
        }

        $csp = new ContentSecurityPolicy();

        if (!empty($drawioUrl))
        {
            $csp->addAllowedScriptDomain($drawioUrl);
            $csp->addAllowedFrameDomain($drawioUrl);
            $csp->addAllowedFrameDomain("blob:");
            $csp->addAllowedWorkerSrcDomain($drawioUrl);
            $csp->addAllowedWorkerSrcDomain("blob:");
        }
        $response->setContentSecurityPolicy($csp);

        return $response;
    }

    /**
     * Getting file by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getNodeByToken($shareToken)
    {
        $share = null;

        try
        {
            $share = $this->shareManager->getShareByToken($shareToken);
        }
        catch (ShareNotFound $e)
        {
            throw new NotFoundException();
        }

        if (!$this->shareAuth->isAuthenticated($share) ||
            !$this->checkPermissions($share, Constants::PERMISSION_READ))
        {
            throw new ForbiddenException('Insufficient permissions', false);
        }

        return [$share->getNode(), $share];
    }

    /**
     * Getting file by id
     *
     * @param $fileId - file identifier
     *
     */
    private function getFileById($fileId)
    {
        $files = $this->root->getById($fileId);

        if (empty($files))
        {
            throw new NotFoundException();
        }

        $file = $files[0];

        if (!$file->isReadable())
        {
            throw new ForbiddenException('Insufficient permissions', false);
        }

        return $file;
    }

    /**
     * Getting file by id or token
     *
     * @param $fileId - file identifier
     * @param $shareToken - access token
     *
     * @return array
     */
    private function getFile($fileId, $shareToken)
    {
        /** @var File $file */
        $file = null;
        $writeable = false;
        $baseFolder = null;
        $share = null;

        if (!empty($fileId) && $this->userSession->isLoggedIn())
        {
            $file = $this->getFileById($fileId);
            $uid = $this->userSession->getUser()->getUID();
            $baseFolder = $this->root->getUserFolder($uid);
            if (!empty($shareToken))
            {
                $share = $this->shareManager->getShareByToken($shareToken);  // Have fileId and shareToken, and be logged in, get $share
            }
        }
        else if (!empty($shareToken))
        {
            list ($file, $share) = $this->getNodeByToken($shareToken);

            if (!empty($fileId) && $file instanceof Folder) // File in a shared folder case
            {
                $file = $file->getFirstNodeById((int)$fileId);

                if ($file === null)
                {
                    throw new NotFoundException();
                }
            }
        }
        else
        {
            throw new BadRequestException(['fileId', 'shareToken']);
        }

        if ($file === null || $file === false)
        {
            throw new NotFoundException();
        }

        if (!empty($shareToken))
        {
            $writeable = $this->checkPermissions($share, Constants::PERMISSION_UPDATE);
        }
        else
        {
            $writeable = $file->isUpdateable();
        }

        return [$file, $writeable, $baseFolder != null? $baseFolder->getRelativePath($file->getPath()) : null];
    }

    /**
     * Getting directory by id or token
     *
     * @param $dirId - directory identifier
     * @param $shareToken - access token
     *
     * @return array
     */
    private function getDir($dirId, $shareToken)
    {
        /** @var Folder $dir */
        $dir = null;
        $isCreatable = false;

        if (!empty($dirId) && $this->userSession->isLoggedIn())
        {
            $nodes = $this->root->getById($dirId);

            if (empty($nodes))
            {
                throw new NotFoundException();
            }

            $dir = $nodes[0];
        }
        else if (!empty($shareToken))
        {
            list ($dir, $share) = $this->getNodeByToken($shareToken);
        }
        else
        {
            throw new BadRequestException(['fileId', 'shareToken']);
        }

        if ($dir === null || $dir === false)
        {
            throw new NotFoundException();
        }

        if (!empty($shareToken))
        {
            $isCreatable = $this->checkPermissions($share, Constants::PERMISSION_CREATE);
        }
        else
        {
            $isCreatable = $dir->isCreatable();
        }

        return [$dir, $isCreatable];
    }

    protected function checkPermissions($share, $permissions) {
        return ($share->getPermissions() & $permissions) === $permissions;
    }
}
