<?php

namespace OCA\Drawio;

use OCP\IConfig;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PersonalConfig {
    private $predefTheme = "default";
    private $predefLang = "auto";
    private $predefDarkMode = "auto";

    private $appName;

    private $config;

    private $logger;

    private $UID;

    // The config keys
    private $_theme = "DrawioTheme";
    private $_lang = "DrawioLang";
    private $_darkmode = "DrawioDarkMode";

    public function __construct($AppName, IConfig $config, IUserSession $userSession, LoggerInterface $logger)
    {
        $this->appName = $AppName;
        $this->config = $config;
        $this->logger = $logger;

        $this->UID = null;
        if ($userSession->getUser()) {
            $this->UID = $userSession->getUser()->getUID();
        }
    }

    public function SetTheme($theme)
    {
        $this->logger->info("SetTheme: " . $theme, array("app" => $this->appName));
        $this->config->setUserValue($this->UID, $this->appName, $this->_theme, $theme);
    }

    public function GetTheme()
    {
        $val = $this->config->getUserValue($this->UID, $this->appName, $this->_theme);
        if (empty($val)) $val = $this->predefTheme;
        return $val;
    }

    public function SetLang($lang)
    {
        $this->logger->info("SetLang: " . $lang, array("app" => $this->appName));
        $this->config->setUserValue($this->UID, $this->appName, $this->_lang, $lang);
    }

    public function GetLang()
    {
        $val = $this->config->getUserValue($this->UID, $this->appName, $this->_lang);
        if (empty($val)) $val = $this->predefLang;
        return $val;
    }

    public function SetDarkMode($darkmode)
    {
        $this->logger->info("SetDarkMode: " . $darkmode, array("app" => $this->appName));
        $this->config->setUserValue($this->UID, $this->appName, $this->_darkmode, $darkmode);
    }
    
    public function GetDarkMode()
    {
        $val = $this->config->getUserValue($this->UID, $this->appName, $this->_darkmode);
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
