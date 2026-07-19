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
        private ISession $session,
    ) {
    }

    public function isAuthenticated(IShare $share): bool {
        if ($share->getPassword() === null) {
            return true;
        }

        $allowedTokensJson = $this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND);
        $allowedTokens = is_string($allowedTokensJson) ? json_decode($allowedTokensJson, true) : null;
        if (!is_array($allowedTokens)) {
            $allowedTokens = [];
        }

        return ($allowedTokens[$share->getToken()] ?? '') === $share->getPassword();
    }
}
