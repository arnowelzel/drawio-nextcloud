<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Listeners;

use OCA\Drawio\Listeners\FileDeleteListener;
use OCP\EventDispatcher\Event;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FileDeleteListenerTest extends TestCase {

    private LoggerInterface&MockObject $logger;
    private IAppData&MockObject $appData;

    protected function setUp(): void {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->appData = $this->createMock(IAppData::class);
    }

    private function createListener(): FileDeleteListener {
        return new FileDeleteListener($this->logger, $this->appData);
    }

    public function testDeletesCachedPreviewOfDeletedFile(): void {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(42);

        $previewFile = $this->createMock(ISimpleFile::class);
        $previewFile->expects($this->once())->method('delete');
        $folder = $this->createMock(ISimpleFolder::class);
        $folder->method('getFile')->with('42.png')->willReturn($previewFile);
        $this->appData->method('getFolder')->with('previews')->willReturn($folder);

        $this->createListener()->handle(new NodeDeletedEvent($file));
    }

    public function testIgnoresDeletedFolders(): void {
        $folder = $this->createMock(Folder::class);
        $this->appData->expects($this->never())->method('getFolder');

        $this->createListener()->handle(new NodeDeletedEvent($folder));
    }

    public function testMissingPreviewIsSilentlyIgnored(): void {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(42);
        $this->appData->method('getFolder')->willThrowException(new NotFoundException());
        $this->logger->expects($this->never())->method('error');

        $this->createListener()->handle(new NodeDeletedEvent($file));
    }

    public function testUnexpectedErrorsAreLoggedButNotThrown(): void {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(42);
        $this->appData->method('getFolder')->willThrowException(new \RuntimeException('disk failure'));
        $this->logger->expects($this->once())->method('error');

        $this->createListener()->handle(new NodeDeletedEvent($file));
    }

    public function testIgnoresUnrelatedEvents(): void {
        $this->appData->expects($this->never())->method('getFolder');

        $this->createListener()->handle(new class extends Event {
        });
    }
}
