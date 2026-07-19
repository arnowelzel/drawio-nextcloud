<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Preview;

use OCA\Drawio\AppConfig;
use OCA\Drawio\Preview\DrawioPreview;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DrawioPreviewTest extends TestCase {

    private IAppData&MockObject $appData;
    private AppConfig&MockObject $appConfig;

    protected function setUp(): void {
        $this->appData = $this->createMock(IAppData::class);
        $this->appConfig = $this->createMock(AppConfig::class);
    }

    private function createProvider(): DrawioPreview {
        return new DrawioPreview($this->createMock(LoggerInterface::class), $this->appData, $this->appConfig);
    }

    private function givenPreviewFile(int $fileId, int $mtime): void {
        $previewFile = $this->createMock(ISimpleFile::class);
        $previewFile->method('getMtime')->willReturn($mtime);
        $folder = $this->createMock(ISimpleFolder::class);
        $folder->method('getFile')->with($fileId . '.png')->willReturn($previewFile);
        $this->appData->method('getFolder')->with('previews')->willReturn($folder);
    }

    private function createFileInfo(int $id = 42, int $mtime = 1000): FileInfo&MockObject {
        $info = $this->createMock(FileInfo::class);
        $info->method('getId')->willReturn($id);
        $info->method('getMtime')->willReturn($mtime);
        return $info;
    }

    public function testMimeTypeRegexMatchesBothMimeTypes(): void {
        $regex = DrawioPreview::getMimeTypeRegex();

        $this->assertSame(1, preg_match($regex, 'application/x-drawio'));
        $this->assertSame(1, preg_match($regex, 'application/x-drawio-wb'));
        $this->assertSame(0, preg_match($regex, 'text/plain'));
        $this->assertSame($regex, $this->createProvider()->getMimeType());
    }

    public function testIsAvailableWhenPreviewIsFresh(): void {
        $this->appConfig->method('GetPreviews')->willReturn('yes');
        $this->givenPreviewFile(42, 2000);

        $this->assertTrue($this->createProvider()->isAvailable($this->createFileInfo(42, 1000)));
    }

    public function testIsNotAvailableWhenPreviewIsStale(): void {
        $this->appConfig->method('GetPreviews')->willReturn('yes');
        $this->givenPreviewFile(42, 500);

        $this->assertFalse($this->createProvider()->isAvailable($this->createFileInfo(42, 1000)));
    }

    public function testIsNotAvailableWhenPreviewsDisabled(): void {
        $this->appConfig->method('GetPreviews')->willReturn('no');
        $this->givenPreviewFile(42, 2000);

        $this->assertFalse($this->createProvider()->isAvailable($this->createFileInfo(42, 1000)));
    }

    public function testIsNotAvailableWhenPreviewFileMissing(): void {
        $this->appConfig->method('GetPreviews')->willReturn('yes');
        $this->appData->method('getFolder')->willThrowException(new NotFoundException());

        $this->assertFalse($this->createProvider()->isAvailable($this->createFileInfo()));
    }

    public function testGetThumbnailReturnsNullWhenPreviewsDisabled(): void {
        $this->appConfig->method('GetPreviews')->willReturn('no');
        $this->givenPreviewFile(42, 2000);
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(42);

        $this->assertNull($this->createProvider()->getThumbnail($file, 256, 256));
    }

    public function testGetThumbnailReturnsNullWhenPreviewFileMissing(): void {
        $this->appConfig->method('GetPreviews')->willReturn('yes');
        $this->appData->method('getFolder')->willThrowException(new NotFoundException());
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(42);

        $this->assertNull($this->createProvider()->getThumbnail($file, 256, 256));
    }
}
