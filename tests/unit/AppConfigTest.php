<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit;

use OCA\Drawio\AppConfig;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AppConfigTest extends TestCase {

    /** @var array<string, string> */
    private array $stored = [];

    private function createAppConfig(array $stored = []): AppConfig {
        $this->stored = $stored;

        $config = $this->createMock(IAppConfig::class);
        $config->method('getAppValueString')->willReturnCallback(
            fn (string $key, string $default = '') => $this->stored[$key] ?? $default
        );
        $config->method('setAppValueString')->willReturnCallback(
            function (string $key, string $value) {
                $this->stored[$key] = $value;
                return true;
            }
        );

        return new AppConfig($config, $this->createMock(LoggerInterface::class));
    }

    public function testDefaultsWhenNothingStored(): void {
        $config = $this->createAppConfig();

        $this->assertSame('https://embed.diagrams.net', $config->GetDrawioUrl());
        $this->assertSame('no', $config->GetOfflineMode());
        $this->assertSame('kennedy', $config->GetTheme());
        $this->assertSame('auto', $config->GetLang());
        $this->assertSame('yes', $config->GetAutosave());
        $this->assertSame('no', $config->GetLibraries());
        $this->assertSame('auto', $config->GetDarkMode());
        $this->assertSame('yes', $config->GetPreviews());
        $this->assertSame('yes', $config->GetWhiteboards());
        $this->assertSame('{}', $config->GetDrawioConfig());
    }

    public function testStoredValuesAreReturned(): void {
        $config = $this->createAppConfig([
            'DrawioUrl' => 'https://draw.example.com',
            'DrawioTheme' => 'min',
            'DrawioOffline' => 'yes',
        ]);

        $this->assertSame('https://draw.example.com', $config->GetDrawioUrl());
        $this->assertSame('min', $config->GetTheme());
        $this->assertSame('yes', $config->GetOfflineMode());
    }

    public function testSetDrawioUrlPrependsSchemeWhenMissing(): void {
        $config = $this->createAppConfig();

        $config->SetDrawioUrl('draw.example.com/editor');

        $this->assertSame('http://draw.example.com/editor', $this->stored['DrawioUrl']);
    }

    public function testSetDrawioUrlTrimsAndLowercases(): void {
        $config = $this->createAppConfig();

        $config->SetDrawioUrl('  HTTPS://Draw.Example.Com  ');

        $this->assertSame('https://draw.example.com', $this->stored['DrawioUrl']);
    }

    public function testSetDrawioUrlKeepsEmptyValue(): void {
        $config = $this->createAppConfig();

        $config->SetDrawioUrl('   ');

        $this->assertSame('', $this->stored['DrawioUrl']);
    }

    public function testLegacyDrawioUrlFallsBackToDefault(): void {
        foreach (['https://draw.io', 'https://www.draw.io', 'http://draw.io', 'http://www.draw.io'] as $legacy) {
            $config = $this->createAppConfig(['DrawioUrl' => $legacy]);
            $this->assertSame('https://embed.diagrams.net', $config->GetDrawioUrl(), "for $legacy");
        }
    }

    public function testDarkModeFallsBackToYesForLegacyDarkTheme(): void {
        $config = $this->createAppConfig(['DrawioTheme' => 'dark']);

        $this->assertSame('yes', $config->GetDarkMode());
    }

    public function testDarkModeStoredValueWins(): void {
        $config = $this->createAppConfig(['DrawioDarkMode' => 'on', 'DrawioTheme' => 'dark']);

        $this->assertSame('on', $config->GetDarkMode());
    }

    public function testSetDrawioConfigRejectsInvalidJson(): void {
        $config = $this->createAppConfig();

        $config->SetDrawioConfig('{not valid json');

        $this->assertSame('', $this->stored['DrawioConfig']);
    }

    public function testSetDrawioConfigStoresValidJson(): void {
        $config = $this->createAppConfig();

        $config->SetDrawioConfig('{"defaultFonts":["Humor Sans"]}');

        $this->assertSame('{"defaultFonts":["Humor Sans"]}', $this->stored['DrawioConfig']);
    }

    public function testGetDrawioConfigReturnsEmptyObjectForInvalidStoredValue(): void {
        $config = $this->createAppConfig(['DrawioConfig' => 'garbage']);

        $this->assertSame('{}', $config->GetDrawioConfig());
    }

    public function testGetDrawioConfigReturnsStoredJson(): void {
        $config = $this->createAppConfig(['DrawioConfig' => '{"a":1}']);

        $this->assertSame('{"a":1}', $config->GetDrawioConfig());
    }

    public function testSettersPersistPlainValues(): void {
        $config = $this->createAppConfig();

        $config->SetOfflineMode('yes');
        $config->SetTheme('atlas');
        $config->SetLang('de');
        $config->SetAutosave('no');
        $config->SetLibraries('yes');
        $config->SetDarkMode('off');
        $config->SetPreviews('no');
        $config->SetWhiteboards('no');

        $this->assertSame('yes', $this->stored['DrawioOffline']);
        $this->assertSame('atlas', $this->stored['DrawioTheme']);
        $this->assertSame('de', $this->stored['DrawioLang']);
        $this->assertSame('no', $this->stored['DrawioAutosave']);
        $this->assertSame('yes', $this->stored['DrawioLibraries']);
        $this->assertSame('off', $this->stored['DrawioDarkMode']);
        $this->assertSame('no', $this->stored['DrawioPreviews']);
        $this->assertSame('no', $this->stored['DrawioWhiteboards']);
    }
}
