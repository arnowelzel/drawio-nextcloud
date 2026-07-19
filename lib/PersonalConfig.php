<?php

namespace OCA\Drawio;

use OCA\Drawio\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;

class PersonalConfig {
    private $predefTheme = "default";
    private $predefLang = "auto";
    private $predefDarkMode = "auto";

    private $UID;

    // The config keys
    private $_theme = "DrawioTheme";
    private $_lang = "DrawioLang";
    private $_darkmode = "DrawioDarkMode";

    public function __construct(
        private IConfig $config,
        private IUserConfig $userConfig,
        private LoggerInterface $logger,
        IUserSession $userSession
    )
    {
        $this->UID = null;
        if ($userSession->getUser()) {
            $this->UID = $userSession->getUser()->getUID();
        }
    }

    public function SetTheme($theme): void
    {
        $this->logger->info("SetTheme: " . $theme, array("app" => Application::APP_ID));
        $this->userConfig->setValueString($this->UID, Application::APP_ID, $this->_theme, $theme);
    }

    public function GetTheme(): string
    {
        $val = $this->userConfig->getValueString($this->UID, Application::APP_ID, $this->_theme);
        if (empty($val)) $val = $this->predefTheme;
        return $val;
    }

    public function SetLang($lang): void
    {
        $this->logger->info("SetLang: " . $lang, array("app" => Application::APP_ID));
        $this->userConfig->setValueString($this->UID, Application::APP_ID, $this->_lang, $lang);
    }

    public function GetLang(): string
    {
        $val = $this->userConfig->getValueString($this->UID, Application::APP_ID, $this->_lang);
        if (empty($val)) $val = $this->predefLang;
        return $val;
    }

    public function SetDarkMode($darkmode): void
    {
        $this->logger->info("SetDarkMode: " . $darkmode, array("app" => Application::APP_ID));
        $this->userConfig->setValueString($this->UID, Application::APP_ID, $this->_darkmode, $darkmode);
    }
    
    public function GetDarkMode(): string
    {
        $val = $this->userConfig->getValueString($this->UID, Application::APP_ID, $this->_darkmode);
        if (empty($val))
        {
            if ($this->GetTheme() == "dark")
            {
                $val = "yes";
            }
            else
            {
                $val = $this->predefDarkMode;
            }
        }
        return $val;
    }
}
