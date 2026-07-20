<?php
namespace OCA\Drawio\Migration;

/**
 * NOTE: This class and its subclasses use \OC::$configDir and \OC::$SERVERROOT,
 * for which there is no public OCP API. Registering custom MIME types via
 * config/mimetypemapping.json is the approach recommended by the Nextcloud
 * documentation and shared by other apps (Keeweb, Mind Map). These usages
 * should be reviewed if Nextcloud provides a public MIME type registration API
 * (https://github.com/nextcloud/server/issues/10131).
 **/

use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

abstract class MimeTypeMigration implements IRepairStep
{
    const CUSTOM_MIMETYPEMAPPING = 'mimetypemapping.json';
    const CUSTOM_MIMETYPEALIASES = 'mimetypealiases.json';

    // File type icons that app versions up to 4.2.x copied into the Nextcloud
    // core, where the code integrity check reports them as EXTRA_FILE
    // (https://github.com/jgraph/drawio-nextcloud/issues/70).
    const LEGACY_CORE_ICONS = ['drawio.svg', 'dwb.svg'];

    // MIME type aliases those versions registered so the icons above were used.
    // The app no longer ships those icons, so the aliases have no effect - but
    // they would be baked back into core/js/mimetypelist.js by the next
    // "occ maintenance:mimetype:update-js" run and break the integrity check.
    const LEGACY_ALIASES = [
        'application/x-drawio' => 'drawio',
        'application/x-drawio-wb' => 'dwb'
    ];

    public function __construct(protected IMimeTypeLoader $mimeTypeLoader)
    {
    }

    /**
     * Delete the file type icons older versions of this app copied into the
     * Nextcloud core directory.
     */
    protected function removeLegacyCoreIcons(IOutput $output): void
    {
        foreach (self::LEGACY_CORE_ICONS as $icon) {
            $path = \OC::$SERVERROOT . '/core/img/filetypes/' . $icon;

            if (!file_exists($path)) {
                continue;
            }

            if (@unlink($path)) {
                $output->info('Removed ' . $path . ', which an older version of this app copied into the Nextcloud core');
            } else {
                $output->warning('Could not remove ' . $path . '. Delete it manually to fix the code integrity check.');
            }
        }
    }

    /**
     * Merge the given entries into a MIME type configuration file, keeping the
     * entries other apps have registered.
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
     * Remove the given entries from a MIME type configuration file, keeping the
     * entries other apps have registered.
     */
    protected function removeFromFile(string $filename, array $data): void
    {
        if (!file_exists($filename)) {
            return;
        }

        $config = $this->readFile($filename);

        foreach (array_keys($data) as $key) {
            unset($config[$key]);
        }

        $this->writeFile($filename, $config);
    }

    private function readFile(string $filename): array
    {
        if (!file_exists($filename)) {
            return [];
        }

        $config = json_decode((string)file_get_contents($filename), true);

        return is_array($config) ? $config : [];
    }

    private function writeFile(string $filename, array $config): void
    {
        // An empty array would be encoded as "[]", but Nextcloud expects these
        // files to hold a JSON object.
        $data = $config === [] ? new \stdClass() : $config;

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
