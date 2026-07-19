<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Support;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Minimal PSR-11 container installed as \OC::$server in unit tests so that
 * static OCP helpers (\OCP\Server::get) can resolve mocked services.
 */
class FakeServerContainer implements ContainerInterface {

    /** @var array<string, object> */
    public array $services = [];

    public function get(string $id) {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }
        throw new class("Service $id not registered in FakeServerContainer") extends \Exception implements NotFoundExceptionInterface {
        };
    }

    public function has(string $id): bool {
        return isset($this->services[$id]);
    }
}
