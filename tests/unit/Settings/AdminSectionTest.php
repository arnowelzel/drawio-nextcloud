<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Settings;

use OCA\Drawio\Settings\AdminSection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

final class AdminSectionTest extends TestCase {

    public function testSectionMetadata(): void {
        $l10n = $this->createMock(IL10N::class);
        $l10n->method('t')->willReturnCallback(static fn (string $text, $params = []) => $text);
        $urlGenerator = $this->createMock(IURLGenerator::class);
        $urlGenerator->method('imagePath')->with('drawio', 'app.svg')->willReturn('/img/app.svg');

        $section = new AdminSection($urlGenerator, $l10n);

        $this->assertSame('drawio', $section->getID());
        $this->assertSame('Diagramming', $section->getName());
        $this->assertSame(75, $section->getPriority());
        $this->assertSame('/img/app.svg', $section->getIcon());
    }
}
