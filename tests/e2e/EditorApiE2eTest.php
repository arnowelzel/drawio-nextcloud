<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\E2e;

/**
 * End-to-end tests of the editor backend over HTTP: load/save round trip,
 * optimistic concurrency, previews, versions and the editor page itself.
 */
final class EditorApiE2eTest extends E2ETestCase {

    private static string $fileName;
    private static int $fileId;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        self::$fileName = 'E2E-editor-' . uniqid() . '.drawio';
        self::davPut(self::$fileName, '<mxfile>initial</mxfile>');
        self::$fileId = self::davStat(self::$fileName)['fileid'];
    }

    public function testGetFileInfoReturnsMetadata(): void {
        $response = self::apiClient()->get('/index.php/apps/drawio/ajax/getFileInfo', [
            'query' => ['fileId' => self::$fileId, 'shareToken' => ''],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $data = self::decodeJson((string)$response->getBody());
        $this->assertSame(self::$fileId, $data['id']);
        $this->assertSame(self::$fileName, $data['name']);
        $this->assertSame('application/x-drawio', $data['mime']);
        $this->assertTrue($data['writeable']);
        $this->assertTrue($data['versionsEnabled']);
        $this->assertNotEmpty($data['etag']);
        $this->assertNotEmpty($data['instanceId']);
    }

    /**
     * The contract the editor depends on: a save persists the content and hands
     * back the ETag the file now has, so the next save is not rejected.
     *
     * Do not assert that the ETag differs from the previous one. Nextcloud
     * derives it from the file metadata, which on some filesystems is identical
     * for two writes within the same second, so that assertion fails depending
     * on how fast the machine is rather than on this app being correct.
     */
    public function testLoadSaveRoundTripReturnsTheCurrentEtag(): void {
        $load = self::decodeJson((string)self::apiClient()->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => self::$fileId, 'shareToken' => ''],
        ])->getBody());
        $this->assertSame('<mxfile>initial</mxfile>', $load['xml']);

        $save = self::apiClient()->put('/index.php/apps/drawio/ajax/save', [
            'json' => [
                'fileId' => (string)self::$fileId,
                'shareToken' => '',
                'fileContents' => '<mxfile>version-2</mxfile>',
                'etag' => $load['etag'],
            ],
        ]);
        $this->assertSame(200, $save->getStatusCode());
        $saveData = self::decodeJson((string)$save->getBody());
        $this->assertNotEmpty($saveData['etag']);

        $reload = self::decodeJson((string)self::apiClient()->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => self::$fileId, 'shareToken' => ''],
        ])->getBody());
        $this->assertSame('<mxfile>version-2</mxfile>', $reload['xml']);
        $this->assertSame($saveData['etag'], $reload['etag']);
    }

    public function testSaveWithStaleEtagIsRejectedWithConflict(): void {
        $response = self::apiClient()->put('/index.php/apps/drawio/ajax/save', [
            'json' => [
                'fileId' => (string)self::$fileId,
                'shareToken' => '',
                'fileContents' => '<mxfile>must-not-win</mxfile>',
                'etag' => 'stale-etag-deadbeef',
            ],
        ]);

        $this->assertSame(409, $response->getStatusCode());

        $reload = self::decodeJson((string)self::apiClient()->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => self::$fileId, 'shareToken' => ''],
        ])->getBody());
        $this->assertStringNotContainsString('must-not-win', $reload['xml']);
    }

    public function testSaveWithoutEtagComplainsAboutEtag(): void {
        $response = self::apiClient()->put('/index.php/apps/drawio/ajax/save', [
            'json' => [
                'fileId' => (string)self::$fileId,
                'shareToken' => '',
                'fileContents' => '<mxfile>x</mxfile>',
                'etag' => '',
            ],
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('File etag not supplied', self::decodeJson((string)$response->getBody())['message']);
    }

    public function testSavePreviewMakesServerPreviewAvailable(): void {
        // 1x1 red PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $response = self::apiClient()->post('/index.php/apps/drawio/ajax/savePreview', [
            'json' => [
                'fileId' => (string)self::$fileId,
                'shareToken' => '',
                'previewContents' => base64_encode($png),
            ],
        ]);
        $this->assertSame(200, $response->getStatusCode());

        $preview = self::apiClient()->get('/index.php/core/preview', [
            'query' => ['fileId' => self::$fileId, 'x' => 64, 'y' => 64, 'a' => 'true'],
            'headers' => ['Accept' => 'image/*'],
        ]);
        $this->assertSame(200, $preview->getStatusCode());
        $this->assertStringContainsString('image/png', $preview->getHeaderLine('Content-Type'));
    }

    public function testFileRevisionsAreListedAndLoadable(): void {
        // Ensure at least one more write so a version of the previous content exists
        $load = self::decodeJson((string)self::apiClient()->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => self::$fileId, 'shareToken' => ''],
        ])->getBody());
        self::apiClient()->put('/index.php/apps/drawio/ajax/save', [
            'json' => [
                'fileId' => (string)self::$fileId,
                'shareToken' => '',
                'fileContents' => '<mxfile>version-3</mxfile>',
                'etag' => $load['etag'],
            ],
        ]);

        $revisions = self::apiClient()->get('/index.php/apps/drawio/ajax/getFileRevisions', [
            'query' => ['fileId' => self::$fileId],
        ]);
        $this->assertSame(200, $revisions->getStatusCode());
        $list = self::decodeJson((string)$revisions->getBody());

        $this->assertNotEmpty($list, 'Expected at least one file version after multiple saves');
        $this->assertArrayHasKey('revId', $list[0]);
        $this->assertArrayHasKey('timestamp', $list[0]);

        $version = self::apiClient()->get('/index.php/apps/drawio/ajax/loadFileVersion', [
            'query' => ['fileId' => self::$fileId, 'revId' => $list[0]['revId']],
        ]);
        $this->assertSame(200, $version->getStatusCode());
        // The version content is returned as a JSON-encoded string
        $content = json_decode((string)$version->getBody());
        $this->assertIsString($content);
        $this->assertStringContainsString('<mxfile>', $content);
    }

    public function testEditorPageRendersWithDrawioCsp(): void {
        $response = self::apiClient()->get('/index.php/apps/drawio/edit', [
            'query' => ['fileId' => self::$fileId, 'isWB' => 'false'],
            'headers' => ['Accept' => 'text/html'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $html = (string)$response->getBody();
        $this->assertStringContainsString('iframeEditor', $html);
        $this->assertStringContainsString('drawioData', $html);

        $csp = $response->getHeaderLine('Content-Security-Policy');
        $this->assertMatchesRegularExpression('#frame-src[^;]*https://embed\.diagrams\.net#', $csp);
        $this->assertMatchesRegularExpression('#frame-src[^;]*blob:#', $csp);
        $this->assertMatchesRegularExpression('#worker-src[^;]*https://embed\.diagrams\.net#', $csp);
        $this->assertMatchesRegularExpression('#worker-src[^;]*blob:#', $csp);
        $this->assertMatchesRegularExpression('#script-src[^;]*https://embed\.diagrams\.net#', $csp);
    }

    public function testCreateEndpointCreatesEmptyDiagram(): void {
        $rootId = self::davStat('')['fileid'];
        $name = 'E2E-create-' . uniqid() . '.drawio';
        self::$cleanupPaths[] = $name;

        $response = self::apiClient()->post('/index.php/apps/drawio/ajax/new', [
            'json' => ['name' => $name, 'dirId' => (string)$rootId, 'shareToken' => ''],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $data = self::decodeJson((string)$response->getBody());
        $this->assertArrayNotHasKey('error', $data);
        $this->assertSame($name, $data['name']);
        $this->assertSame('application/x-drawio', $data['mimetype']);
        $this->assertGreaterThan(0, $data['id']);
    }
}
