<?php

declare(strict_types=1);

namespace OCA\Drawio\Controller;

use OCA\Drawio\AppInfo\Application;
use OCA\Drawio\PersonalConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PersonalSettingsController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly PersonalConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Config page
     */
    public function index(): TemplateResponse {
        $data = [
            'drawioTheme' => $this->config->GetTheme(),
            'drawioLang' => $this->config->GetLang(),
            'drawioDarkMode' => $this->config->GetDarkMode(),
        ];

        Util::addScript(Application::APP_ID, 'personalSettings');
        Util::addStyle(Application::APP_ID, 'settings');

        return new TemplateResponse($this->appName, 'personalSettings', $data, TemplateResponse::RENDER_AS_BLANK);
    }

    /**
     * Save settings
     *
     * These are per-user preferences, so any logged-in user may change their
     * own — this is not an admin setting.
     *
     * @return array<string, string>
     */
    #[NoAdminRequired]
    public function settings(): array
    {
        $this->config->SetTheme($this->param('theme'));
        $this->config->SetLang($this->param('lang'));
        $this->config->SetDarkMode($this->param('darkMode'));

        return [
            'theme' => $this->config->GetTheme(),
            'lang' => $this->config->GetLang(),
            'drawioDarkMode' => $this->config->GetDarkMode(),
        ];
    }

    private function param(string $name): string
    {
        return trim((string)$this->request->getParam($name, ''));
    }
}
