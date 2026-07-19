<?php

declare(strict_types=1);

namespace OCA\Drawio\Settings;

use OCA\Drawio\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {

	public function __construct(
		private readonly IURLGenerator $url,
		private readonly IL10N $l10n,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10n->t('Diagramming');
	}

	public function getPriority(): int {
		return 75;
	}

	public function getIcon(): string {
		return $this->url->imagePath(Application::APP_ID, 'app.svg');
	}
}
