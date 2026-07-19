<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Controller;

use OCA\Drawio\AppConfig;
use OCA\Drawio\Controller\EditorController;
use OCA\Drawio\Service\PublicShareAuth;
use OCA\Drawio\Tests\Support\ResetsGlobalState;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class EditorControllerTest extends TestCase {
    use ResetsGlobalState;

    private IRequest&MockObject $request;
    private IRootFolder&MockObject $root;
    private IUserSession&MockObject $userSession;
    private IURLGenerator&MockObject $urlGenerator;
    private LoggerInterface&MockObject $logger;
    private AppConfig&MockObject $appConfig;
    private IManager&MockObject $shareManager;
    private PublicShareAuth&MockObject $shareAuth;
    private ILockingProvider&MockObject $lockingProvider;
    private IAppData&MockObject $appData;
    private IConfig&MockObject $ncConfig;
    private IL10NFactory&MockObject $l10nFactory;
    private ?IVersionManager $versionManager = null;

    protected function setUp(): void {
        $this->resetGlobalState();

        $this->request = $this->createMock(IRequest::class);
        $this->root = $this->createMock(IRootFolder::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->urlGenerator = $this->createMock(IURLGenerator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->appConfig = $this->createMock(AppConfig::class);
        $this->shareManager = $this->createMock(IManager::class);
        $this->shareAuth = $this->createMock(PublicShareAuth::class);
        $this->lockingProvider = $this->createMock(ILockingProvider::class);
        $this->appData = $this->createMock(IAppData::class);
        $this->ncConfig = $this->createMock(IConfig::class);
        $this->l10nFactory = $this->createMock(IL10NFactory::class);
        $this->versionManager = null;

        $this->ncConfig->method('getSystemValueString')->willReturn('instance123');
    }

    private function createController(): EditorController {
        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnCallback(static fn (string $text, $params = []) => $text);

        return new EditorController(
            $this->request,
            $this->root,
            $this->userSession,
            $this->urlGenerator,
            $l10n,
            $this->logger,
            $this->appConfig,
            $this->shareManager,
            $this->shareAuth,
            $this->lockingProvider,
            $this->appData,
            $this->ncConfig,
            $this->l10nFactory,
            $this->versionManager
        );
    }

    private function loginAs(string $uid = 'admin'): IUser&MockObject {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('isLoggedIn')->willReturn(true);
        $this->userSession->method('getUser')->willReturn($user);
        return $user;
    }

    private function createFile(array $props = []): File&MockObject {
        $owner = $this->createMock(IUser::class);
        $owner->method('getUID')->willReturn($props['owner'] ?? 'admin');

        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn($props['id'] ?? 97);
        $file->method('getSize')->willReturn($props['size'] ?? 42);
        $file->method('getContent')->willReturn($props['content'] ?? '<mxfile/>');
        $file->method('isUpdateable')->willReturn($props['updateable'] ?? true);
        $file->method('isReadable')->willReturn($props['readable'] ?? true);
        $file->method('getMimeType')->willReturn($props['mime'] ?? 'application/x-drawio');
        $file->method('getName')->willReturn($props['name'] ?? 'Test.drawio');
        $file->method('getOwner')->willReturn($owner);
        if (!isset($props['etagSequence'])) {
            $file->method('getEtag')->willReturn($props['etag'] ?? 'etag1');
        }
        $file->method('getMTime')->willReturn($props['mtime'] ?? 1700000000);
        $file->method('getCreationTime')->willReturn($props['created'] ?? 1600000000);
        $file->method('getUploadTime')->willReturn($props['uploaded'] ?? 1600000001);
        $file->method('getPath')->willReturn($props['path'] ?? '/admin/files/Test.drawio');
        $file->method('getPermissions')->willReturn($props['permissions'] ?? 27);
        $file->method('getType')->willReturn('file');
        return $file;
    }

    private function givenUserFile(File $file, string $relativePath = '/Test.drawio'): void {
        $this->root->method('getFirstNodeById')->willReturn($file);
        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getRelativePath')->willReturn($relativePath);
        $this->root->method('getUserFolder')->willReturn($userFolder);
    }

    private function createShare(File|Folder $node, int $permissions): IShare&MockObject {
        $share = $this->createMock(IShare::class);
        $share->method('getNode')->willReturn($node);
        $share->method('getPermissions')->willReturn($permissions);
        return $share;
    }

    // ---- load ----

    public function testLoadReturnsFilePayloadForLoggedInUser(): void {
        $this->loginAs();
        $file = $this->createFile();
        $this->givenUserFile($file);

        $this->lockingProvider->expects($this->once())
            ->method('acquireLock')->with('drawio_97', ILockingProvider::LOCK_SHARED);
        $this->lockingProvider->expects($this->once())
            ->method('releaseLock')->with('drawio_97', ILockingProvider::LOCK_SHARED);

        $response = $this->createController()->load(97, null);

        $this->assertInstanceOf(DataResponse::class, $response);
        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('<mxfile/>', $data['xml']);
        $this->assertSame(97, $data['id']);
        $this->assertTrue($data['writeable']);
        $this->assertSame('application/x-drawio', $data['mime']);
        $this->assertSame('/Test.drawio', $data['path']);
        $this->assertSame('Test.drawio', $data['name']);
        $this->assertSame('admin', $data['owner']);
        $this->assertSame('etag1', $data['etag']);
        $this->assertSame(1600000000, $data['created']);
        $this->assertFalse($data['versionsEnabled']);
        $this->assertSame('instance123', $data['instanceId']);
    }

    public function testLoadRejectsFolders(): void {
        $this->loginAs();
        $folder = $this->createMock(Folder::class);
        $folder->method('isReadable')->willReturn(true);
        $this->root->method('getFirstNodeById')->willReturn($folder);
        $userFolder = $this->createMock(Folder::class);
        $this->root->method('getUserFolder')->willReturn($userFolder);

        $response = $this->createController()->load(97, null);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertSame('You can not open a folder', $response->getData()['message']);
    }

    public function testLoadRejectsTooBigFiles(): void {
        $this->loginAs();
        $file = $this->createFile(['size' => 104857601]);
        $this->givenUserFile($file);

        $this->lockingProvider->expects($this->never())->method('acquireLock');

        $response = $this->createController()->load(97, null);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertStringContainsString('too big', $response->getData()['message']);
    }

    public function testLoadWithoutFileIdAndTokenIsBadRequest(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);

        $response = $this->createController()->load(null, null);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertSame('Invalid fileId/shareToken supplied.', $response->getData()['message']);
    }

    public function testLoadUnknownFileIdIsNotFound(): void {
        $this->loginAs();
        $this->root->method('getFirstNodeById')->willReturn(null);

        $response = $this->createController()->load(404, null);

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testLoadUnreadableFileIsForbidden(): void {
        $this->loginAs();
        $file = $this->createFile(['readable' => false]);
        $this->root->method('getFirstNodeById')->willReturn($file);

        $response = $this->createController()->load(97, null);

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testLoadReturns409WhenLockCannotBeAcquired(): void {
        $this->loginAs();
        $file = $this->createFile();
        $this->givenUserFile($file);
        $this->lockingProvider->method('acquireLock')->willThrowException(new LockedException('drawio_97'));
        $this->lockingProvider->expects($this->never())->method('releaseLock');

        $response = $this->createController()->load(97, null);

        $this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
        $this->assertSame('The file is locked.', $response->getData()['message']);
    }

    public function testLoadViaShareTokenForAnonymousVisitor(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $file = $this->createFile(['updateable' => false]);
        $share = $this->createShare($file, Constants::PERMISSION_READ);
        $this->shareManager->method('getShareByToken')->with('tok123')->willReturn($share);
        $this->shareAuth->method('isAuthenticated')->with($share)->willReturn(true);

        $response = $this->createController()->load(null, 'tok123');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('tok123', $data['shareToken']);
        $this->assertNull($data['path']);
        $this->assertFalse($data['versionsEnabled']);
    }

    public function testLoadViaShareTokenDeniedWhenNotAuthenticated(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $file = $this->createFile();
        $share = $this->createShare($file, Constants::PERMISSION_READ);
        $this->shareManager->method('getShareByToken')->willReturn($share);
        $this->shareAuth->method('isAuthenticated')->willReturn(false);

        $response = $this->createController()->load(null, 'tok123');

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        $this->assertSame('Insufficient permissions', $response->getData()['message']);
    }

    public function testLoadFileInSharedFolderResolvesChildNode(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $child = $this->createFile(['id' => 314, 'name' => 'Inner.drawio']);
        $sharedFolder = $this->createMock(Folder::class);
        $sharedFolder->method('getFirstNodeById')->with(314)->willReturn($child);
        $share = $this->createShare($sharedFolder, Constants::PERMISSION_READ);
        $this->shareManager->method('getShareByToken')->willReturn($share);
        $this->shareAuth->method('isAuthenticated')->willReturn(true);

        $response = $this->createController()->load(314, 'tok123');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame(314, $response->getData()['id']);
        $this->assertSame('Inner.drawio', $response->getData()['name']);
    }

    public function testLoadFileMissingInSharedFolderIsNotFound(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $sharedFolder = $this->createMock(Folder::class);
        $sharedFolder->method('getFirstNodeById')->willReturn(null);
        $share = $this->createShare($sharedFolder, Constants::PERMISSION_READ);
        $this->shareManager->method('getShareByToken')->willReturn($share);
        $this->shareAuth->method('isAuthenticated')->willReturn(true);

        $response = $this->createController()->load(999, 'tok123');

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    // ---- getFileInfo ----

    public function testGetFileInfoMapsShareUpdatePermissionToWriteable(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $file = $this->createFile();
        $share = $this->createShare($file, Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE);
        $this->shareManager->method('getShareByToken')->willReturn($share);
        $this->shareAuth->method('isAuthenticated')->willReturn(true);

        $response = $this->createController()->getFileInfo(null, 'tok123');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertTrue($response->getData()['writeable']);
    }

    public function testGetFileInfoReadOnlyShareIsNotWriteable(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $file = $this->createFile();
        $share = $this->createShare($file, Constants::PERMISSION_READ);
        $this->shareManager->method('getShareByToken')->willReturn($share);
        $this->shareAuth->method('isAuthenticated')->willReturn(true);

        $response = $this->createController()->getFileInfo(null, 'tok123');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertFalse($response->getData()['writeable']);
    }

    public function testGetFileInfoFallsBackToUploadTimeWhenNoCreationTime(): void {
        $this->loginAs();
        $file = $this->createFile(['created' => 0, 'uploaded' => 1650000000]);
        $this->givenUserFile($file);

        $response = $this->createController()->getFileInfo(97, null);

        $this->assertSame(1650000000, $response->getData()['created']);
    }

    // ---- save ----

    public function testSavePersistsContentAndReturnsNewEtag(): void {
        $this->loginAs();
        $file = $this->createFile(['etagSequence' => true]);
        $file->method('getEtag')->willReturnOnConsecutiveCalls('etag1', 'etag2');
        $file->expects($this->once())->method('putContent')->with('<mxfile>new</mxfile>');
        $this->givenUserFile($file);

        $this->lockingProvider->expects($this->once())
            ->method('acquireLock')->with('drawio_97', ILockingProvider::LOCK_EXCLUSIVE);
        $this->lockingProvider->expects($this->once())
            ->method('releaseLock')->with('drawio_97', ILockingProvider::LOCK_EXCLUSIVE);

        $response = $this->createController()->save(97, null, '<mxfile>new</mxfile>', 'etag1');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('etag2', $data['etag']);
        $this->assertSame(42, $data['size']);
        $this->assertSame(1700000000, $data['mtime']);
    }

    public function testSaveDetectsEtagConflict(): void {
        $this->loginAs();
        $file = $this->createFile(['etag' => 'current-etag']);
        $file->expects($this->never())->method('putContent');
        $this->givenUserFile($file);

        $this->lockingProvider->expects($this->once())->method('releaseLock');

        $response = $this->createController()->save(97, null, '<mxfile/>', 'stale-etag');

        $this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
        $this->assertSame('The file you are working on was updated in the meantime.', $response->getData()['message']);
    }

    public function testSaveTreatsPostSaveHookFailureAsSuccessWhenEtagChanged(): void {
        $this->loginAs();
        $file = $this->createFile(['etagSequence' => true]);
        // etag1 for the conflict check, etag2 afterwards: the file was written
        $file->method('getEtag')->willReturnOnConsecutiveCalls('etag1', 'etag2', 'etag2');
        $file->method('putContent')->willThrowException(new \RuntimeException('activity hook failed'));
        $this->givenUserFile($file);

        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('Post-save hook error'));

        $response = $this->createController()->save(97, null, '<mxfile/>', 'etag1');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame('etag2', $response->getData()['etag']);
    }

    public function testSaveFailsWhenHookFailsAndEtagUnchanged(): void {
        $this->loginAs();
        $file = $this->createFile(['etag' => 'etag1']);
        $file->method('putContent')->willThrowException(new \RuntimeException('write failed'));
        $this->givenUserFile($file);

        $response = $this->createController()->save(97, null, '<mxfile/>', 'etag1');

        $this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
    }

    public function testSaveWithoutWritePermissionIsForbidden(): void {
        $this->loginAs();
        $file = $this->createFile(['updateable' => false]);
        $this->givenUserFile($file);
        $file->expects($this->never())->method('putContent');

        $response = $this->createController()->save(97, null, '<mxfile/>', 'etag1');

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        $this->assertSame('Insufficient permissions', $response->getData()['message']);
    }

    public function testSaveWithoutEtagComplainsAboutEtag(): void {
        $response = $this->createController()->save(97, null, '<mxfile/>', '');

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertSame('File etag not supplied', $response->getData()['message']);
    }

    public function testSaveWithoutContentComplainsAboutContent(): void {
        $response = $this->createController()->save(97, null, '', 'etag1');

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertSame('File content not supplied', $response->getData()['message']);
    }

    // ---- savePreview ----

    public function testSavePreviewStoresDecodedImage(): void {
        $this->loginAs();
        $file = $this->createFile();
        $this->givenUserFile($file);

        $previewFolder = $this->createMock(ISimpleFolder::class);
        $previewFolder->expects($this->once())->method('newFile')
            ->with('97.png', 'raw-png-bytes');
        $this->appData->method('getFolder')->with('previews')->willReturn($previewFolder);

        $response = $this->createController()->savePreview(97, null, base64_encode('raw-png-bytes'));

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame('OK', $response->getData());
    }

    public function testSavePreviewCreatesPreviewFolderOnDemand(): void {
        $this->loginAs();
        $file = $this->createFile();
        $this->givenUserFile($file);

        $previewFolder = $this->createMock(ISimpleFolder::class);
        $previewFolder->expects($this->once())->method('newFile');
        $this->appData->method('getFolder')->willThrowException(new \OCP\Files\NotFoundException());
        $this->appData->expects($this->once())->method('newFolder')->with('previews')->willReturn($previewFolder);

        $response = $this->createController()->savePreview(97, null, base64_encode('x'));

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
    }

    public function testSavePreviewForbiddenWithoutWritePermission(): void {
        $this->loginAs();
        $file = $this->createFile(['updateable' => false]);
        $this->givenUserFile($file);
        $previewFolder = $this->createMock(ISimpleFolder::class);
        $previewFolder->expects($this->never())->method('newFile');
        $this->appData->method('getFolder')->willReturn($previewFolder);

        $response = $this->createController()->savePreview(97, null, base64_encode('x'));

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testSavePreviewWithoutContentsIsBadRequest(): void {
        $response = $this->createController()->savePreview(97, null, '');

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    // ---- create ----

    public function testCreateReturnsFileInfoArray(): void {
        $this->loginAs();
        $parent = $this->createMock(Folder::class);
        $parent->method('getId')->willReturn(5);

        $file = $this->createFile(['id' => 98, 'name' => 'New.drawio', 'size' => 1, 'etag' => 'etagN', 'mtime' => 1700000001]);
        $file->method('getParent')->willReturn($parent);

        $dir = $this->createMock(Folder::class);
        $dir->method('isCreatable')->willReturn(true);
        $dir->method('getNonExistingName')->with('New.drawio')->willReturn('New.drawio');
        $dir->expects($this->once())->method('newFile')->with('New.drawio', ' ')->willReturn($file);
        $this->root->method('getFirstNodeById')->willReturn($dir);

        $result = $this->createController()->create('New.drawio', 5, null);

        $this->assertSame([
            'id' => 98,
            'parentId' => 5,
            'mtime' => 1700000001000,
            'name' => 'New.drawio',
            'permissions' => 27,
            'mimetype' => 'application/x-drawio',
            'size' => 1,
            'type' => 'file',
            'etag' => 'etagN',
        ], $result);
    }

    public function testCreateWithoutPermissionReturnsError(): void {
        $this->loginAs();
        $dir = $this->createMock(Folder::class);
        $dir->method('isCreatable')->willReturn(false);
        $dir->expects($this->never())->method('newFile');
        $this->root->method('getFirstNodeById')->willReturn($dir);

        $result = $this->createController()->create('New.drawio', 5, null);

        $this->assertSame("You don't have enough permission to create file", $result['error']);
    }

    public function testCreateHandlesNotPermittedException(): void {
        $this->loginAs();
        $dir = $this->createMock(Folder::class);
        $dir->method('isCreatable')->willReturn(true);
        $dir->method('getNonExistingName')->willReturn('New.drawio');
        $dir->method('newFile')->willThrowException(new NotPermittedException());
        $this->root->method('getFirstNodeById')->willReturn($dir);

        $result = $this->createController()->create('New.drawio', 5, null);

        $this->assertSame("Can't create file", $result['error']);
    }

    // ---- index ----

    public function testIndexRedirectsAnonymousUsersToLogin(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $this->request->method('getRequestUri')->willReturn('/apps/drawio/edit?fileId=97');
        $this->urlGenerator->expects($this->once())->method('linkToRoute')
            ->with('core.login.showLoginForm', ['redirect_url' => '/apps/drawio/edit?fileId=97'])
            ->willReturn('/login?redirect_url=x');

        $response = $this->createController()->index(97);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login?redirect_url=x', $response->getRedirectURL());
    }

    public function testIndexRendersEditorTemplateWithCsp(): void {
        $this->loginAs();
        $this->appConfig->method('GetDrawioUrl')->willReturn('https://draw.example.com/editor?custom=1');
        $this->appConfig->method('GetTheme')->willReturn('kennedy');
        $this->appConfig->method('GetDarkMode')->willReturn('auto');
        $this->appConfig->method('GetOfflineMode')->willReturn('no');
        $this->appConfig->method('GetLang')->willReturn('auto');
        $this->appConfig->method('GetAutosave')->willReturn('yes');
        $this->appConfig->method('GetLibraries')->willReturn('no');
        $this->appConfig->method('GetPreviews')->willReturn('yes');
        $this->appConfig->method('GetDrawioConfig')->willReturn('{}');
        $this->l10nFactory->method('findLanguage')->willReturn('pt_BR');

        $response = $this->createController()->index(97, null, false, false);

        $this->assertInstanceOf(TemplateResponse::class, $response);
        $this->assertNotInstanceOf(PublicTemplateResponse::class, $response);
        $this->assertSame('editor', $response->getTemplateName());

        $params = $response->getParams();
        $this->assertSame('https://draw.example.com/editor', $params['drawioUrl']);
        $this->assertSame('custom=1', $params['drawioUrlArgs']);
        $this->assertSame('pt', $params['drawioLang']);
        $this->assertSame(97, $params['fileId']);

        $policy = $response->getContentSecurityPolicy()->buildPolicy();
        $this->assertMatchesRegularExpression('#script-src[^;]*https://draw\.example\.com/editor#', $policy);
        $this->assertMatchesRegularExpression('#frame-src[^;]*https://draw\.example\.com/editor#', $policy);
        $this->assertMatchesRegularExpression('#frame-src[^;]*blob:#', $policy);
        $this->assertMatchesRegularExpression('#worker-src[^;]*https://draw\.example\.com/editor#', $policy);
        $this->assertMatchesRegularExpression('#worker-src[^;]*blob:#', $policy);

        $this->assertContains('drawio/js/editor', self::scripts()['drawio'] ?? []);
        $this->assertContains('drawio/css/editor', \OC_Util::$styles);
    }

    public function testIndexUsesPublicTemplateForShareVisitors(): void {
        $this->userSession->method('isLoggedIn')->willReturn(false);
        $this->userSession->method('getUser')->willReturn(null);
        $this->appConfig->method('GetDrawioUrl')->willReturn('https://embed.diagrams.net');
        $this->appConfig->method('GetTheme')->willReturn('kennedy');
        $this->appConfig->method('GetDarkMode')->willReturn('auto');
        $this->appConfig->method('GetOfflineMode')->willReturn('no');
        $this->appConfig->method('GetLang')->willReturn('en');
        $this->appConfig->method('GetAutosave')->willReturn('yes');
        $this->appConfig->method('GetLibraries')->willReturn('no');
        $this->appConfig->method('GetPreviews')->willReturn('yes');
        $this->appConfig->method('GetDrawioConfig')->willReturn('{}');

        $response = $this->createController()->index(null, 'tok123', false, false);

        $this->assertInstanceOf(PublicTemplateResponse::class, $response);
        $this->assertSame('tok123', $response->getParams()['shareToken']);
    }

    // ---- versions ----

    public function testGetFileRevisionsWithoutVersionManagerIsRejected(): void {
        $response = $this->createController()->getFileRevisions(97);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertSame('Versions plugin is not enabled', $response->getData()['message']);
    }

    public function testGetFileRevisionsListsVersions(): void {
        $this->loginAs();
        $file = $this->createFile();
        $this->givenUserFile($file);

        $version = $this->createMock(IVersion::class);
        $version->method('getRevisionId')->willReturn(1234);
        $version->method('getTimestamp')->willReturn(1700000000);

        $versionManager = $this->createMock(IVersionManager::class);
        $versionManager->method('getVersionsForFile')->willReturn(['v1' => $version]);
        $this->versionManager = $versionManager;

        $response = $this->createController()->getFileRevisions(97);

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame([['revId' => 1234, 'timestamp' => 1700000000]], $response->getData());
    }

    public function testLoadFileVersionReturnsVersionContent(): void {
        $this->loginAs();
        $file = $this->createFile();
        $this->givenUserFile($file);

        $versionFile = $this->createMock(File::class);
        $versionFile->method('getContent')->willReturn('<mxfile>old</mxfile>');
        $versionManager = $this->createMock(IVersionManager::class);
        $versionManager->method('getVersionFile')->willReturn($versionFile);
        $this->versionManager = $versionManager;

        $response = $this->createController()->loadFileVersion(97, '1234');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame('<mxfile>old</mxfile>', $response->getData());
    }

    public function testLoadFileVersionWithoutVersionManagerIsRejected(): void {
        $response = $this->createController()->loadFileVersion(97, '1234');

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }
}
