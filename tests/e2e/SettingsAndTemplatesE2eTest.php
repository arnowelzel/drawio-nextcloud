<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\E2e;

/**
 * End-to-end tests of the admin settings endpoint and its effect on the
 * "+" new file menu (template creators), including the whiteboards toggle.
 */
final class SettingsAndTemplatesE2eTest extends E2ETestCase {

    private static function postSettings(string $whiteboards): array {
        $response = self::apiClient()->post('/index.php/apps/drawio/ajax/settings', [
            'form_params' => [
                'drawioUrl' => '',
                'offlineMode' => 'no',
                'theme' => 'kennedy',
                'lang' => 'auto',
                'autosave' => 'yes',
                'libraries' => 'no',
                'darkMode' => 'auto',
                'previews' => 'yes',
                'drawioConfig' => '',
                'whiteboards' => $whiteboards,
            ],
        ]);
        self::assertSame(200, $response->getStatusCode(), 'settings save failed: ' . $response->getBody());
        return self::decodeJson((string)$response->getBody());
    }

    /**
     * @return list<string> action labels of the drawio template creators
     */
    private static function drawioTemplateCreatorLabels(): array {
        $response = self::apiClient()->get('/ocs/v2.php/apps/files/api/v1/templates?format=json');
        self::assertSame(200, $response->getStatusCode());

        $creators = self::decodeJson((string)$response->getBody())['ocs']['data'];
        $labels = [];
        foreach ($creators as $creator) {
            if (($creator['app'] ?? '') === 'drawio') {
                $labels[] = $creator['actionLabel'];
            }
        }
        return $labels;
    }

    public static function tearDownAfterClass(): void {
        // Safety net: restore defaults even if a test failed midway
        try {
            self::postSettings('yes');
        } catch (\Throwable $e) {
        }
        parent::tearDownAfterClass();
    }

    public function testSettingsRoundTripEchoesPersistedValues(): void {
        $result = self::postSettings('yes');

        $this->assertSame('https://embed.diagrams.net', $result['drawioUrl']);
        $this->assertSame('kennedy', $result['theme']);
        $this->assertSame('no', $result['offlineMode']);
        $this->assertSame('yes', $result['drawioAutosave']);
        $this->assertSame('{}', $result['drawioConfig']);
        $this->assertSame('yes', $result['drawioWhiteboards']);
    }

    public function testWhiteboardsToggleControlsNewFileMenuEntries(): void {
        self::postSettings('yes');
        $labels = self::drawioTemplateCreatorLabels();
        $this->assertContains('New Diagram', $labels);
        $this->assertContains('New Whiteboard', $labels);

        self::postSettings('no');
        $labels = self::drawioTemplateCreatorLabels();
        $this->assertContains('New Diagram', $labels);
        $this->assertNotContains('New Whiteboard', $labels,
            'Disabling whiteboards must remove the New Whiteboard template creator');

        self::postSettings('yes');
        $this->assertContains('New Whiteboard', self::drawioTemplateCreatorLabels());
    }
}
