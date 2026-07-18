<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Reference;

use OCA\Drawio\Reference\DrawioReferenceProvider;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DrawioReferenceProviderTest extends TestCase {

    private IURLGenerator&MockObject $urlGenerator;
    private IRootFolder&MockObject $rootFolder;
    private IUserSession&MockObject $userSession;
    private IShareManager&MockObject $shareManager;

    protected function setUp(): void {
        $this->urlGenerator = $this->createMock(IURLGenerator::class);
        $this->rootFolder = $this->createMock(IRootFolder::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->shareManager = $this->createMock(IShareManager::class);

        $this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(
            static fn (string $path) => 'https://nc.example.com' . $path
        );
        $this->urlGenerator->method('linkToRoute')->willReturnCallback(
            static function (string $route, array $args = []) {
                return '/core/preview?fileId=' . ($args['fileId'] ?? '');
            }
        );
        $this->urlGenerator->method('imagePath')->willReturn('/apps/drawio/img/app.svg');
    }

    private function createProvider(): DrawioReferenceProvider {
        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnCallback(static fn (string $text, $params = []) => $text);

        return new DrawioReferenceProvider(
            $l10n,
            $this->urlGenerator,
            $this->rootFolder,
            $this->userSession,
            $this->shareManager,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function loginAs(string $uid = 'admin'): void {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);
    }

    private function createFile(int $id = 97, string $name = 'Test.drawio'): File&MockObject {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn($id);
        $file->method('getName')->willReturn($name);
        $file->method('getMimeType')->willReturn('application/x-drawio');
        return $file;
    }

    public function testDiscoverableMetadata(): void {
        $provider = $this->createProvider();

        $this->assertSame('drawio-diagram', $provider->getId());
        $this->assertSame('Diagrams', $provider->getTitle());
        $this->assertSame(['files'], $provider->getSupportedSearchProviderIds());
        $this->assertSame('https://nc.example.com/apps/drawio/img/app.svg', $provider->getIconUrl());
    }

    public function testMatchReferenceAcceptsEditorUrls(): void {
        $provider = $this->createProvider();

        $this->assertTrue($provider->matchReference('https://nc.example.com/apps/drawio/edit?fileId=97'));
        $this->assertTrue($provider->matchReference('https://nc.example.com/index.php/apps/drawio/edit?fileId=97'));
    }

    public function testMatchReferenceRejectsForeignUrls(): void {
        $provider = $this->createProvider();

        $this->assertFalse($provider->matchReference('https://evil.example.com/apps/drawio/edit?fileId=97'));
        $this->assertFalse($provider->matchReference('https://nc.example.com/apps/files/'));
    }

    public function testResolveReferenceBuildsRichObjectForOwnFile(): void {
        $this->loginAs();
        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->with(97)->willReturn([$this->createFile()]);
        $this->rootFolder->method('getUserFolder')->with('admin')->willReturn($userFolder);

        $url = 'https://nc.example.com/apps/drawio/edit?fileId=97';
        $reference = $this->createProvider()->resolveReference($url);

        $this->assertNotNull($reference);
        $this->assertSame('Test.drawio', $reference->getTitle());
        $this->assertSame('drawio_diagram', $reference->getRichObjectType());
        $richObject = $reference->getRichObject();
        $this->assertSame(97, $richObject['id']);
        $this->assertSame('Test.drawio', $richObject['name']);
        $this->assertSame('application/x-drawio', $richObject['mime']);
        $this->assertSame($url, $richObject['editUrl']);
        $this->assertSame('https://nc.example.com/core/preview?fileId=97', $richObject['previewUrl']);
    }

    public function testResolveReferenceReturnsNullForAnonymousUsersWithoutToken(): void {
        $this->userSession->method('getUser')->willReturn(null);

        $reference = $this->createProvider()->resolveReference('https://nc.example.com/apps/drawio/edit?fileId=97');

        $this->assertNull($reference);
    }

    public function testResolveReferenceReturnsNullWithoutFileId(): void {
        $this->loginAs();

        $reference = $this->createProvider()->resolveReference('https://nc.example.com/apps/drawio/edit?foo=1');

        $this->assertNull($reference);
    }

    public function testResolveReferenceReturnsNullForUnknownFile(): void {
        $this->loginAs();
        $userFolder = $this->createMock(Folder::class);
        $userFolder->method('getById')->willReturn([]);
        $this->rootFolder->method('getUserFolder')->willReturn($userFolder);

        $reference = $this->createProvider()->resolveReference('https://nc.example.com/apps/drawio/edit?fileId=404');

        $this->assertNull($reference);
    }

    public function testResolveReferenceWithShareTokenUsesShareNode(): void {
        $share = $this->createMock(IShare::class);
        $share->method('getNode')->willReturn($this->createFile(97, 'Shared.drawio'));
        $this->shareManager->method('getShareByToken')->with('tok123')->willReturn($share);

        $url = 'https://nc.example.com/apps/drawio/edit?fileId=97&shareToken=tok123';
        $reference = $this->createProvider()->resolveReference($url);

        $this->assertNotNull($reference);
        $this->assertSame('Shared.drawio', $reference->getTitle());
    }

    public function testResolveReferenceWithShareTokenResolvesFileInSharedFolder(): void {
        $folder = $this->createMock(Folder::class);
        $folder->method('getFirstNodeById')->with(314)->willReturn($this->createFile(314, 'Inner.drawio'));
        $share = $this->createMock(IShare::class);
        $share->method('getNode')->willReturn($folder);
        $this->shareManager->method('getShareByToken')->willReturn($share);

        $reference = $this->createProvider()->resolveReference(
            'https://nc.example.com/apps/drawio/edit?fileId=314&shareToken=tok123'
        );

        $this->assertNotNull($reference);
        $this->assertSame('Inner.drawio', $reference->getTitle());
    }

    public function testResolveReferenceWithShareTokenReturnsNullWhenFileMissingInFolder(): void {
        $folder = $this->createMock(Folder::class);
        $folder->method('getFirstNodeById')->willReturn(null);
        $share = $this->createMock(IShare::class);
        $share->method('getNode')->willReturn($folder);
        $this->shareManager->method('getShareByToken')->willReturn($share);

        $reference = $this->createProvider()->resolveReference(
            'https://nc.example.com/apps/drawio/edit?fileId=999&shareToken=tok123'
        );

        $this->assertNull($reference);
    }

    public function testGetCachePrefixCombinesFileIdAndToken(): void {
        $provider = $this->createProvider();

        $this->assertSame(
            '97-tok123',
            $provider->getCachePrefix('https://nc.example.com/apps/drawio/edit?fileId=97&shareToken=tok123')
        );
        $this->assertSame('-', $provider->getCachePrefix('https://nc.example.com/apps/drawio/edit'));
        $this->assertNull($provider->getCacheKey('anything'));
    }
}
