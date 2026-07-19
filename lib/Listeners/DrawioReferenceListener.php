<?php

declare(strict_types=1);

namespace OCA\Drawio\Listeners;

use OCA\Drawio\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Loads the reference widget script when rich text references are rendered.
 *
 * @template-implements IEventListener<RenderReferenceEvent>
 */
class DrawioReferenceListener implements IEventListener {

    public function handle(Event $event): void {
        if (!($event instanceof RenderReferenceEvent)) {
            return;
        }

        Util::addScript(Application::APP_ID, 'drawio-reference');
    }
}
