<?php
namespace OCA\Drawio\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;
use OCA\Drawio\Controller\AdminSettingsController;

class AdminSettings implements IDelegatedSettings
{
    public function __construct(
        private AdminSettingsController $settingsController
    )
    {
    }

    public function getName(): ?string
    {
        return null;
    }

    public function getAuthorizedAppConfig(): array
    {
        return [
            'drawio' => ['/drawio.*/'],
        ];
    }

    public function getForm(): TemplateResponse
    {
        return $this->settingsController->index();
    }

    public function getSection(): string
    {
        return "drawio";
    }

    public function getPriority(): int
    {
        return 60;
    }
}
