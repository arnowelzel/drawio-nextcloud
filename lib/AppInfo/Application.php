<?php
namespace OCA\Drawio\AppInfo;

use OCA\Drawio\Listeners\DrawioReferenceListener;
use OCA\Drawio\Listeners\FileDeleteListener;
use OCA\Drawio\Listeners\FilesScriptsListener;
use OCA\Drawio\Listeners\RegisterTemplateCreatorListener;
use OCA\Drawio\Preview\DrawioPreview;
use OCA\Drawio\Reference\DrawioReferenceProvider;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Template\RegisterTemplateCreatorEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'drawio';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerEventListener(NodeDeletedEvent::class, FileDeleteListener::class);
        $context->registerEventListener(RenderReferenceEvent::class, DrawioReferenceListener::class);
        $context->registerEventListener(RegisterTemplateCreatorEvent::class, RegisterTemplateCreatorListener::class);
        $context->registerEventListener(LoadAdditionalScriptsEvent::class, FilesScriptsListener::class);
        $context->registerEventListener(BeforeTemplateRenderedEvent::class, FilesScriptsListener::class);

        $context->registerReferenceProvider(DrawioReferenceProvider::class);

        $context->registerPreviewProvider(
            DrawioPreview::class,
            DrawioPreview::getMimeTypeRegex()
        );
    }

    public function boot(IBootContext $context): void
    {
        $context->injectFn(function (IMimeTypeDetector $detector): void {
            // There is no OCP API to register MIME types at runtime yet
            // (see https://github.com/nextcloud/server/issues/9192), so this
            // relies on the OC\Files\Type\Detection implementation. It backs up
            // the config/mimetypemapping.json entries written by the repair step
            // for setups where the config directory is not writable.
            // getAllMappings() must be called first: it forces the detector to
            // load the default mappings, which would otherwise be skipped later
            // because registerType() marks the mapping table as initialized.
            if (method_exists($detector, 'registerType')) {
                $detector->getAllMappings();
                $detector->registerType("drawio", "application/x-drawio");
                $detector->registerType("dwb", "application/x-drawio-wb");
            }
        });
    }
}
