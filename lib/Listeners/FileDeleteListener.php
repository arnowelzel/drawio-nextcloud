<?php

namespace OCA\Drawio\Listeners;

use OCA\Drawio\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Removes the cached preview image when a file is deleted.
 *
 * @template-implements IEventListener<NodeDeletedEvent>
 */
class FileDeleteListener implements IEventListener {

    /** @var LoggerInterface */
    private $logger;

    /** @var IAppData */
    private $appData;

	public function __construct(LoggerInterface $logger, IAppData $appData)
    {
        $this->logger = $logger;
        $this->appData = $appData;
    }

	public function handle(Event $event): void {
		if (!($event instanceof NodeDeletedEvent)) {
			return;
		}

		$node = $event->getNode();

		if ($node instanceof Folder) {
			return;
		}

        try
        {
            $this->appData->getFolder('previews')->getFile($node->getId() . '.png')->delete();
        }
        catch (NotFoundException $e)
        {
            // ignore
            return;
        }
        catch (\Exception $e)
        {
            // ignore
            $this->logger->error($e->getMessage(), ["message" => "Can't delete preview for file: " . $node->getPath(), "app" => Application::APP_ID, 'exception' => $e]);
        }
	}
}
