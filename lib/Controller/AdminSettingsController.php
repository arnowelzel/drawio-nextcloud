<?php

declare(strict_types=1);

/**
 *
 * @author Pawel Rojek <pawel at pawelrojek.com>
 * @author Ian Reinhart Geiser <igeiser at devonit.com>
 * @author Arno Welzel <privat at arnowelzel.de>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 *
 **/

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
    public function __construct(
        IRequest $request,
        private readonly AppConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Config page
     */
    public function index(): TemplateResponse {
        $data = [
            'drawioUrl' => $this->config->GetDrawioUrl(),
            'drawioOfflineMode' => $this->config->GetOfflineMode(),
            'drawioTheme' => $this->config->GetTheme(),
            'drawioLang' => $this->config->GetLang(),
            'drawioAutosave' => $this->config->GetAutosave(),
            'drawioLibraries' => $this->config->GetLibraries(),
            'drawioDarkMode' => $this->config->GetDarkMode(),
            'drawioPreviews' => $this->config->GetPreviews(),
            'drawioConfig' => $this->config->GetDrawioConfig(),
            'drawioWhiteboards' => $this->config->GetWhiteboards(),
        ];

        Util::addScript(Application::APP_ID, 'adminSettings');
        Util::addStyle(Application::APP_ID, 'settings');

        return new TemplateResponse($this->appName, 'adminSettings', $data, TemplateResponse::RENDER_AS_BLANK);
    }

    /**
     * Save settings
     *
     * @return array<string, string>
     */
    #[AuthorizedAdminSetting(settings: AdminSettings::class)]
    public function settings(): array
    {
        $this->config->SetDrawioUrl($this->param('drawioUrl'));
        $this->config->SetOfflineMode($this->param('offlineMode'));
        $this->config->SetTheme($this->param('theme'));
        $this->config->SetLang($this->param('lang'));
        $this->config->SetAutosave($this->param('autosave'));
        $this->config->SetLibraries($this->param('libraries'));
        $this->config->SetDarkMode($this->param('darkMode'));
        $this->config->SetPreviews($this->param('previews'));
        $this->config->SetDrawioConfig($this->param('drawioConfig'));
        $this->config->SetWhiteboards($this->param('whiteboards'));

        return [
            'drawioUrl' => $this->config->GetDrawioUrl(),
            'offlineMode' => $this->config->GetOfflineMode(),
            'theme' => $this->config->GetTheme(),
            'lang' => $this->config->GetLang(),
            'drawioAutosave' => $this->config->GetAutosave(),
            'drawioLibraries' => $this->config->GetLibraries(),
            'drawioDarkMode' => $this->config->GetDarkMode(),
            'drawioPreviews' => $this->config->GetPreviews(),
            'drawioConfig' => $this->config->GetDrawioConfig(),
            'drawioWhiteboards' => $this->config->GetWhiteboards(),
        ];
    }

    private function param(string $name): string
    {
        return trim((string)$this->request->getParam($name, ''));
    }
}
