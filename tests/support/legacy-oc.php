<?php

declare(strict_types=1);

/**
 * Minimal runtime shims for private Nextcloud server classes that OCP code
 * touches when running outside a full server (unit tests only).
 */

namespace {
    if (!class_exists('OC')) {
        class OC {
            /** @var \Psr\Container\ContainerInterface|null */
            public static $server = null;
            /** @var string */
            public static $SERVERROOT = '';
            /** @var string */
            public static $configDir = '';
        }
    }

    if (!class_exists('OC_Util')) {
        class OC_Util {
            /** @var list<string> */
            public static array $styles = [];

            public static function addStyle($application, $file = null, $prepend = false): void {
                $path = !empty($application) ? "$application/css/$file" : "css/$file";
                if ($prepend) {
                    array_unshift(self::$styles, $path);
                } else {
                    self::$styles[] = $path;
                }
            }
        }
    }
}

namespace OC {
    if (!class_exists(AppScriptDependency::class)) {
        /**
         * \OCP\Util::addScript() instantiates this private class to track
         * script ordering.
         */
        class AppScriptDependency {
            public function __construct(
                private string $id,
                private array $deps = [],
            ) {
            }

            public function getId(): string {
                return $this->id;
            }

            public function getDeps(): array {
                return $this->deps;
            }

            public function addDep(string $dep): void {
                if (!in_array($dep, $this->deps, true)) {
                    $this->deps[] = $dep;
                }
            }
        }
    }
}

namespace OC\Hooks {
    if (!interface_exists(Emitter::class)) {
        /**
         * \OCP\Files\IRootFolder extends this private interface.
         */
        interface Emitter {
            public function listen($scope, $method, callable $callback);

            public function removeListener($scope = null, $method = null, ?callable $callback = null);
        }
    }
}
