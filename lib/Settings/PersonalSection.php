<?php
namespace OCA\Drawio\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection
{
	public function __construct(
        private IURLGenerator $url,
        private IL10N $l10n
    )
    {
	}

	public function getID(): string
    {
		return 'drawio';
	}

	public function getName(): string
    {
		return $this->l10n->t('Diagramming');
	}

	public function getPriority(): int
    {
		return 75;
	}

	public function getIcon(): string
    {
		return $this->url->imagePath('drawio', 'app.svg');
	}
}
