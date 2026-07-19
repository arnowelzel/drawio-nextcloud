<?php

declare(strict_types=1);

/**
 *
 * @author Pawel Rojek <pawel at pawelrojek.com>
 * @author Ian Reinhart Geiser <igeiser at devonit.com>
 * @author Arno Welzel <privat at arnowelzel.de>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 *
 **/

namespace OCA\Drawio\Controller;

use OCA\Drawio\AppConfig;
use OCA\Drawio\AppInfo\Application;
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
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
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
use OCP\Share\IShare;
use OCP\Util;
use Psr\Log\LoggerInterface;

class EditorController extends Controller
{
    /** Files larger than this are not opened in the editor */
    private const MAX_FILE_SIZE = 104857600; // 100 MB

    private const LOCK_PREFIX = 'drawio_';

    public function __construct(
        IRequest $request,
        private readonly IRootFolder $root,
        private readonly IUserSession $userSession,
        private readonly IURLGenerator $urlGenerator,
        private readonly IL10N $trans,
        private readonly LoggerInterface $logger,
        private readonly AppConfig $config,
        private readonly IManager $shareManager,
        private readonly PublicShareAuth $shareAuth,
        private readonly ILockingProvider $lockingProvider,
        private readonly IAppData $appData,
        private readonly IConfig $ncConfig,
        private readonly IL10NFactory $l10nFactory,
        private readonly ?IVersionManager $versionManager = null,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function loadFileVersion(?int $fileId = null, ?string $revId = null): DataResponse
    {
        try {
            if ($this->versionManager === null) {
                return $this->error($this->trans->t('Versions plugin is not enabled'), Http::STATUS_BAD_REQUEST);
            }

            if (empty($fileId) || empty($revId)) {
                return $this->error($this->trans->t('Invalid fileId/revId supplied.'), Http::STATUS_BAD_REQUEST);
            }

            $user = $this->userSession->getUser();

            if ($user === null) {
                return $this->error($this->trans->t('Insufficient permissions'), Http::STATUS_FORBIDDEN);
            }

            $file = $this->getFileById($fileId);

            if ($file instanceof Folder) {
                return $this->error($this->trans->t('You can not open a folder'), Http::STATUS_BAD_REQUEST);
            }

            return new DataResponse(
                $this->versionManager->getVersionFile($user, $file, $revId)->getContent(),
                Http::STATUS_OK
            );
        } catch (NotFoundException) {
            return $this->loadInternal($fileId, null, true);
        } catch (\Exception $e) {
            return $this->internalError($e, "Can't load file version: $fileId, $revId");
        }
    }

    #[NoAdminRequired]
    public function getFileRevisions(?int $fileId = null): DataResponse
    {
        try {
            if ($this->versionManager === null) {
                return $this->error($this->trans->t('Versions plugin is not enabled'), Http::STATUS_BAD_REQUEST);
            }

            if (empty($fileId)) {
                return $this->error($this->trans->t('Invalid fileId supplied.'), Http::STATUS_BAD_REQUEST);
            }

            $user = $this->userSession->getUser();

            if ($user === null) {
                return $this->error($this->trans->t('Insufficient permissions'), Http::STATUS_FORBIDDEN);
            }

            $file = $this->getFileById($fileId);

            if ($file instanceof Folder) {
                return $this->error($this->trans->t('You can not open a folder'), Http::STATUS_BAD_REQUEST);
            }

            $versions = $this->versionManager->getVersionsForFile($user, $file);

            return new DataResponse(
                array_values(array_map(static fn (IVersion $version): array => [
                    'revId' => $version->getRevisionId(),
                    'timestamp' => $version->getTimestamp(),
                ], $versions)),
                Http::STATUS_OK
            );
        } catch (\Exception $e) {
            return $this->internalError($e, "Can't get file versions: $fileId");
        }
    }

    #[NoAdminRequired]
    #[PublicPage]
    public function load(?int $fileId = null, ?string $shareToken = null): DataResponse
    {
        return $this->loadInternal($fileId, $shareToken, false);
    }

    private function loadInternal(?int $fileId, ?string $shareToken, bool $contentsOnly): DataResponse
    {
        $lockKey = null;
        $locked = false;

        try {
            [$file, , $relativePath] = $this->getFile($fileId, $shareToken);

            if (!($file instanceof File)) {
                return $this->error($this->trans->t('You can not open a folder'), Http::STATUS_BAD_REQUEST);
            }

            if ($file->getSize() > self::MAX_FILE_SIZE) {
                return $this->error(
                    $this->trans->t('This file is too big to be opened. Please download the file instead.'),
                    Http::STATUS_BAD_REQUEST
                );
            }

            $lockKey = self::LOCK_PREFIX . $file->getId();
            $this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_SHARED);
            $locked = true;

            $fileContents = $file->getContent();

            if ($contentsOnly) {
                return new DataResponse($fileContents, Http::STATUS_OK);
            }

            return new DataResponse(
                ['xml' => $fileContents] + $this->describeFile($file, $file->isUpdateable(), $relativePath, $shareToken),
                Http::STATUS_OK
            );
        } catch (BadRequestException) {
            return $this->error($this->trans->t('Invalid fileId/shareToken supplied.'), Http::STATUS_BAD_REQUEST);
        } catch (NotFoundException) {
            return $this->error($this->trans->t('File not found.'), Http::STATUS_NOT_FOUND);
        } catch (LockedException) {
            return $this->error($this->trans->t('The file is locked.'), Http::STATUS_CONFLICT);
        } catch (ForbiddenException $e) {
            return $this->error($e->getMessage(), Http::STATUS_FORBIDDEN);
        } catch (HintException $e) {
            return $this->error($e->getHint(), Http::STATUS_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->internalError($e, "Can't load file: $fileId , $shareToken");
        } finally {
            if ($locked && $lockKey !== null) {
                $this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_SHARED);
            }
        }
    }

    #[NoAdminRequired]
    #[PublicPage]
    public function getFileInfo(?int $fileId = null, ?string $shareToken = null): DataResponse
    {
        try {
            [$file, $writeable, $relativePath] = $this->getFile($fileId, $shareToken);

            if (!($file instanceof File)) {
                return $this->error($this->trans->t('You can not open a folder'), Http::STATUS_BAD_REQUEST);
            }

            return new DataResponse(
                $this->describeFile($file, $writeable, $relativePath, $shareToken),
                Http::STATUS_OK
            );
        } catch (BadRequestException) {
            return $this->error($this->trans->t('Invalid fileId/shareToken supplied.'), Http::STATUS_BAD_REQUEST);
        } catch (NotFoundException) {
            return $this->error($this->trans->t('File not found.'), Http::STATUS_NOT_FOUND);
        } catch (LockedException) {
            return $this->error($this->trans->t('The file is locked.'), Http::STATUS_CONFLICT);
        } catch (ForbiddenException $e) {
            return $this->error($e->getMessage(), Http::STATUS_FORBIDDEN);
        } catch (HintException $e) {
            return $this->error($e->getHint(), Http::STATUS_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->internalError($e, "Can't get file info: $fileId , $shareToken");
        }
    }

    #[NoAdminRequired]
    #[PublicPage]
    public function save(
        ?int $fileId = null,
        ?string $shareToken = null,
        string $fileContents = '',
        string $etag = '',
    ): DataResponse {
        try {
            if ($fileContents === '') {
                $this->logger->error('No file content supplied', ['app' => $this->appName]);

                return $this->error($this->trans->t('File content not supplied'), Http::STATUS_BAD_REQUEST);
            }

            if ($etag === '') {
                $this->logger->error('No file etag supplied', ['app' => $this->appName]);

                return $this->error($this->trans->t('File etag not supplied'), Http::STATUS_BAD_REQUEST);
            }

            [$file, $writeable] = $this->getFile($fileId, $shareToken);

            if (!($file instanceof File)) {
                return $this->error($this->trans->t('You can not write to a folder'), Http::STATUS_BAD_REQUEST);
            }

            if (!$writeable) {
                $this->logger->error(
                    "User does not have permission to write to file: $fileId , $shareToken",
                    ['app' => $this->appName]
                );

                return $this->error($this->trans->t('Insufficient permissions'), Http::STATUS_FORBIDDEN);
            }

            return $this->writeFile($file, $fileContents, $etag);
        } catch (BadRequestException) {
            return $this->error($this->trans->t('Invalid fileId/shareToken supplied.'), Http::STATUS_BAD_REQUEST);
        } catch (NotFoundException) {
            return $this->error($this->trans->t('File not found.'), Http::STATUS_NOT_FOUND);
        } catch (HintException $e) {
            return $this->error($e->getHint(), Http::STATUS_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->internalError($e, "Can't save file: $fileId , $shareToken");
        }
    }

    private function writeFile(File $file, string $fileContents, string $etag): DataResponse
    {
        $lockKey = self::LOCK_PREFIX . $file->getId();
        $locked = false;

        try {
            // TODO Could not get the locking to work, two browsers can edit the same file at the same time
            $this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
            $locked = true;

            // Check if file was changed in the meantime
            if ($etag !== $file->getEtag()) {
                return $this->error(
                    $this->trans->t('The file you are working on was updated in the meantime.'),
                    Http::STATUS_CONFLICT
                );
            }

            try {
                $file->putContent($fileContents);
            } catch (LockedException|ForbiddenException|GenericFileException $e) {
                throw $e;
            } catch (\Exception $e) {
                // Post-save hooks (e.g. Activity, Deck) may fail even though
                // the file was written successfully. Check if the file was
                // actually saved by comparing etags, and if so treat as success.
                clearstatcache();

                if ($file->getEtag() === $etag) {
                    throw $e;
                }

                $this->logger->warning(
                    'Post-save hook error (file was saved successfully): ' . $e->getMessage(),
                    ['app' => $this->appName, 'exception' => $e]
                );

                return $this->describeWrite($file);
            }

            clearstatcache();

            return $this->describeWrite($file);
        } catch (LockedException) {
            return $this->error($this->trans->t('The file is locked.'), Http::STATUS_CONFLICT);
        } catch (ForbiddenException $e) {
            return $this->error($e->getMessage(), Http::STATUS_FORBIDDEN);
        } catch (GenericFileException) {
            return $this->error($this->trans->t('Could not write to file.'), Http::STATUS_INTERNAL_SERVER_ERROR);
        } finally {
            if ($locked) {
                $this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
            }
        }
    }

    #[NoAdminRequired]
    #[PublicPage]
    public function savePreview(
        ?int $fileId = null,
        ?string $shareToken = null,
        string $previewContents = '',
    ): DataResponse {
        try {
            if ($previewContents === '') {
                $this->logger->error('Incorrect parameters for savePreview', ['app' => $this->appName]);

                return $this->error($this->trans->t('Incorrect parameters'), Http::STATUS_BAD_REQUEST);
            }

            [$file, $writeable] = $this->getFile($fileId, $shareToken);
            $this->logger->debug("Saving preview for file: $fileId , $shareToken", ['app' => $this->appName]);

            $previewFolder = $this->getPreviewFolder();

            if ($file instanceof Folder || !$writeable) {
                return $this->error($this->trans->t('You cannot write to this path'), Http::STATUS_FORBIDDEN);
            }

            $previewFolder->newFile($file->getId() . '.png', (string)base64_decode($previewContents));

            return new DataResponse('OK', Http::STATUS_OK);
        } catch (\Exception $e) {
            return $this->internalError($e, "Can't save preview for file: $fileId , $shareToken");
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[NoAdminRequired]
    #[PublicPage]
    public function create(string $name = '', ?int $dirId = null, ?string $shareToken = null): array
    {
        try {
            [$folder, $isCreatable] = $this->getDir($dirId, $shareToken);
        } catch (NotFoundException) {
            $this->logger->info("Folder for file creation was not found: $dirId", ['app' => $this->appName]);

            return ['error' => $this->trans->t('The required folder was not found')];
        }

        if (!$isCreatable) {
            $this->logger->info("Folder for file creation without permission: $dirId", ['app' => $this->appName]);

            return ['error' => $this->trans->t("You don't have enough permission to create file")];
        }

        try {
            $file = $folder->newFile($folder->getNonExistingName($name), ' '); // "space" - empty file for drawio
        } catch (NotPermittedException $e) {
            $this->logger->error($e->getMessage(), [
                'message' => "Can't create file: $name",
                'app' => $this->appName,
                'exception' => $e,
            ]);

            return ['error' => $this->trans->t("Can't create file")];
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

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function index(
        ?int $fileId = null,
        ?string $shareToken = null,
        bool $lightbox = false,
        bool $isWB = false,
    ): TemplateResponse|RedirectResponse {
        if (empty($shareToken) && !$this->userSession->isLoggedIn()) {
            return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', [
                'redirect_url' => $this->request->getRequestUri(),
            ]));
        }

        $drawioUrl = $this->config->GetDrawioUrl();
        $drawioUrlArgs = '';

        if (str_contains($drawioUrl, '?')) {
            [$drawioUrl, $drawioUrlArgs] = explode('?', $drawioUrl, 2);
        }

        $params = [
            'drawioUrl' => $drawioUrl,
            'drawioUrlArgs' => $drawioUrlArgs,
            'drawioTheme' => $this->config->GetTheme(),
            'drawioDarkMode' => $this->config->GetDarkMode(),
            'drawioLang' => $this->editorLanguage(),
            'drawioOfflineMode' => $this->config->GetOfflineMode(),
            'drawioAutosave' => $this->config->GetAutosave(),
            'drawioLibraries' => $this->config->GetLibraries(),
            'fileId' => $fileId,
            'shareToken' => $shareToken,
            'isWB' => $isWB,
            'drawioReadOnly' => $lightbox,
            'drawioPreviews' => $this->config->GetPreviews(),
            'drawioConfig' => $this->config->GetDrawioConfig(),
        ];

        Util::addScript(Application::APP_ID, 'editor');
        Util::addStyle(Application::APP_ID, 'editor');

        if ($this->userSession->getUser() !== null) {
            $response = new TemplateResponse($this->appName, 'editor', $params);
        } else {
            $response = new PublicTemplateResponse($this->appName, 'editor', $params);
            $response->setFooterVisible(false);
        }

        $response->setContentSecurityPolicy($this->buildContentSecurityPolicy($drawioUrl));

        return $response;
    }

    private function buildContentSecurityPolicy(string $drawioUrl): ContentSecurityPolicy
    {
        $csp = new ContentSecurityPolicy();

        if ($drawioUrl !== '') {
            $csp->addAllowedScriptDomain($drawioUrl);
            $csp->addAllowedFrameDomain($drawioUrl);
            $csp->addAllowedFrameDomain('blob:');
            $csp->addAllowedWorkerSrcDomain($drawioUrl);
            $csp->addAllowedWorkerSrcDomain('blob:');
        }

        return $csp;
    }

    /**
     * The editor expects a plain language code, Nextcloud may report a locale
     */
    private function editorLanguage(): string
    {
        $lang = trim(strtolower($this->config->GetLang()));

        if ($lang !== 'auto') {
            return $lang;
        }

        $lang = $this->l10nFactory->findLanguage();
        $separator = strpos($lang, '_');

        return $separator === false ? $lang : substr($lang, 0, $separator);
    }

    /**
     * @return array<string, mixed>
     */
    private function describeFile(File $file, bool $writeable, ?string $relativePath, ?string $shareToken): array
    {
        return [
            'id' => $file->getId(),
            'size' => $file->getSize(),
            'writeable' => $writeable,
            'mime' => $file->getMimeType(),
            'path' => $relativePath,
            'name' => $file->getName(),
            'owner' => $file->getOwner()?->getUID(),
            'etag' => $file->getEtag(),
            'mtime' => $file->getMTime(),
            'created' => $file->getCreationTime() ?: $file->getUploadTime(),
            'shareToken' => $shareToken,
            'versionsEnabled' => empty($shareToken) && $this->versionManager !== null,
            'ver' => 2,
            'instanceId' => $this->ncConfig->getSystemValueString('instanceid', ''),
        ];
    }

    private function describeWrite(File $file): DataResponse
    {
        return new DataResponse([
            'etag' => $file->getEtag(),
            'size' => $file->getSize(),
            'mtime' => $file->getMTime(),
        ], Http::STATUS_OK);
    }

    private function error(string $message, int $status): DataResponse
    {
        return new DataResponse(['message' => $message], $status);
    }

    private function internalError(\Exception $exception, string $context): DataResponse
    {
        $this->logger->error($exception->getMessage(), [
            'message' => $context,
            'app' => $this->appName,
            'exception' => $exception,
        ]);

        return $this->error($this->trans->t('An internal server error occurred.'), Http::STATUS_INTERNAL_SERVER_ERROR);
    }

    private function getPreviewFolder(): ISimpleFolder
    {
        try {
            return $this->appData->getFolder('previews');
        } catch (NotFoundException) {
            return $this->appData->newFolder('previews');
        }
    }

    /**
     * Getting the shared node by token
     *
     * @return array{0: Node, 1: IShare}
     */
    private function getNodeByToken(string $shareToken): array
    {
        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (ShareNotFound) {
            throw new NotFoundException();
        }

        if (!$this->shareAuth->isAuthenticated($share)
            || !$this->checkPermissions($share, Constants::PERMISSION_READ)) {
            throw new ForbiddenException('Insufficient permissions', false);
        }

        return [$share->getNode(), $share];
    }

    /**
     * Getting file by id
     */
    private function getFileById(int $fileId): Node
    {
        $file = $this->root->getFirstNodeById($fileId);

        if ($file === null) {
            throw new NotFoundException();
        }

        if (!$file->isReadable()) {
            throw new ForbiddenException('Insufficient permissions', false);
        }

        return $file;
    }

    /**
     * Getting file by id or token
     *
     * @return array{0: Node, 1: bool, 2: ?string}
     */
    private function getFile(?int $fileId, ?string $shareToken): array
    {
        $baseFolder = null;
        $share = null;

        if (!empty($fileId) && $this->userSession->isLoggedIn()) {
            $file = $this->getFileById($fileId);
            $baseFolder = $this->root->getUserFolder($this->userSession->getUser()->getUID());

            if (!empty($shareToken)) {
                // Have fileId and shareToken, and be logged in, get $share
                $share = $this->shareManager->getShareByToken($shareToken);
            }
        } elseif (!empty($shareToken)) {
            [$file, $share] = $this->getNodeByToken($shareToken);

            if (!empty($fileId) && $file instanceof Folder) { // File in a shared folder case
                $file = $file->getFirstNodeById($fileId);

                if ($file === null) {
                    throw new NotFoundException();
                }
            }
        } else {
            throw new BadRequestException(['fileId', 'shareToken']);
        }

        $writeable = $share !== null
            ? $this->checkPermissions($share, Constants::PERMISSION_UPDATE)
            : $file->isUpdateable();

        return [$file, $writeable, $baseFolder?->getRelativePath($file->getPath())];
    }

    /**
     * Getting directory by id or token
     *
     * @return array{0: Folder, 1: bool}
     */
    private function getDir(?int $dirId, ?string $shareToken): array
    {
        $share = null;

        if (!empty($dirId) && $this->userSession->isLoggedIn()) {
            $dir = $this->root->getFirstNodeById($dirId);

            if ($dir === null) {
                throw new NotFoundException();
            }
        } elseif (!empty($shareToken)) {
            [$dir, $share] = $this->getNodeByToken($shareToken);
        } else {
            throw new BadRequestException(['fileId', 'shareToken']);
        }

        if (!($dir instanceof Folder)) {
            throw new NotFoundException();
        }

        $isCreatable = $share !== null
            ? $this->checkPermissions($share, Constants::PERMISSION_CREATE)
            : $dir->isCreatable();

        return [$dir, $isCreatable];
    }

    protected function checkPermissions(IShare $share, int $permissions): bool
    {
        return ($share->getPermissions() & $permissions) === $permissions;
    }
}
