<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Listeners;

use OCA\Drawio\AppConfig;
use OCA\Drawio\Listeners\RegisterTemplateCreatorListener;
use OCP\EventDispatcher\Event;
use OCP\Files\Template\ITemplateManager;
use OCP\Files\Template\RegisterTemplateCreatorEvent;
use OCP\Files\Template\TemplateFileCreator;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

final class RegisterTemplateCreatorListenerTest extends TestCase {

    private function createListener(string $whiteboards = 'yes'): RegisterTemplateCreatorListener {
        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnCallback(static fn (string $text, $params = []) => $text);

        $config = $this->createMock(AppConfig::class);
        $config->method('GetWhiteboards')->willReturn($whiteboards);

        return new RegisterTemplateCreatorListener($l10n, $config);
    }

    /**
     * @return list<TemplateFileCreator>
     */
    private function dispatchAndCollectCreators(RegisterTemplateCreatorListener $listener): array {
        $callbacks = [];
        $templateManager = $this->createMock(ITemplateManager::class);
        $templateManager->method('registerTemplateFileCreator')
            ->willReturnCallback(function (callable $callback) use (&$callbacks): void {
                $callbacks[] = $callback;
            });

        $listener->handle(new RegisterTemplateCreatorEvent($templateManager));

        return array_map(static fn (callable $callback) => $callback(), $callbacks);
    }

    public function testRegistersDiagramAndWhiteboardCreators(): void {
        $creators = $this->dispatchAndCollectCreators($this->createListener('yes'));

        $this->assertCount(2, $creators);

        $diagramData = $creators[0]->jsonSerialize();
        $this->assertSame('drawio', $diagramData['app']);
        $this->assertSame('.drawio', $diagramData['extension']);
        $this->assertSame(['application/x-drawio'], $diagramData['mimetypes']);
        $this->assertSame('New Diagram', $diagramData['actionLabel']);
        $this->assertStringContainsString('<svg', $diagramData['iconSvgInline']);

        $whiteboardData = $creators[1]->jsonSerialize();
        $this->assertSame('.dwb', $whiteboardData['extension']);
        $this->assertSame(['application/x-drawio-wb'], $whiteboardData['mimetypes']);
        $this->assertSame('New Whiteboard', $whiteboardData['actionLabel']);
        $this->assertStringContainsString('<svg', $whiteboardData['iconSvgInline']);
    }

    /**
     * Regression test: the admin setting "Enable whiteboards?" promises to hide
     * the "New Whiteboard" entry from the file creation menu, but the listener
     * used to register both creators unconditionally.
     */
    public function testWhiteboardCreatorIsSkippedWhenWhiteboardsDisabled(): void {
        $creators = $this->dispatchAndCollectCreators($this->createListener('no'));

        $this->assertCount(1, $creators);
        $this->assertSame('.drawio', $creators[0]->jsonSerialize()['extension']);
    }

    public function testDiagramCreatorIsAlwaysRegistered(): void {
        $creators = $this->dispatchAndCollectCreators($this->createListener('no'));

        $this->assertNotEmpty($creators);
        $this->assertSame('New Diagram', $creators[0]->jsonSerialize()['actionLabel']);
    }

    public function testIgnoresUnrelatedEvents(): void {
        $listener = $this->createListener();

        // Must not throw or interact with anything
        $listener->handle(new class extends Event {
        });
        $this->addToAssertionCount(1);
    }
}
