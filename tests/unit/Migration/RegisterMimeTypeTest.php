<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Migration;

use OCA\Drawio\Migration\RegisterMimeType;
use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterMimeTypeTest extends TestCase {

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

    private function runStep(): void {
        $step = new RegisterMimeType($this->mimeTypeLoader);
        $this->assertSame('Register MIME types for Diagramming', $step->getName());
        $step->run($this->createMock(IOutput::class));
    }

    public function testRunWritesMimeTypeConfigFiles(): void {
        $this->mimeTypeLoader->method('getId')->willReturnMap([
            ['application/x-drawio', 7],
            ['application/x-drawio-wb', 8],
        ]);
        $filecacheUpdates = [];
        $this->mimeTypeLoader->expects($this->exactly(2))->method('updateFilecache')
            ->willReturnCallback(function (string $ext, int $mimeTypeId) use (&$filecacheUpdates): int {
                $filecacheUpdates[$ext] = $mimeTypeId;
                return 1;
            });

        $this->runStep();

        $this->assertSame(['drawio' => 7, 'dwb' => 8], $filecacheUpdates);

        $mapping = json_decode(file_get_contents($this->configDir . 'mimetypemapping.json'), true);
        $this->assertSame(['application/x-drawio'], $mapping['drawio']);
        $this->assertSame(['application/x-drawio-wb'], $mapping['dwb']);

        $aliases = json_decode(file_get_contents($this->configDir . 'mimetypealiases.json'), true);
        $this->assertSame('drawio', $aliases['application/x-drawio']);
        $this->assertSame('dwb', $aliases['application/x-drawio-wb']);
    }

    public function testRunPreservesForeignEntriesInExistingConfigFiles(): void {
        file_put_contents($this->configDir . 'mimetypemapping.json', json_encode(['mind' => ['application/x-mind']]));
        file_put_contents($this->configDir . 'mimetypealiases.json', json_encode(['application/x-mind' => 'mind']));

        $this->runStep();

        $mapping = json_decode(file_get_contents($this->configDir . 'mimetypemapping.json'), true);
        $this->assertSame(['application/x-mind'], $mapping['mind']);
        $this->assertSame(['application/x-drawio'], $mapping['drawio']);

        $aliases = json_decode(file_get_contents($this->configDir . 'mimetypealiases.json'), true);
        $this->assertSame('mind', $aliases['application/x-mind']);
        $this->assertSame('drawio', $aliases['application/x-drawio']);
    }
}
