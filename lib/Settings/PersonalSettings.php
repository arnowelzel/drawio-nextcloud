<?php

declare(strict_types=1);

namespace OCA\Drawio\Settings;

use OCA\Drawio\Controller\PersonalSettingsController;
use OCP\Settings\IDelegatedSettings;

class PersonalSettings implements IDelegatedSettings {
    private PersonalSettingsController $settingsController;

    public function __construct(PersonalSettingsController $settingsController) {
        $this->settingsController = $settingsController;
    }

    public function getName(): ?string {
        return null;
    }

    public function getAuthorizedAppConfig(): array {
        return [
            'drawio' => ['/drawio.*/'],
        ];
    }

    #[\Override]
    public function getForm() {
        return $this->settingsController->index();
    }

    #[\Override]
    public function getSection(): string {
        return 'drawio';
    }

    #[\Override]
    public function getPriority(): int {
        return 90;
    }
}
