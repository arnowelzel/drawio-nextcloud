<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Listeners;

use OCA\Drawio\Listeners\FilesScriptsListener;
use OCA\Drawio\Tests\Support\ResetsGlobalState;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;

final class FilesScriptsListenerTest extends TestCase {
    use ResetsGlobalState;

    protected function setUp(): void {
        $this->resetGlobalState();
    }

    public function testAddsInitScriptInFilesApp(): void {
        (new FilesScriptsListener())->handle(new LoadAdditionalScriptsEvent());

        $this->assertContains('drawio/js/main', self::scriptsInit());
    }

    public function testAddsInitScriptOnPublicSharePage(): void {
        $event = new BeforeTemplateRenderedEvent($this->createMock(IShare::class), null);

        (new FilesScriptsListener())->handle($event);

        $this->assertContains('drawio/js/main', self::scriptsInit());
    }

    public function testSkipsShareAuthenticationPage(): void {
        $event = new BeforeTemplateRenderedEvent(
            $this->createMock(IShare::class),
            BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH
        );

        (new FilesScriptsListener())->handle($event);

        $this->assertSame([], self::scriptsInit());
    }

    public function testIgnoresUnrelatedEvents(): void {
        (new FilesScriptsListener())->handle(new class extends Event {
        });

        $this->assertSame([], self::scriptsInit());
    }
}
