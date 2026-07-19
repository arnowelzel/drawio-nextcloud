<?php

declare(strict_types=1);

/**
 * NOTE: This class and its subclasses use \OC::$configDir, for which there is
 * no public OCP API. Registering custom MIME types via config/mimetypemapping.json
 * is the approach recommended by the Nextcloud documentation and shared by other
 * apps (Keeweb, Mind Map). These usages should be reviewed if Nextcloud provides
 * a public MIME type registration API (https://github.com/nextcloud/server/issues/10131).
 **/

namespace OCA\Drawio\Migration;

use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IRepairStep;

abstract class MimeTypeMigration implements IRepairStep
{
    protected const CUSTOM_MIMETYPEMAPPING = 'mimetypemapping.json';
    protected const CUSTOM_MIMETYPEALIASES = 'mimetypealiases.json';

    public function __construct(
        protected readonly IMimeTypeLoader $mimeTypeLoader,
    ) {
    }

    /**
     * Merge the given entries into a MIME type configuration file, keeping
     * the entries other apps have registered.
     *
     * @param array<string, mixed> $data
     */
    protected function appendToFile(string $filename, array $data): void
    {
        $config = $this->readFile($filename);

        foreach ($data as $key => $value) {
            $config[$key] = $value;
        }

        $this->writeFile($filename, $config);
    }

    /**
     * Remove the given entries from a MIME type configuration file, keeping
     * the entries other apps have registered.
     *
     * @param array<string, mixed> $data
     */
    protected function removeFromFile(string $filename, array $data): void
    {
        $config = $this->readFile($filename);

        foreach (array_keys($data) as $key) {
            unset($config[$key]);
        }

        $this->writeFile($filename, $config);
    }

    /**
     * @return array<string, mixed>
     */
    private function readFile(string $filename): array
    {
        if (!file_exists($filename)) {
            return [];
        }

        $config = json_decode((string)file_get_contents($filename), true);

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeFile(string $filename, array $config): void
    {
        file_put_contents($filename, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
