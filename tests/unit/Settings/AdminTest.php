<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Settings;

use OCA\Drawio\Controller\SettingsController;
use OCA\Drawio\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase {

    private function createAdmin(?SettingsController $controller = null): Admin {
        return new Admin($controller ?? $this->createMock(SettingsController::class));
    }

    public function testSectionAndPriority(): void {
        $admin = $this->createAdmin();

        $this->assertSame('drawio', $admin->getSection());
        $this->assertSame(60, $admin->getPriority());
        $this->assertNull($admin->getName());
    }

    public function testDelegationAllowsDrawioAppConfig(): void {
        $this->assertSame(
            ['drawio' => ['/drawio.*/']],
            $this->createAdmin()->getAuthorizedAppConfig()
        );
    }

    public function testGetFormDelegatesToSettingsController(): void {
        $form = new TemplateResponse('drawio', 'settings', [], TemplateResponse::RENDER_AS_BLANK);
        $controller = $this->createMock(SettingsController::class);
        $controller->expects($this->once())->method('index')->willReturn($form);

        $this->assertSame($form, $this->createAdmin($controller)->getForm());
    }
}
