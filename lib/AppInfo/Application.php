<?php

/**
 *
 * @author Pawel Rojek <pawel at pawelrojek.com>
 * @author Ian Reinhart Geiser <igeiser at devonit.com>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 *
 **/

namespace OCA\Drawio\AppInfo;

use OCA\Drawio\AppConfig;
use OCA\Drawio\PersonalConfig;
use OCA\Drawio\Preview\DrawioPreview;
use OCA\Drawio\Listeners\FileDeleteListener;
use OCA\Drawio\Listeners\DrawioReferenceListener;
use OCA\Drawio\Listeners\RegisterTemplateCreatorListener;
use OCA\Drawio\Reference\DrawioReferenceProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Util;
use OCP\Files\IMimeTypeDetector;
use OCP\AppFramework\Services\IInitialState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Template\RegisterTemplateCreatorEvent;
use Psr\Log\LoggerInterface;

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

        $context->registerReferenceProvider(DrawioReferenceProvider::class);

        $context->registerPreviewProvider(
            DrawioPreview::class,
            DrawioPreview::getMimeTypeRegex()
        );

        $context->registerService(AppConfig::class, function ($c) {
            return new AppConfig(
                self::APP_ID,
                $c->get(IConfig::class),
                $c->get(LoggerInterface::class)
            );
        });

        $context->registerService(PersonalConfig::class, function ($c) {
            return new PersonalConfig(
                self::APP_ID,
                $c->get(IConfig::class),
                $c->get(IUserSession::class),
                $c->get(LoggerInterface::class)
            );
        });
    }

    public function boot(IBootContext $context): void
    {
        Util::addInitScript(self::APP_ID, 'main');
        Util::addStyle(self::APP_ID, 'main');

        $container = $context->getAppContainer();

        $initialState = $container->get(IInitialState::class);
        $appConfig = $container->get(AppConfig::class);
        $initialState->provideInitialState('whiteboards', $appConfig->GetWhiteboards());
        $detector = $container->get(IMimeTypeDetector::class);
        $detector->getAllMappings();
        $detector->registerType('drawio', 'application/x-drawio');
        $detector->registerType('dwb', 'application/x-drawio-wb');

        // $this->ensureMimeTypeAssets($container, $appConfig, $detector);
    }
}
