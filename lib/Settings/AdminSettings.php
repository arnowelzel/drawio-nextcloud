<?php

declare(strict_types=1);

/**
 *
 * @author Pawel Rojek <pawel at pawelrojek.com>
 * @author Ian Reinhart Geiser <igeiser at devonit.com>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 *
 **/

namespace OCA\Drawio\Settings;

use OCA\Drawio\AppInfo\Application;
use OCA\Drawio\Controller\AdminSettingsController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class AdminSettings implements IDelegatedSettings {

    public function __construct(
        private readonly AdminSettingsController $settingsController,
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
