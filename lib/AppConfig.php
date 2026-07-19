<?php
namespace OCA\Drawio;

use OCA\Drawio\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use Psr\Log\LoggerInterface;

class AppConfig {

    private $predefDrawioUrl = "https://embed.diagrams.net";
    private $predefOfflineMode = "no";
    private $predefTheme = "kennedy"; //kennedy, min (=minimal), atlas, simple
    private $predefLang = "auto";
    private $predefAutosave = "yes";
    private $predefLibraries = "no";
    private $predefDarkMode = "auto";
    private $predefPreviews = "yes";
    private $predefWhiteboards = "yes";

    // The config keys
    private $_drawioUrl = "DrawioUrl";
    private $_offlinemode = "DrawioOffline";
    private $_theme = "DrawioTheme";
    private $_lang = "DrawioLang";
    private $_autosave = "DrawioAutosave";
    private $_libraries = "DrawioLibraries";
    private $_darkmode = "DrawioDarkMode";
    private $_previews = "DrawioPreviews";
    private $_drawioConfig = "DrawioConfig";
    private $_whiteboards = "DrawioWhiteboards";

    public function __construct(
        private IAppConfig $config,
        private LoggerInterface $logger,
    )
    {
    }

    public function SetDrawioUrl($drawio): void
    {
        $drawio = strtolower(trim($drawio));
        if (strlen($drawio) > 0 && !preg_match("/^https?:\/\//i", $drawio)) {
            $drawio = "http://" . $drawio;
        }
        $this->logger->info("SetDrawioUrl: " . $drawio, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_drawioUrl, $drawio);
    }

    public function GetDrawioUrl(): string
    {
        $val = $this->config->getAppValueString($this->_drawioUrl);
        if (empty($val)) {
            $val = $this->predefDrawioUrl;
        }
        // default URL changed from draw.io to embed.diagrams.net #118
        if (in_array(strtolower($val), ["https://draw.io", "https://www.draw.io", "http://draw.io", "http://www.draw.io"])) {
            $val = $this->predefDrawioUrl;
        }

        return $val;
    }


    public function SetOfflineMode($offlinemode): void
    {
        $offlinemode = (string)$offlinemode;
        $this->logger->info("SetOfflineMode: " . $offlinemode, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_offlinemode, $offlinemode);
    }

    public function GetOfflineMode(): string
    {
        $val = $this->config->getAppValueString($this->_offlinemode);
        if (empty($val)) {
            $val = $this->predefOfflineMode;
        }

        return $val;
    }

    public function SetTheme($theme): void
    {
        $this->logger->info("SetTheme: " . $theme, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_theme, $theme);
    }

    public function GetTheme(): string
    {
        $val = $this->config->getAppValueString($this->_theme);
        if (empty($val)) {
            $val = $this->predefTheme;
        }

        return $val;
    }

    public function SetLang($lang): void
    {
        $this->logger->info("SetLang: " . $lang, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_lang, $lang);
    }

    public function GetLang(): string
    {
        $val = $this->config->getAppValueString($this->_lang);
        if (empty($val)) {
            $val = $this->predefLang;
        }

        return $val;
    }

    public function SetAutosave($autosave): void
    {
        $this->logger->info("SetAutosave: " . $autosave, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_autosave, $autosave);
    }

    public function GetAutosave(): string
    {
        $val = $this->config->getAppValueString($this->_autosave);
        if (empty($val)) {
            $val = $this->predefAutosave;
        }

        return $val;
    }

    public function SetLibraries($libraries): void
    {
        $this->logger->info("SetLibraries: " . $libraries, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_libraries, $libraries);
    }

    public function GetLibraries(): string
    {
        $val = $this->config->getAppValueString($this->_libraries);
        if (empty($val)) {
            $val = $this->predefLibraries;
        }

        return $val;
    }

    public function SetDarkMode($darkmode): void
    {
        $this->logger->info("SetDarkMode: " . $darkmode, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_darkmode, $darkmode);
    }

    public function GetDarkMode(): string
    {
        $val = $this->config->getAppValueString($this->_darkmode);
        if (empty($val)) {
            if ($this->GetTheme() == "dark") {
                $val = "yes";
            } else {
                $val = $this->predefDarkMode;
            }
        }

        return $val;
    }

    public function SetPreviews($previews): void
    {
        $this->logger->info("SetPreviews: " . $previews, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_previews, $previews);
    }

    public function GetPreviews(): string
    {
        $val = $this->config->getAppValueString($this->_previews);
        if (empty($val)) {
            $val = $this->predefPreviews;
        }

        return $val;
    }

    public function SetWhiteboards($whiteboards): void
    {
        $this->logger->info("SetWhiteboards: " . $whiteboards, ["app" => Application::APP_ID]);
        $this->config->setAppValueString($this->_whiteboards, $whiteboards);
    }

    public function GetWhiteboards(): string
    {
        $val = $this->config->getAppValueString($this->_whiteboards);
        if (empty($val)) {
            $val = $this->predefWhiteboards;
        }

        return $val;
    }

    public function SetDrawioConfig($drawioConfig): void
    {
        $this->logger->info("SetDrawioConfig: " . $drawioConfig, ["app" => Application::APP_ID]);
        // Check if the json is valid
        $val = json_decode($drawioConfig);
        $this->config->setAppValueString($this->_drawioConfig, empty($val)? "" : $drawioConfig);
    }

    public function GetDrawioConfig(): string
    {
        $val = $this->config->getAppValueString($this->_drawioConfig);

        if (empty(json_decode($val))) {
            return "{}";
        } else {
            return $val;
        }
    }
}
