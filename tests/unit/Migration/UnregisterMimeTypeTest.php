<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Migration;

use OCA\Drawio\Migration\UnregisterMimeType;
use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UnregisterMimeTypeTest extends TestCase {

    private string $root;
    private string $configDir;
    private string $coreIconDir;
    private IMimeTypeLoader&MockObject $mimeTypeLoader;

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . '/drawio-test-' . uniqid();
        $this->configDir = $this->root . '/config/';
        $this->coreIconDir = $this->root . '/core/img/filetypes/';
        mkdir($this->configDir, 0777, true);
        mkdir($this->coreIconDir, 0777, true);

        \OC::$configDir = $this->configDir;
        \OC::$SERVERROOT = $this->root;

        $this->mimeTypeLoader = $this->createMock(IMimeTypeLoader::class);
    }

    protected function tearDown(): void {
        foreach (glob($this->configDir . '*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->coreIconDir . '*') ?: [] as $file) {
            unlink($file);
        }
        foreach ([$this->configDir, $this->root . '/core/img/filetypes', $this->root . '/core/img', $this->root . '/core', $this->root] as $dir) {
            @rmdir($dir);
        }
    }

    private function runStep(): void {
        $step = new UnregisterMimeType($this->mimeTypeLoader);
        $this->assertSame('Unregister MIME type for Diagramming', $step->getName());
        $step->run($this->createMock(IOutput::class));
    }

    public function testRunRemovesOnlyDrawioEntries(): void {
        file_put_contents($this->configDir . 'mimetypemapping.json', json_encode([
            'drawio' => ['application/x-drawio'],
            'dwb' => ['application/x-drawio-wb'],
            'mind' => ['application/x-mind'],
        ]));
        file_put_contents($this->configDir . 'mimetypealiases.json', json_encode([
            'application/x-drawio' => 'drawio',
            'application/x-drawio-wb' => 'dwb',
            'application/x-mind' => 'mind',
        ]));

        $this->mimeTypeLoader->method('getId')->with('application/octet-stream')->willReturn(3);
        $filecacheUpdates = [];
        $this->mimeTypeLoader->expects($this->exactly(2))->method('updateFilecache')
            ->willReturnCallback(function (string $ext, int $mimeTypeId) use (&$filecacheUpdates): int {
                $filecacheUpdates[$ext] = $mimeTypeId;
                return 1;
            });

        $this->runStep();

        $this->assertSame(['drawio' => 3, 'dwb' => 3], $filecacheUpdates);

        $mapping = json_decode((string)file_get_contents($this->configDir . 'mimetypemapping.json'), true);
        $this->assertArrayNotHasKey('drawio', $mapping);
        $this->assertArrayNotHasKey('dwb', $mapping);
        $this->assertSame(['application/x-mind'], $mapping['mind']);

        $aliases = json_decode((string)file_get_contents($this->configDir . 'mimetypealiases.json'), true);
        $this->assertArrayNotHasKey('application/x-drawio', $aliases);
        $this->assertArrayNotHasKey('application/x-drawio-wb', $aliases);
        $this->assertSame('mind', $aliases['application/x-mind']);
    }

    public function testRunRemovesIconsFromTheNextcloudCore(): void {
        file_put_contents($this->coreIconDir . 'drawio.svg', '<svg/>');
        file_put_contents($this->coreIconDir . 'dwb.svg', '<svg/>');
        file_put_contents($this->coreIconDir . 'text.svg', '<svg/>');
        $this->mimeTypeLoader->method('getId')->willReturn(3);
        $this->mimeTypeLoader->method('updateFilecache')->willReturn(1);

        $this->runStep();

        $this->assertFileDoesNotExist($this->coreIconDir . 'drawio.svg');
        $this->assertFileDoesNotExist($this->coreIconDir . 'dwb.svg');
        $this->assertFileExists($this->coreIconDir . 'text.svg');
    }
}
