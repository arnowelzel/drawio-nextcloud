<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\E2e;

use GuzzleHttp\Cookie\CookieJar;

/**
 * End-to-end tests of the public share flow.
 *
 * Two scenarios are covered:
 *
 * - Password protected links, including the Nextcloud 33 share password
 *   session mechanism (public_link_authenticated_frontend): anonymous
 *   visitors must be rejected before entering the share password and
 *   accepted afterwards.
 * - Links without password protection, which anonymous visitors must be
 *   able to open right away.
 */
final class PublicShareE2eTest extends E2ETestCase {

    private const SHARE_PASSWORD = 'Fixture#2026!drawio';

    private static string $fileName;
    private static int $shareId;
    private static string $shareToken;

    private static string $openFileName;
    private static int $openFileId;
    private static string $openShareToken;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        self::$fileName = 'E2E-share-' . uniqid() . '.drawio';
        self::davPut(self::$fileName, '<mxfile>shared-content</mxfile>');

        $data = self::createLinkShare(self::$fileName, self::SHARE_PASSWORD);
        self::$shareId = (int)$data['id'];
        self::$shareToken = (string)$data['token'];

        self::$openFileName = 'E2E-openshare-' . uniqid() . '.drawio';
        self::davPut(self::$openFileName, '<mxfile>public-content</mxfile>');
        self::$openFileId = self::davStat(self::$openFileName)['fileid'];

        self::$openShareToken = (string)self::createLinkShare(self::$openFileName)['token'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function createLinkShare(string $path, ?string $password = null): array {
        $share = ['path' => '/' . $path, 'shareType' => 3];

        if ($password !== null) {
            $share['password'] = $password;
        }

        $response = self::apiClient()->post('/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json', [
            'json' => $share,
        ]);

        if ($response->getStatusCode() !== 200) {
            self::fail('Could not create link share for ' . $path . ': ' . $response->getBody());
        }

        return json_decode((string)$response->getBody(), true)['ocs']['data'];
    }

    /**
     * @return array{jar: CookieJar, token: string, authUrl: string}
     */
    private function openShareAuthPage(): array {
        $jar = new CookieJar();
        $response = self::browserClient($jar)->get('/index.php/s/' . self::$shareToken, [
            'allow_redirects' => ['track_redirects' => true],
        ]);
        $this->assertSame(200, $response->getStatusCode());

        $html = (string)$response->getBody();
        $this->assertStringContainsString('core-public-share-auth', $html,
            'Share page should show the password prompt');

        // The auth page (GET) and the authenticate endpoint (POST) share the
        // same URL, so the last redirect target is where the password form posts to.
        $redirects = $response->getHeader('X-Guzzle-Redirect-History');
        $authUrl = $redirects === [] ? ('/index.php/s/' . self::$shareToken) : end($redirects);

        return [
            'jar' => $jar,
            'token' => self::extractRequestToken($html),
            'authUrl' => $authUrl,
        ];
    }

    private function authenticate(array $session, string $password): void {
        $response = self::browserClient($session['jar'])->post($session['authUrl'], [
            'form_params' => [
                'password' => $password,
                'requesttoken' => $session['token'],
            ],
        ]);
        $this->assertContains($response->getStatusCode(), [200, 303]);
    }

    private function getFileInfoAnonymously(array $session): \Psr\Http\Message\ResponseInterface {
        return self::browserClient($session['jar'])->get('/index.php/apps/drawio/ajax/getFileInfo', [
            'query' => ['fileId' => '', 'shareToken' => self::$shareToken],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ]);
    }

    public function testAnonymousAccessIsDeniedBeforePasswordEntry(): void {
        $session = $this->openShareAuthPage();

        $response = $this->getFileInfoAnonymously($session);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testWrongPasswordDoesNotAuthenticate(): void {
        $session = $this->openShareAuthPage();

        $this->authenticate($session, 'definitely-wrong-password');

        $this->assertSame(403, $this->getFileInfoAnonymously($session)->getStatusCode());
    }

    public function testCorrectPasswordGrantsReadOnlyAccess(): void {
        $session = $this->openShareAuthPage();
        $this->authenticate($session, self::SHARE_PASSWORD);

        $info = $this->getFileInfoAnonymously($session);
        $this->assertSame(200, $info->getStatusCode());
        $data = self::decodeJson((string)$info->getBody());
        $this->assertSame(self::$fileName, $data['name']);
        $this->assertFalse($data['writeable']);
        $this->assertFalse($data['versionsEnabled']);

        $load = self::browserClient($session['jar'])->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => '', 'shareToken' => self::$shareToken],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ]);
        $this->assertSame(200, $load->getStatusCode());
        $this->assertSame('<mxfile>shared-content</mxfile>', self::decodeJson((string)$load->getBody())['xml']);
    }

    public function testSaveThroughReadOnlyShareIsForbidden(): void {
        $session = $this->openShareAuthPage();
        $this->authenticate($session, self::SHARE_PASSWORD);

        $load = self::decodeJson((string)self::browserClient($session['jar'])->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => '', 'shareToken' => self::$shareToken],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ])->getBody());

        $save = self::browserClient($session['jar'])->put('/index.php/apps/drawio/ajax/save', [
            'json' => [
                'fileId' => '',
                'shareToken' => self::$shareToken,
                'fileContents' => '<mxfile>anonymous-write</mxfile>',
                'etag' => $load['etag'],
            ],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ]);

        $this->assertSame(403, $save->getStatusCode());
    }

    public function testEditableShareAllowsAnonymousSave(): void {
        $update = self::apiClient()->put('/ocs/v2.php/apps/files_sharing/api/v1/shares/' . self::$shareId . '?format=json', [
            'json' => ['permissions' => 3], // read + update
        ]);
        $this->assertSame(200, $update->getStatusCode());

        $session = $this->openShareAuthPage();
        $this->authenticate($session, self::SHARE_PASSWORD);

        $load = self::decodeJson((string)self::browserClient($session['jar'])->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => '', 'shareToken' => self::$shareToken],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ])->getBody());
        $this->assertTrue($load['writeable']);

        $save = self::browserClient($session['jar'])->put('/index.php/apps/drawio/ajax/save', [
            'json' => [
                'fileId' => '',
                'shareToken' => self::$shareToken,
                'fileContents' => '<mxfile>anonymous-edit</mxfile>',
                'etag' => $load['etag'],
            ],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ]);
        $this->assertSame(200, $save->getStatusCode());

        $verify = self::decodeJson((string)self::apiClient()->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => self::davStat(self::$fileName)['fileid'], 'shareToken' => ''],
        ])->getBody());
        $this->assertSame('<mxfile>anonymous-edit</mxfile>', $verify['xml']);
    }

    public function testUnknownShareTokenIsNotFound(): void {
        $response = self::apiClient()->get('/index.php/apps/drawio/ajax/getFileInfo', [
            'query' => ['fileId' => '', 'shareToken' => 'doesnotexist' . uniqid()],
        ]);

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * Opens the editor the way an anonymous visitor does when following a
     * public link, and returns the page body together with the session that
     * the browser would keep for the follow-up requests.
     *
     * @return array{jar: CookieJar, token: string, html: string}
     */
    private function openEditorAnonymously(): array {
        $jar = new CookieJar();
        $response = self::browserClient($jar)->get('/index.php/apps/drawio/edit', [
            'query' => [
                'fileId' => self::$openFileId,
                'shareToken' => self::$openShareToken,
                'isWB' => 'false',
            ],
            'headers' => ['Accept' => 'text/html'],
        ]);

        $this->assertSame(200, $response->getStatusCode(),
            'The editor must open on a public link without a password');

        $html = (string)$response->getBody();

        return ['jar' => $jar, 'token' => self::extractRequestToken($html), 'html' => $html];
    }

    /**
     * Regression test for the 500 error on public links (#32): the editor page
     * reads the personal settings, which asked the user config for values
     * without a user ID when nobody is logged in.
     */
    public function testEditorOpensAnonymouslyOnLinkWithoutPassword(): void {
        $session = $this->openEditorAnonymously();

        $this->assertStringContainsString('iframeEditor', $session['html'],
            'The editor iframe must be rendered for anonymous visitors');
        $this->assertStringContainsString('drawioData', $session['html']);
    }

    /**
     * The editor configuration must fall back to the admin defaults instead of
     * failing when there is no user to read personal settings for.
     */
    public function testEditorConfigurationFallsBackToDefaultsForAnonymousVisitors(): void {
        $session = $this->openEditorAnonymously();

        preg_match('#<div style="display: none" id="drawioData">([^<]+)</div>#', $session['html'], $matches);
        $this->assertNotEmpty($matches[1] ?? '', 'No drawioData payload found on the editor page');

        $data = self::decodeJson((string)base64_decode($matches[1]));
        $this->assertSame(self::$openShareToken, $data['shareToken']);
        $this->assertNotEmpty($data['drawioUrl']);
        $this->assertNotEmpty($data['drawioTheme']);
        $this->assertArrayHasKey('drawioLang', $data);
        $this->assertArrayHasKey('drawioDarkMode', $data);
    }

    public function testFileIsReadableAnonymouslyOnLinkWithoutPassword(): void {
        $session = $this->openEditorAnonymously();

        $info = self::browserClient($session['jar'])->get('/index.php/apps/drawio/ajax/getFileInfo', [
            'query' => ['fileId' => '', 'shareToken' => self::$openShareToken],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ]);
        $this->assertSame(200, $info->getStatusCode());

        $data = self::decodeJson((string)$info->getBody());
        $this->assertSame(self::$openFileName, $data['name']);
        $this->assertSame('application/x-drawio', $data['mime']);
        $this->assertFalse($data['writeable'], 'A link share is read only by default');

        $load = self::browserClient($session['jar'])->get('/index.php/apps/drawio/ajax/load', [
            'query' => ['fileId' => '', 'shareToken' => self::$openShareToken],
            'headers' => ['requesttoken' => $session['token'], 'Accept' => 'application/json'],
        ]);
        $this->assertSame(200, $load->getStatusCode());
        $this->assertSame('<mxfile>public-content</mxfile>', self::decodeJson((string)$load->getBody())['xml']);
    }

    public function testLinkWithoutPasswordDoesNotAskForOne(): void {
        $response = self::browserClient(new CookieJar())->get('/index.php/s/' . self::$openShareToken);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('core-public-share-auth', (string)$response->getBody(),
            'A link without a password must not show the password prompt');
    }
}
