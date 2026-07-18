<?php

declare(strict_types=1);

namespace OCA\Drawio\Listeners;

use OCA\Drawio\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Loads the file actions script in the Files app and on public share pages.
 *
 * @template-implements IEventListener<LoadAdditionalScriptsEvent|BeforeTemplateRenderedEvent>
 */
class FilesScriptsListener implements IEventListener {

    public function handle(Event $event): void {
        if ($event instanceof BeforeTemplateRenderedEvent) {
            // Skip the share authentication (password) page
            if ($event->getScope() === BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH) {
                return;
            }
        } elseif (!($event instanceof LoadAdditionalScriptsEvent)) {
            return;
        }

        Util::addInitScript(Application::APP_ID, 'main');
    }
}
