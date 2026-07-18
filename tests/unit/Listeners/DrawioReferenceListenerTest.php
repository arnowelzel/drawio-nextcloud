<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Listeners;

use OCA\Drawio\Listeners\DrawioReferenceListener;
use OCA\Drawio\Tests\Support\ResetsGlobalState;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

final class DrawioReferenceListenerTest extends TestCase {
    use ResetsGlobalState;

    protected function setUp(): void {
        $this->resetGlobalState();
    }

    public function testAddsReferenceWidgetScript(): void {
        (new DrawioReferenceListener())->handle(new RenderReferenceEvent());

        $this->assertContains('drawio/js/drawio-reference', self::scripts()['drawio'] ?? []);
    }

    public function testIgnoresUnrelatedEvents(): void {
        (new DrawioReferenceListener())->handle(new class extends Event {
        });

        $this->assertSame([], self::scripts());
    }
}
