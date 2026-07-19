<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Settings;

use OCA\Drawio\Controller\AdminSettingsController;
use OCA\Drawio\Settings\AdminSettings;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\TestCase;

final class AdminSettingsTest extends TestCase {

    private function createAdmin(?AdminSettingsController $controller = null): AdminSettings {
        return new AdminSettings($controller ?? $this->createMock(AdminSettingsController::class));
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
        $controller = $this->createMock(AdminSettingsController::class);
        $controller->expects($this->once())->method('index')->willReturn($form);

        $this->assertSame($form, $this->createAdmin($controller)->getForm());
    }
}
