<?php
namespace OCA\Drawio\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\IRequest;
use OCA\Drawio\PersonalConfig;

use Psr\Log\LoggerInterface;

class PersonalSettingsController extends Controller
{

    private $trans;
    private $logger;
    private $config;


    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param PersonalConfig $config - application configuration
     * @param IMimeTypeLoader $mimeTypeLoader - MIME type loader
     * @param IMimeTypeDetector $mimeTypeDetector - MIME type detector
     */
    public function __construct($AppName,
                                IRequest $request,
                                IL10N $trans,
                                LoggerInterface $logger,
                                PersonalConfig $config,
                                IMimeTypeLoader $mimeTypeLoader,
                                IMimeTypeDetector $mimeTypeDetector
    )
    {
        parent::__construct($AppName, $request);

        $this->trans = $trans;
        $this->logger = $logger;
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
        return new TemplateResponse($this->appName, "settings-personal", $data, "blank");
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
