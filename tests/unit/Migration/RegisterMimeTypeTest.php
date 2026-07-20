<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Migration;

use OCA\Drawio\Migration\RegisterMimeType;
use OCP\Files\IMimeTypeLoader;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterMimeTypeTest extends TestCase {

    private string $root;
    private string $configDir;
    private string $coreIconDir;
    private IMimeTypeLoader&MockObject $mimeTypeLoader;

    /** @var array<string, string> */
    private array $appValues = [];

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . '/drawio-test-' . uniqid();
        $this->configDir = $this->root . '/config/';
        $this->coreIconDir = $this->root . '/core/img/filetypes/';
        mkdir($this->configDir, 0777, true);
        mkdir($this->coreIconDir, 0777, true);

        \OC::$configDir = $this->configDir;
        \OC::$SERVERROOT = $this->root;

        $this->mimeTypeLoader = $this->createMock(IMimeTypeLoader::class);
        $this->mimeTypeLoader->method('getId')->willReturnMap([
            ['application/x-drawio', 7],
            ['application/x-drawio-wb', 8],
        ]);
        $this->mimeTypeLoader->method('updateFilecache')->willReturn(1);

        $this->appValues = [];
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

    private function createAppConfig(): IAppConfig&MockObject {
        $appConfig = $this->createMock(IAppConfig::class);
        $appConfig->method('getValueString')->willReturnCallback(
            fn (string $app, string $key, string $default = '') => $this->appValues[$key] ?? $default
        );
        $appConfig->method('setValueString')->willReturnCallback(
            function (string $app, string $key, string $value): bool {
                $this->appValues[$key] = $value;
                return true;
            }
        );
        return $appConfig;
    }

    private function runStep(): void {
        $step = new RegisterMimeType($this->mimeTypeLoader, $this->createAppConfig());
        $this->assertSame('Register MIME types for Diagramming', $step->getName());
        $step->run($this->createMock(IOutput::class));
    }

    private function writeLegacyIcons(): void {
        file_put_contents($this->coreIconDir . 'drawio.svg', '<svg>old drawio</svg>');
        file_put_contents($this->coreIconDir . 'dwb.svg', '<svg>old dwb</svg>');
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(string $name): array {
        return json_decode((string)file_get_contents($this->configDir . $name), true);
    }

    public function testRunWritesMimeTypeMapping(): void {
        $filecacheUpdates = [];
        $this->mimeTypeLoader = $this->createMock(IMimeTypeLoader::class);
        $this->mimeTypeLoader->method('getId')->willReturnMap([
            ['application/x-drawio', 7],
            ['application/x-drawio-wb', 8],
        ]);
        $this->mimeTypeLoader->expects($this->exactly(2))->method('updateFilecache')
            ->willReturnCallback(function (string $ext, int $mimeTypeId) use (&$filecacheUpdates): int {
                $filecacheUpdates[$ext] = $mimeTypeId;
                return 1;
            });

        $this->runStep();

        $this->assertSame(['drawio' => 7, 'dwb' => 8], $filecacheUpdates);

        $mapping = $this->readConfig('mimetypemapping.json');
        $this->assertSame(['application/x-drawio'], $mapping['drawio']);
        $this->assertSame(['application/x-drawio-wb'], $mapping['dwb']);
    }

    /**
     * Regression test: writing the icon aliases makes the next
     * "occ maintenance:mimetype:update-js" bake them into
     * core/js/mimetypelist.js, which breaks the code integrity check. The app
     * does not ship the icons they refer to since 4.3.0.
     */
    public function testRunDoesNotWriteIconAliases(): void {
        $this->runStep();

        $this->assertFileDoesNotExist($this->configDir . 'mimetypealiases.json');
    }

    public function testRunRemovesAliasesWrittenByOlderVersions(): void {
        file_put_contents($this->configDir . 'mimetypealiases.json', json_encode([
            'application/x-drawio' => 'drawio',
            'application/x-drawio-wb' => 'dwb',
            'application/x-mind' => 'mind',
        ]));

        $this->runStep();

        $aliases = $this->readConfig('mimetypealiases.json');
        $this->assertArrayNotHasKey('application/x-drawio', $aliases);
        $this->assertArrayNotHasKey('application/x-drawio-wb', $aliases);
        $this->assertSame('mind', $aliases['application/x-mind'], "Other apps' aliases must be kept");
    }

    public function testEmptiedAliasFileStaysAJsonObject(): void {
        file_put_contents($this->configDir . 'mimetypealiases.json', json_encode([
            'application/x-drawio' => 'drawio',
            'application/x-drawio-wb' => 'dwb',
        ]));

        $this->runStep();

        $contents = trim((string)file_get_contents($this->configDir . 'mimetypealiases.json'));
        $this->assertSame('{}', $contents, 'Nextcloud expects a JSON object, not an empty array');
    }

    /**
     * Regression test for https://github.com/jgraph/drawio-nextcloud/issues/70:
     * versions up to 4.2.x copied the file type icons into the Nextcloud core,
     * where they are reported as EXTRA_FILE by "occ integrity:check-core".
     */
    public function testRunRemovesIconsCopiedIntoTheCoreByOlderVersions(): void {
        $this->writeLegacyIcons();

        $this->runStep();

        $this->assertFileDoesNotExist($this->coreIconDir . 'drawio.svg');
        $this->assertFileDoesNotExist($this->coreIconDir . 'dwb.svg');
    }

    public function testRunKeepsUnrelatedCoreIcons(): void {
        file_put_contents($this->coreIconDir . 'text.svg', '<svg>text</svg>');

        $this->runStep();

        $this->assertFileExists($this->coreIconDir . 'text.svg');
    }

    public function testCleanupRunsOnlyOnce(): void {
        $this->writeLegacyIcons();
        $this->runStep();
        $this->assertFileDoesNotExist($this->coreIconDir . 'drawio.svg');

        // An administrator restoring the icons afterwards keeps them
        $this->writeLegacyIcons();
        $this->runStep();

        $this->assertFileExists($this->coreIconDir . 'drawio.svg');
        $this->assertFileExists($this->coreIconDir . 'dwb.svg');
    }

    public function testRunPreservesForeignEntriesInExistingConfigFiles(): void {
        file_put_contents($this->configDir . 'mimetypemapping.json', json_encode(['mind' => ['application/x-mind']]));

        $this->runStep();

        $mapping = $this->readConfig('mimetypemapping.json');
        $this->assertSame(['application/x-mind'], $mapping['mind']);
        $this->assertSame(['application/x-drawio'], $mapping['drawio']);
    }

    public function testRunSucceedsWhenCoreIconsAreNotWritable(): void {
        // No icons and no core directory at all must not fail the upgrade
        foreach (glob($this->coreIconDir . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->coreIconDir);

        $this->runStep();

        $this->assertFileExists($this->configDir . 'mimetypemapping.json');
    }
}
