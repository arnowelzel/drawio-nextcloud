<?php

declare(strict_types=1);

/**
 * Runtime doubles for OCA classes from other apps (files, files_sharing,
 * files_versions) that are not part of the nextcloud/ocp package.
 * The API mirrors nextcloud/server stable33. Used by PHPUnit only;
 * psalm uses tests/stubs/oca.phpstub instead.
 */

namespace OCA\Files\Event {
    use OCP\EventDispatcher\Event;

    if (!class_exists(LoadAdditionalScriptsEvent::class)) {
        class LoadAdditionalScriptsEvent extends Event {
        }
    }
}

namespace OCA\Files_Sharing\Event {
    use OCP\EventDispatcher\Event;
    use OCP\Share\IShare;

    if (!class_exists(BeforeTemplateRenderedEvent::class)) {
        class BeforeTemplateRenderedEvent extends Event {
            public const SCOPE_PUBLIC_SHARE_AUTH = 'publicShareAuth';

            public function __construct(
                private IShare $share,
                private ?string $scope = null,
            ) {
                parent::__construct();
            }

            public function getShare(): IShare {
                return $this->share;
            }

            public function getScope(): ?string {
                return $this->scope;
            }
        }
    }
}

namespace OCA\Files_Versions\Versions {
    use OCP\Files\File;
    use OCP\Files\FileInfo;
    use OCP\Files\Storage\IStorage;
    use OCP\IUser;

    if (!interface_exists(IVersion::class)) {
        interface IVersion {
            public function getBackend(): IVersionBackend;

            public function getSourceFile(): FileInfo;

            /** @return int|string */
            public function getRevisionId();

            public function getTimestamp(): int;

            public function getSize(): int;

            public function getSourceFileName(): string;

            public function getMimeType(): string;

            public function getVersionPath(): string;

            public function getUser(): IUser;
        }
    }

    if (!interface_exists(IVersionBackend::class)) {
        interface IVersionBackend {
            public function useBackendForStorage(IStorage $storage): bool;

            /** @return IVersion[] */
            public function getVersionsForFile(IUser $user, FileInfo $file): array;

            public function createVersion(IUser $user, FileInfo $file);

            public function rollback(IVersion $version);

            /** @return resource|false */
            public function read(IVersion $version);

            /** @param int|string $revision */
            public function getVersionFile(IUser $user, FileInfo $sourceFile, $revision): File;
        }
    }

    if (!interface_exists(IVersionManager::class)) {
        interface IVersionManager extends IVersionBackend {
            public function registerBackend(string $storageType, IVersionBackend $backend);
        }
    }
}
