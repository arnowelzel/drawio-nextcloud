<?php

declare(strict_types=1);

namespace OCA\Drawio\Settings;

use OCA\Drawio\AppInfo\Application;
use OCA\Drawio\Controller\PersonalSettingsController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class PersonalSettings implements IDelegatedSettings {

    public function __construct(
        private readonly PersonalSettingsController $settingsController,
    ) {
    }

    public function getName(): ?string {
        return null;
    }

    public function getAuthorizedAppConfig(): array {
        return [
            Application::APP_ID => ['/drawio.*/'],
        ];
    }

    public function getForm(): TemplateResponse {
        return $this->settingsController->index();
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    public function getPriority(): int {
        return 60;
    }
}
