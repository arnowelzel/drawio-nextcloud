<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit\Service;

use OCA\Drawio\Service\PublicShareAuth;
use OCP\AppFramework\PublicShareController;
use OCP\ISession;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;

final class PublicShareAuthTest extends TestCase {

    private function createShare(?string $password, string $token = 'sharetoken123'): IShare {
        $share = $this->createMock(IShare::class);
        $share->method('getPassword')->willReturn($password);
        $share->method('getToken')->willReturn($token);
        return $share;
    }

    private function createSession(array $values): ISession {
        $session = $this->createMock(ISession::class);
        $session->method('get')->willReturnCallback(
            static fn (string $key) => $values[$key] ?? null
        );
        return $session;
    }

    public function testShareWithoutPasswordIsAuthenticated(): void {
        $auth = new PublicShareAuth($this->createSession([]));

        $this->assertTrue($auth->isAuthenticated($this->createShare(null)));
    }

    /**
     * Regression test: since Nextcloud 33 the session stores a JSON map of
     * share token => password hash under
     * PublicShareController::DAV_AUTHENTICATED_FRONTEND instead of the old
     * 'public_link_authenticated' share id entry. Password-protected share
     * links must be recognized as authenticated with the new format.
     */
    public function testAuthenticatedWithNextcloud33SessionFormat(): void {
        $session = $this->createSession([
            PublicShareController::DAV_AUTHENTICATED_FRONTEND => json_encode(['sharetoken123' => 'hashedpw']),
        ]);
        $auth = new PublicShareAuth($session);

        $this->assertTrue($auth->isAuthenticated($this->createShare('hashedpw')));
    }

    public function testNotAuthenticatedWithoutSessionEntry(): void {
        $auth = new PublicShareAuth($this->createSession([]));

        $this->assertFalse($auth->isAuthenticated($this->createShare('hashedpw')));
    }

    public function testNotAuthenticatedForOtherToken(): void {
        $session = $this->createSession([
            PublicShareController::DAV_AUTHENTICATED_FRONTEND => json_encode(['othertoken' => 'hashedpw']),
        ]);
        $auth = new PublicShareAuth($session);

        $this->assertFalse($auth->isAuthenticated($this->createShare('hashedpw')));
    }

    public function testNotAuthenticatedWhenPasswordHashChanged(): void {
        $session = $this->createSession([
            PublicShareController::DAV_AUTHENTICATED_FRONTEND => json_encode(['sharetoken123' => 'oldhash']),
        ]);
        $auth = new PublicShareAuth($session);

        $this->assertFalse($auth->isAuthenticated($this->createShare('newhash')));
    }

    public function testPreNextcloud33SessionFormatIsIgnored(): void {
        // Nextcloud 33+ no longer writes 'public_link_authenticated',
        // so a leftover pre-33 entry must not grant access.
        $session = $this->createSession(['public_link_authenticated' => '42']);
        $auth = new PublicShareAuth($session);

        $this->assertFalse($auth->isAuthenticated($this->createShare('hashedpw')));
    }

    public function testMalformedSessionValueDeniesAccess(): void {
        $session = $this->createSession([
            PublicShareController::DAV_AUTHENTICATED_FRONTEND => '{invalid json',
        ]);
        $auth = new PublicShareAuth($session);

        $this->assertFalse($auth->isAuthenticated($this->createShare('hashedpw')));
    }
}
