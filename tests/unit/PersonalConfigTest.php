<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit;

use OCA\Drawio\PersonalConfig;
use OCP\Config\IUserConfig;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

final class PersonalConfigTest extends TestCase {

    /** @var array<string, string> */
    private array $stored = [];

    private function createConfig(array $stored = [], ?string $uid = 'alice'): PersonalConfig {
        $this->stored = $stored;

        $userConfig = $this->createMock(IUserConfig::class);
        $userConfig->method('getValueString')->willReturnCallback(
            fn (string $userId, string $app, string $key, string $default = '') => $this->stored[$key] ?? $default
        );
        $userConfig->method('setValueString')->willReturnCallback(
            function (string $userId, string $app, string $key, string $value): bool {
                $this->stored[$key] = $value;
                return true;
            }
        );

        $userSession = $this->createMock(IUserSession::class);
        if ($uid === null) {
            $userSession->method('getUser')->willReturn(null);
        } else {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($uid);
            $userSession->method('getUser')->willReturn($user);
        }

        return new PersonalConfig($userConfig, $userSession, $this->createMock(LoggerInterface::class));
    }

    public function testDefaultsAreTheAdminFallbackSentinels(): void {
        $config = $this->createConfig();

        $this->assertSame('default', $config->GetTheme());
        $this->assertSame('auto', $config->GetLang());
        $this->assertSame('auto', $config->GetDarkMode());
    }

    public function testStoredPerUserValuesAreReturned(): void {
        $config = $this->createConfig([
            'DrawioTheme' => 'atlas',
            'DrawioLang' => 'de',
            'DrawioDarkMode' => 'on',
        ]);

        $this->assertSame('atlas', $config->GetTheme());
        $this->assertSame('de', $config->GetLang());
        $this->assertSame('on', $config->GetDarkMode());
    }

    public function testSettersPersistPerUserValues(): void {
        $config = $this->createConfig();

        $config->SetTheme('simple');
        $config->SetLang('fr');
        $config->SetDarkMode('off');

        $this->assertSame('simple', $this->stored['DrawioTheme']);
        $this->assertSame('fr', $this->stored['DrawioLang']);
        $this->assertSame('off', $this->stored['DrawioDarkMode']);
    }

    public function testDarkModeFallsBackToYesForDarkTheme(): void {
        $config = $this->createConfig(['DrawioTheme' => 'dark']);

        $this->assertSame('yes', $config->GetDarkMode());
    }

    /**
     * Anonymous visitors have no personal config and must never touch the
     * IUserConfig store (which requires a user id); every getter returns the
     * admin-fallback sentinel.
     */
    public function testAnonymousUserGetsDefaultsAndDoesNotPersist(): void {
        $config = $this->createConfig(uid: null);

        $config->SetTheme('atlas');

        $this->assertSame('default', $config->GetTheme());
        $this->assertSame('auto', $config->GetLang());
        $this->assertSame([], $this->stored, 'Nothing must be written for an anonymous user');
    }
}
