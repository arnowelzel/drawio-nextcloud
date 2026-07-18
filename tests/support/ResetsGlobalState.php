<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Support;

use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\L10N\IFactory;
use OCP\Util;

/**
 * Resets the static script/style registries in \OCP\Util and \OC_Util and
 * installs a FakeServerContainer with the services those statics resolve,
 * so controller/listener code using Util::addScript etc. can run in unit tests.
 */
trait ResetsGlobalState {

    protected function resetGlobalState(): void {
        foreach (['scriptsInit' => [], 'scripts' => [], 'scriptDeps' => []] as $property => $default) {
            $reflection = new \ReflectionProperty(Util::class, $property);
            $reflection->setValue(null, $default);
        }
        \OC_Util::$styles = [];

        $container = new FakeServerContainer();
        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnCallback(static fn (string $text, $params = []) => $text);
        $l10nFactory = $this->createMock(IFactory::class);
        $l10nFactory->method('findLanguage')->willReturn('en');
        $l10nFactory->method('get')->willReturn($l10n);
        $container->services[IFactory::class] = $l10nFactory;
        $container->services[IInitialStateService::class] = $this->createMock(IInitialStateService::class);
        \OC::$server = $container;
    }

    /** @return list<string> */
    protected static function scriptsInit(): array {
        $reflection = new \ReflectionProperty(Util::class, 'scriptsInit');
        return $reflection->getValue();
    }

    /** @return array<string, list<string>> */
    protected static function scripts(): array {
        $reflection = new \ReflectionProperty(Util::class, 'scripts');
        return $reflection->getValue();
    }
}
