<?php

namespace OCA\Drawio\Controller;

use OCA\Drawio\AppConfig;
use OCA\Drawio\AppInfo\Application;
use OCA\Drawio\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class AdminSettingsController extends Controller
{
    private $config;

    /**
     * @param IRequest $request - request object
     * @param AppConfig $config - application configuration
     */
    public function __construct(IRequest $request,
                                AppConfig $config
                                )
    {
        parent::__construct(Application::APP_ID, $request);

        $this->config = $config;
    }

    /**
     * Config page
     *
     * @return TemplateResponse
     */
    public function index() {
        $data = [
            "drawioUrl" => $this->config->GetDrawioUrl(),
            "drawioOfflineMode" => $this->config->GetOfflineMode(),
            "drawioTheme" => $this->config->GetTheme(),
            "drawioLang" => $this->config->GetLang(),
            "drawioAutosave" => $this->config->GetAutosave(),
            "drawioLibraries" => $this->config->GetLibraries(),
            "drawioDarkMode" => $this->config->GetDarkMode(),
            "drawioPreviews" => $this->config->GetPreviews(),
            "drawioConfig" => $this->config->GetDrawioConfig(),
            "drawioWhiteboards" => $this->config->GetWhiteboards(),
        ];

        Util::addScript(Application::APP_ID, "adminSettings");
        Util::addStyle(Application::APP_ID, "settings");

        return new TemplateResponse($this->appName, "adminSettings", $data, TemplateResponse::RENDER_AS_BLANK);
    }

	/**
	 * Save settings
	 */
    #[AuthorizedAdminSetting(settings: AdminSettings::class)]
    public function settings()
    {
        $drawio = trim($this->request->getParam('drawioUrl', ''));
        $offlinemode = trim($this->request->getParam('offlineMode', ''));
        $theme = trim($this->request->getParam('theme', ''));
        $lang = trim($this->request->getParam('lang', ''));
        $autosave = trim($this->request->getParam('autosave', ''));
        $libraries = trim($this->request->getParam('libraries', ''));
        $darkmode = trim($this->request->getParam('darkMode', ''));
        $previews = trim($this->request->getParam('previews', ''));
        $drawioConfig = trim($this->request->getParam('drawioConfig', ''));
        $whiteboards = trim($this->request->getParam('whiteboards', ''));

        $this->config->SetDrawioUrl($drawio);
        $this->config->SetOfflineMode($offlinemode);
        $this->config->SetTheme($theme);
        $this->config->SetLang($lang);
        $this->config->SetAutosave($autosave);
        $this->config->SetLibraries($libraries);
        $this->config->SetDarkMode($darkmode);
        $this->config->SetPreviews($previews);
        $this->config->SetDrawioConfig($drawioConfig);
        $this->config->SetWhiteboards($whiteboards);

        return [
            "drawioUrl" => $this->config->GetDrawioUrl(),
            "offlineMode" => $this->config->GetOfflineMode(),
            "theme" => $this->config->GetTheme(),
            "lang" => $this->config->GetLang(),
            "drawioAutosave" =>$this->config->GetAutosave(),
            "drawioLibraries" =>$this->config->GetLibraries(),
            "drawioDarkMode" =>$this->config->GetDarkMode(),
            "drawioPreviews" =>$this->config->GetPreviews(),
            "drawioConfig" =>$this->config->GetDrawioConfig(),
            "drawioWhiteboards" =>$this->config->GetWhiteboards(),
        ];
    }
}
