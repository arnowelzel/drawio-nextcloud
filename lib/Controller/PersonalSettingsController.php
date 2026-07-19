<?php
namespace OCA\Drawio\Controller;

use OCA\Drawio\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\IRequest;
use OCA\Drawio\PersonalConfig;

use OCP\Util;
use Psr\Log\LoggerInterface;

class PersonalSettingsController extends Controller
{
    private $config;

    /**
     * @param IRequest $request - request object
     * @param PersonalConfig $config - personal configuration
     */
    public function __construct(IRequest $request,
                                PersonalConfig $config,
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
            "drawioTheme" => $this->config->GetTheme(),
            "drawioLang" => $this->config->GetLang(),
            "drawioDarkMode" => $this->config->GetDarkMode(),
        ];

        Util::addScript(Application::APP_ID, "personalSettings");
        Util::addStyle(Application::APP_ID, "settings");

        return new TemplateResponse($this->appName, "personalSettings", $data, TemplateResponse::RENDER_AS_BLANK);
    }

    /**
     * Save settings
     *
     * @AuthorizedAdminSetting(settings=OCA\Drawio\Settings\Admin)
     */
    public function settings()
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
