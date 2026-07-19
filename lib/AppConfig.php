<?php

declare(strict_types=1);

/**
 *
 * @author Pawel Rojek <pawel at pawelrojek.com>
 * @author Ian Reinhart Geiser <igeiser at devonit.com>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 *
 **/

namespace OCA\Drawio;

use OCA\Drawio\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use Psr\Log\LoggerInterface;

class AppConfig {

    private const DEFAULT_DRAWIO_URL = 'https://embed.diagrams.net';
    private const DEFAULT_OFFLINE_MODE = 'no';
    /** kennedy, min (=minimal), atlas, simple */
    private const DEFAULT_THEME = 'kennedy';
    private const DEFAULT_LANG = 'auto';
    private const DEFAULT_AUTOSAVE = 'yes';
    private const DEFAULT_LIBRARIES = 'no';
    private const DEFAULT_DARK_MODE = 'auto';
    private const DEFAULT_PREVIEWS = 'yes';
    private const DEFAULT_WHITEBOARDS = 'yes';

    private const KEY_DRAWIO_URL = 'DrawioUrl';
    private const KEY_OFFLINE_MODE = 'DrawioOffline';
    private const KEY_THEME = 'DrawioTheme';
    private const KEY_LANG = 'DrawioLang';
    private const KEY_AUTOSAVE = 'DrawioAutosave';
    private const KEY_LIBRARIES = 'DrawioLibraries';
    private const KEY_DARK_MODE = 'DrawioDarkMode';
    private const KEY_PREVIEWS = 'DrawioPreviews';
    private const KEY_DRAWIO_CONFIG = 'DrawioConfig';
    private const KEY_WHITEBOARDS = 'DrawioWhiteboards';

    /**
     * The default URL was changed from draw.io to embed.diagrams.net (#118)
     */
    private const LEGACY_DRAWIO_URLS = [
        'https://draw.io',
        'https://www.draw.io',
        'http://draw.io',
        'http://www.draw.io',
    ];

    public function __construct(
        private readonly IAppConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function SetDrawioUrl(string $drawio): void
    {
        $drawio = strtolower(trim($drawio));

        if ($drawio !== '' && preg_match('/^https?:\/\//i', $drawio) !== 1) {
            $drawio = 'http://' . $drawio;
        }

        $this->set(self::KEY_DRAWIO_URL, $drawio);
    }

    public function GetDrawioUrl(): string
    {
        $val = $this->config->getAppValueString(self::KEY_DRAWIO_URL);

        if ($val === '' || in_array(strtolower($val), self::LEGACY_DRAWIO_URLS, true)) {
            return self::DEFAULT_DRAWIO_URL;
        }

        return $val;
    }

    public function SetOfflineMode(string $offlinemode): void
    {
        $this->set(self::KEY_OFFLINE_MODE, $offlinemode);
    }

    public function GetOfflineMode(): string
    {
        return $this->get(self::KEY_OFFLINE_MODE, self::DEFAULT_OFFLINE_MODE);
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

    public function SetAutosave(string $autosave): void
    {
        $this->set(self::KEY_AUTOSAVE, $autosave);
    }

    public function GetAutosave(): string
    {
        return $this->get(self::KEY_AUTOSAVE, self::DEFAULT_AUTOSAVE);
    }

    public function SetLibraries(string $libraries): void
    {
        $this->set(self::KEY_LIBRARIES, $libraries);
    }

    public function GetLibraries(): string
    {
        return $this->get(self::KEY_LIBRARIES, self::DEFAULT_LIBRARIES);
    }

    public function SetDarkMode(string $darkmode): void
    {
        $this->set(self::KEY_DARK_MODE, $darkmode);
    }

    public function GetDarkMode(): string
    {
        $val = $this->config->getAppValueString(self::KEY_DARK_MODE);

        if ($val !== '') {
            return $val;
        }

        // The dark theme was replaced by a dedicated dark mode setting
        return $this->GetTheme() === 'dark' ? 'yes' : self::DEFAULT_DARK_MODE;
    }

    public function SetPreviews(string $previews): void
    {
        $this->set(self::KEY_PREVIEWS, $previews);
    }

    public function GetPreviews(): string
    {
        return $this->get(self::KEY_PREVIEWS, self::DEFAULT_PREVIEWS);
    }

    public function SetWhiteboards(string $whiteboards): void
    {
        $this->set(self::KEY_WHITEBOARDS, $whiteboards);
    }

    public function GetWhiteboards(): string
    {
        return $this->get(self::KEY_WHITEBOARDS, self::DEFAULT_WHITEBOARDS);
    }

    public function SetDrawioConfig(string $drawioConfig): void
    {
        // Only store valid JSON
        $this->set(self::KEY_DRAWIO_CONFIG, empty(json_decode($drawioConfig)) ? '' : $drawioConfig);
    }

    public function GetDrawioConfig(): string
    {
        $val = $this->config->getAppValueString(self::KEY_DRAWIO_CONFIG);

        return empty(json_decode($val)) ? '{}' : $val;
    }

    private function get(string $key, string $default): string
    {
        $val = $this->config->getAppValueString($key);

        return $val === '' ? $default : $val;
    }

    private function set(string $key, string $value): void
    {
        $this->logger->info('Setting ' . $key . ': ' . $value, ['app' => Application::APP_ID]);
        $this->config->setAppValueString($key, $value);
    }
}
