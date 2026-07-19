<?php

declare(strict_types=1);

namespace OCA\Drawio\Service;

use OCP\AppFramework\PublicShareController;
use OCP\ISession;
use OCP\Share\IShare;

/**
 * Checks whether the current session is authenticated for a public share.
 *
 * Mirrors {@see PublicShareController::isAuthenticated()}: since Nextcloud 33
 * the session stores a JSON map of share token to password hash under
 * {@see PublicShareController::DAV_AUTHENTICATED_FRONTEND}.
 */
class PublicShareAuth {

    public function __construct(
        private readonly ISession $session,
    ) {
    }

    public function isAuthenticated(IShare $share): bool {
        $password = $share->getPassword();

        if ($password === null) {
            return true;
        }

        return ($this->authenticatedTokens()[$share->getToken()] ?? '') === $password;
    }

    /**
     * @return array<string, string>
     */
    private function authenticatedTokens(): array {
        $allowedTokensJson = $this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND);

        if (!is_string($allowedTokensJson)) {
            return [];
        }

        $allowedTokens = json_decode($allowedTokensJson, true);

        return is_array($allowedTokens) ? $allowedTokens : [];
    }
}
