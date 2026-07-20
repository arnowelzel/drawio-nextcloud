<?php
namespace OCA\Drawio\Tests\Unit\Settings;

use OCA\Drawio\Controller\PersonalSettingsController;
use OCA\Drawio\Settings\PersonalSettings;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\TestCase;

final class PersonalSettingsTest extends TestCase
{
    private function createPersonalSettings(?PersonalSettingsController $controller = null): PersonalSettings {
        return new PersonalSettings($controller ?? $this->createMock(PersonalSettingsController::class));
    }

    public function testSectionAndPriority(): void {
        $admin = $this->createPersonalSettings();

        $this->assertSame('drawio', $admin->getSection());
        $this->assertSame(60, $admin->getPriority());
        $this->assertNull($admin->getName());
    }

    public function testDelegationAllowsDrawioAppConfig(): void {
        $this->assertSame(
            ['drawio' => ['/drawio.*/']],
            $this->createPersonalSettings()->getAuthorizedAppConfig()
        );
    }

    public function testGetFormDelegatesToSettingsController(): void {
        $form = new TemplateResponse('drawio', 'settings', [], TemplateResponse::RENDER_AS_BLANK);
        $controller = $this->createMock(PersonalSettingsController::class);
        $controller->expects($this->once())->method('index')->willReturn($form);

        $this->assertSame($form, $this->createPersonalSettings($controller)->getForm());
    }
}
