<?php
namespace OCA\Drawio\Controller;

use OCA\Drawio\AppInfo\Application;
use OCA\Drawio\Settings\PersonalSettings;
use OCA\Drawio\PersonalConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PersonalSettingsController extends Controller
{
    public function __construct(
        IRequest $request,
        private PersonalConfig $config,
    )
    {
        parent::__construct(Application::APP_ID, $request);
    }

    public function index(): TemplateResponse
    {
        $data = [
            "drawioTheme" => $this->config->GetTheme(),
            "drawioLang" => $this->config->GetLang(),
            "drawioDarkMode" => $this->config->GetDarkMode(),
        ];

        Util::addScript(Application::APP_ID, "personalSettings");
        Util::addStyle(Application::APP_ID, "settings");

        return new TemplateResponse($this->appName, "personalSettings", $data, TemplateResponse::RENDER_AS_BLANK);
    }

    #[NoAdminRequired]
    public function settings(): array
    {
        $theme = trim($this->request->getParam('theme', ''));
        $lang = trim($this->request->getParam('lang', ''));
        $darkmode = trim($this->request->getParam('darkMode', ''));

        $this->config->SetTheme($theme);
        $this->config->SetLang($lang);
        $this->config->SetDarkMode($darkmode);

        return [
            "theme" => $this->config->GetTheme(),
            "lang" => $this->config->GetLang(),
            "drawioDarkMode" =>$this->config->GetDarkMode(),
        ];
    }
}
