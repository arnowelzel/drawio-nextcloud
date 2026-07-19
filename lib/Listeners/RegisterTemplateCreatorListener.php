<?php

declare(strict_types=1);

namespace OCA\Drawio\Listeners;

use OCA\Drawio\AppConfig;
use OCA\Drawio\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Template\RegisterTemplateCreatorEvent;
use OCP\Files\Template\TemplateFileCreator;
use OCP\IL10N;

/**
 * Registers the diagram and whiteboard entries in the "+" new file menu.
 *
 * @template-implements IEventListener<RegisterTemplateCreatorEvent>
 */
class RegisterTemplateCreatorListener implements IEventListener {

    public function __construct(
        private readonly IL10N $l10n,
        private readonly AppConfig $config,
    ) {
    }

    public function handle(Event $event): void {
        if (!($event instanceof RegisterTemplateCreatorEvent)) {
            return;
        }

        $event->getTemplateManager()->registerTemplateFileCreator(
            fn (): TemplateFileCreator => $this->createTemplateFileCreator(
                $this->l10n->t('New Diagram'),
                '.drawio',
                'application/x-drawio',
                'filetype-drawio.svg'
            )
        );

        // The admin setting "Enable whiteboards?" hides the whiteboard
        // entry from the file creation menu.
        if ($this->config->GetWhiteboards() !== 'yes') {
            return;
        }

        $event->getTemplateManager()->registerTemplateFileCreator(
            fn (): TemplateFileCreator => $this->createTemplateFileCreator(
                $this->l10n->t('New Whiteboard'),
                '.dwb',
                'application/x-drawio-wb',
                'filetype-dwb.svg'
            )
        );
    }

    private function createTemplateFileCreator(
        string $label,
        string $extension,
        string $mimetype,
        string $icon,
    ): TemplateFileCreator {
        $creator = new TemplateFileCreator(Application::APP_ID, $label, $extension);
        $creator->addMimetype($mimetype);
        $creator->setIconSvgInline((string)file_get_contents(__DIR__ . '/../../img/' . $icon));
        $creator->setActionLabel($label);

        return $creator;
    }
}
