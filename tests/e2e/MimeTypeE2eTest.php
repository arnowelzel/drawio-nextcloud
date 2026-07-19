<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\E2e;

/**
 * End-to-end proof of the MIME registration chain: files uploaded through
 * WebDAV must be detected as draw.io types by the server (config
 * mimetypemapping.json written by the repair step + runtime registration).
 */
final class MimeTypeE2eTest extends E2ETestCase {

    public function testUploadedDrawioFileGetsDrawioMimeType(): void {
        $name = 'E2E-mime-' . uniqid() . '.drawio';

        $status = self::davPut($name, '<mxfile><diagram id="a" name="P1"/></mxfile>');
        $this->assertContains($status, [201, 204]);

        $stat = self::davStat($name);
        $this->assertSame('application/x-drawio', $stat['contenttype']);
        $this->assertGreaterThan(0, $stat['fileid']);
    }

    public function testUploadedWhiteboardFileGetsWhiteboardMimeType(): void {
        $name = 'E2E-mime-' . uniqid() . '.dwb';

        $status = self::davPut($name, '<mxfile><diagram id="a" name="P1"/></mxfile>');
        $this->assertContains($status, [201, 204]);

        $this->assertSame('application/x-drawio-wb', self::davStat($name)['contenttype']);
    }

    public function testTemplateApiCreatesFileWithDrawioMimeType(): void {
        $name = 'E2E-template-' . uniqid() . '.drawio';
        self::$cleanupPaths[] = $name;

        $response = self::apiClient()->post('/ocs/v2.php/apps/files/api/v1/templates/create?format=json', [
            'json' => ['filePath' => '/' . $name, 'templatePath' => '', 'templateType' => 'user'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $data = self::decodeJson((string)$response->getBody())['ocs']['data'];
        $this->assertSame('application/x-drawio', $data['mime']);
        $this->assertGreaterThan(0, $data['fileid']);
    }
}
