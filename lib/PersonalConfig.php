<?php

declare(strict_types=1);

namespace OCA\Drawio;

use OCA\Drawio\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Per-user editor preferences. Each getter falls back to a sentinel value
 * ("default"/"auto") that tells the editor to use the admin default from
 * {@see AppConfig}. Anonymous visitors have no personal config, so every
 * getter returns that sentinel.
 */
class PersonalConfig {

    private const DEFAULT_THEME = 'default';
    private const DEFAULT_LANG = 'auto';
    private const DEFAULT_DARK_MODE = 'auto';

    private const KEY_THEME = 'DrawioTheme';
    private const KEY_LANG = 'DrawioLang';
    private const KEY_DARK_MODE = 'DrawioDarkMode';

    private readonly ?string $uid;

    public function __construct(
        private readonly IUserConfig $config,
        IUserSession $userSession,
        private readonly LoggerInterface $logger,
    ) {
        $this->uid = $userSession->getUser()?->getUID();
    }

    public function SetTheme(string $theme): void
    {
        $this->set(self::KEY_THEME, $theme);
    }

    public function GetTheme(): string
    {
        return $this->get(self::KEY_THEME, self::DEFAULT_THEME);
    }

    public function SetLang(string $lang): void
    {
        $this->set(self::KEY_LANG, $lang);
    }

    public function GetLang(): string
    {
        return $this->get(self::KEY_LANG, self::DEFAULT_LANG);
    }

    public function SetDarkMode(string $darkmode): void
    {
        $this->set(self::KEY_DARK_MODE, $darkmode);
    }

    public function GetDarkMode(): string
    {
        $val = $this->get(self::KEY_DARK_MODE, '');

        if ($val !== '') {
            return $val;
        }

        // A user who picked the dark theme gets dark mode by default
        return $this->GetTheme() === 'dark' ? 'yes' : self::DEFAULT_DARK_MODE;
    }

    private function get(string $key, string $default): string
    {
        if ($this->uid === null) {
            return $default;
        }

        $val = $this->config->getValueString($this->uid, Application::APP_ID, $key);

        return $val === '' ? $default : $val;
    }

    private function set(string $key, string $value): void
    {
        if ($this->uid === null) {
            return;
        }

        $this->logger->info('Setting personal ' . $key . ': ' . $value, ['app' => Application::APP_ID]);
        $this->config->setValueString($this->uid, Application::APP_ID, $key, $value);
    }
}
