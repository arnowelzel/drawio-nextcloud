<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\E2e;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;

/**
 * Base class for end-to-end tests against a running Nextcloud instance
 * (scripts/dev-setup.sh / docker-compose.yml).
 *
 * Configuration via environment:
 * - NEXTCLOUD_E2E_BASE_URL  (default http://localhost:8088)
 * - NEXTCLOUD_E2E_ADMIN_USER / NEXTCLOUD_E2E_ADMIN_PASSWORD (default admin/admin)
 *
 * The whole class is skipped when the instance is not reachable.
 */
abstract class E2ETestCase extends TestCase {

    protected static string $baseUrl;
    protected static string $adminUser;
    protected static string $adminPassword;

    /** @var list<string> WebDAV paths (relative to the admin files root) to delete after the class */
    protected static array $cleanupPaths = [];

    public static function setUpBeforeClass(): void {
        self::$baseUrl = rtrim(getenv('NEXTCLOUD_E2E_BASE_URL') ?: 'http://localhost:8088', '/');
        self::$adminUser = getenv('NEXTCLOUD_E2E_ADMIN_USER') ?: 'admin';
        self::$adminPassword = getenv('NEXTCLOUD_E2E_ADMIN_PASSWORD') ?: 'admin';

        try {
            $status = (new Client(['timeout' => 5]))->get(self::$baseUrl . '/status.php');
            $body = (string)$status->getBody();
            if ($status->getStatusCode() !== 200 || !str_contains($body, '"installed":true')) {
                self::markTestSkipped('Nextcloud e2e instance is not installed at ' . self::$baseUrl);
            }
        } catch (\Throwable $e) {
            self::markTestSkipped('Nextcloud e2e instance is not reachable at ' . self::$baseUrl . ': ' . $e->getMessage());
        }

        // Pin the admin language so message assertions are deterministic
        self::apiClient()->put('/ocs/v2.php/cloud/users/' . self::$adminUser, [
            'form_params' => ['key' => 'language', 'value' => 'en'],
        ]);
    }

    public static function tearDownAfterClass(): void {
        foreach (self::$cleanupPaths as $path) {
            try {
                self::apiClient()->delete('/remote.php/dav/files/' . self::$adminUser . '/' . ltrim($path, '/'));
            } catch (\Throwable $e) {
                // best effort cleanup
            }
        }
        self::$cleanupPaths = [];
    }

    /**
     * Cookie-less API client authenticated as admin. The OCS-APIRequest
     * header marks the request as an API request, which is how non-browser
     * clients pass the CSRF checks on both OCS and app routes.
     */
    protected static function apiClient(): Client {
        return new Client([
            'base_uri' => self::$baseUrl,
            'auth' => [self::$adminUser, self::$adminPassword],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    /**
     * Browser-like anonymous client: keeps cookies, sends no auth and no
     * OCS header, so it is subject to the same CSRF/session rules as a
     * real visitor.
     */
    protected static function browserClient(CookieJar $jar): Client {
        return new Client([
            'base_uri' => self::$baseUrl,
            'cookies' => $jar,
            'http_errors' => false,
            'timeout' => 30,
            'allow_redirects' => true,
        ]);
    }

    protected static function extractRequestToken(string $html): string {
        if (!preg_match('/data-requesttoken="([^"]+)"/', $html, $matches)) {
            self::fail('No requesttoken found in page');
        }
        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    protected static function davPut(string $path, string $content): int {
        self::$cleanupPaths[] = $path;
        $response = self::apiClient()->put(
            '/remote.php/dav/files/' . self::$adminUser . '/' . ltrim($path, '/'),
            ['body' => $content]
        );
        return $response->getStatusCode();
    }

    /**
     * @return array{fileid: int, contenttype: string}
     */
    protected static function davStat(string $path): array {
        $response = self::apiClient()->request('PROPFIND',
            '/remote.php/dav/files/' . self::$adminUser . '/' . ltrim($path, '/'),
            [
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?>
                    <d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
                        <d:prop><oc:fileid/><d:getcontenttype/></d:prop>
                    </d:propfind>',
            ]
        );
        self::assertSame(207, $response->getStatusCode(), 'PROPFIND ' . $path . ' failed');

        $body = (string)$response->getBody();
        preg_match('#<oc:fileid>(\d+)</oc:fileid>#', $body, $fileId);
        preg_match('#<d:getcontenttype>([^<]*)</d:getcontenttype>#', $body, $contentType);

        return [
            'fileid' => (int)($fileId[1] ?? 0),
            'contenttype' => $contentType[1] ?? '',
        ];
    }

    protected static function decodeJson(string $body): array {
        $decoded = json_decode($body, true);
        self::assertIsArray($decoded, 'Expected JSON response, got: ' . substr($body, 0, 200));
        return $decoded;
    }
}
