<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Controller;

use OCA\Drawio\AppConfig;
use OCA\Drawio\Controller\AdminSettingsController;
use OCA\Drawio\Tests\Support\ResetsGlobalState;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminSettingsControllerTest extends TestCase {
    use ResetsGlobalState;

    private IRequest&MockObject $request;
    private AppConfig&MockObject $appConfig;

    protected function setUp(): void {
        $this->resetGlobalState();
        $this->request = $this->createMock(IRequest::class);
        $this->appConfig = $this->createMock(AppConfig::class);
    }

    private function createController(): AdminSettingsController {
        return new AdminSettingsController($this->request, $this->appConfig);
    }

    private function configureGetters(): void {
        $this->appConfig->method('GetDrawioUrl')->willReturn('https://embed.diagrams.net');
        $this->appConfig->method('GetOfflineMode')->willReturn('no');
        $this->appConfig->method('GetTheme')->willReturn('kennedy');
        $this->appConfig->method('GetLang')->willReturn('auto');
        $this->appConfig->method('GetAutosave')->willReturn('yes');
        $this->appConfig->method('GetLibraries')->willReturn('no');
        $this->appConfig->method('GetDarkMode')->willReturn('auto');
        $this->appConfig->method('GetPreviews')->willReturn('yes');
        $this->appConfig->method('GetDrawioConfig')->willReturn('{}');
        $this->appConfig->method('GetWhiteboards')->willReturn('yes');
    }

    public function testIndexRendersBlankSettingsTemplate(): void {
        $this->configureGetters();

        $response = $this->createController()->index();

        $this->assertInstanceOf(TemplateResponse::class, $response);
        $this->assertSame('adminSettings', $response->getTemplateName());
        $this->assertSame(TemplateResponse::RENDER_AS_BLANK, $response->getRenderAs());
        $params = $response->getParams();
        $this->assertSame('https://embed.diagrams.net', $params['drawioUrl']);
        $this->assertSame('yes', $params['drawioWhiteboards']);

        $this->assertContains('drawio/js/adminSettings', self::scripts()['drawio'] ?? []);
        $this->assertContains('drawio/css/settings', \OC_Util::$styles);
    }

    public function testSettingsTrimsAndPersistsAllValues(): void {
        $params = [
            'drawioUrl' => '  https://draw.example.com  ',
            'offlineMode' => 'yes ',
            'theme' => ' atlas',
            'lang' => ' de ',
            'autosave' => 'no',
            'libraries' => 'yes',
            'darkMode' => 'on',
            'previews' => 'no',
            'drawioConfig' => ' {"a":1} ',
            'whiteboards' => 'no',
        ];
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, $default = null) => $params[$key] ?? $default
        );

        $this->appConfig->expects($this->once())->method('SetDrawioUrl')->with('https://draw.example.com');
        $this->appConfig->expects($this->once())->method('SetOfflineMode')->with('yes');
        $this->appConfig->expects($this->once())->method('SetTheme')->with('atlas');
        $this->appConfig->expects($this->once())->method('SetLang')->with('de');
        $this->appConfig->expects($this->once())->method('SetAutosave')->with('no');
        $this->appConfig->expects($this->once())->method('SetLibraries')->with('yes');
        $this->appConfig->expects($this->once())->method('SetDarkMode')->with('on');
        $this->appConfig->expects($this->once())->method('SetPreviews')->with('no');
        $this->appConfig->expects($this->once())->method('SetDrawioConfig')->with('{"a":1}');
        $this->appConfig->expects($this->once())->method('SetWhiteboards')->with('no');

        $this->configureGetters();

        $result = $this->createController()->settings();

        $this->assertSame('https://embed.diagrams.net', $result['drawioUrl']);
        $this->assertArrayHasKey('drawioWhiteboards', $result);
        $this->assertArrayHasKey('drawioConfig', $result);
    }
}
