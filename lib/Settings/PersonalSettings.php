<?php
namespace OCA\Drawio\Settings;

use OCA\Drawio\Controller\PersonalSettingsController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class PersonalSettings implements IDelegatedSettings
{
    public function __construct(
        private PersonalSettingsController $settingsController
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
