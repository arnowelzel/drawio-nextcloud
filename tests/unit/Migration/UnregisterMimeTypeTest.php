<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Migration;

use OCA\Drawio\Migration\UnregisterMimeType;
use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UnregisterMimeTypeTest extends TestCase {

    private string $configDir;
    private IMimeTypeLoader&MockObject $mimeTypeLoader;

    protected function setUp(): void {
        $this->configDir = sys_get_temp_dir() . '/drawio-test-' . uniqid() . '/';
        mkdir($this->configDir);
        \OC::$configDir = $this->configDir;

        $this->mimeTypeLoader = $this->createMock(IMimeTypeLoader::class);
    }

    protected function tearDown(): void {
        foreach (['mimetypemapping.json', 'mimetypealiases.json'] as $file) {
            @unlink($this->configDir . $file);
        }
        @rmdir($this->configDir);
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

        $step = new UnregisterMimeType($this->mimeTypeLoader);
        $this->assertSame('Unregister MIME type for Diagramming', $step->getName());
        $step->run($this->createMock(IOutput::class));

        $this->assertSame(['drawio' => 3, 'dwb' => 3], $filecacheUpdates);

        $mapping = json_decode(file_get_contents($this->configDir . 'mimetypemapping.json'), true);
        $this->assertArrayNotHasKey('drawio', $mapping);
        $this->assertArrayNotHasKey('dwb', $mapping);
        $this->assertSame(['application/x-mind'], $mapping['mind']);

        $aliases = json_decode(file_get_contents($this->configDir . 'mimetypealiases.json'), true);
        $this->assertArrayNotHasKey('application/x-drawio', $aliases);
        $this->assertArrayNotHasKey('application/x-drawio-wb', $aliases);
        $this->assertSame('mind', $aliases['application/x-mind']);
    }
}
