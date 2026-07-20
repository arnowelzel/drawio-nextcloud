<?php
namespace OCA\Drawio\Migration;

use OCP\Migration\IOutput;

class UnregisterMimeType extends MimeTypeMigration
{
    public function getName(): string
    {
        return 'Unregister MIME type for Diagramming';
    }

    private function unregisterForExistingFiles(): void
    {
        $mimeTypeId = $this->mimeTypeLoader->getId('application/octet-stream');
        $this->mimeTypeLoader->updateFilecache('drawio', $mimeTypeId);
        $this->mimeTypeLoader->updateFilecache('dwb', $mimeTypeId);
    }

    private function unregisterForNewFiles(): void
    {
        $this->removeFromFile(\OC::$configDir . self::CUSTOM_MIMETYPEMAPPING, [
            'drawio' => ['application/x-drawio'],
            'dwb' => ['application/x-drawio-wb']
        ]);

        // Written by app versions up to 4.2.x
        $this->removeFromFile(\OC::$configDir . self::CUSTOM_MIMETYPEALIASES, self::LEGACY_ALIASES);
    }

    public function run(IOutput $output): void
    {
        $output->info('Unregistering the mimetype...');

        // Reset existing files to the generic MIME type
        $this->unregisterForExistingFiles();

        // Remove the MIME type registration for new files
        $this->unregisterForNewFiles();

        // Remove the icons older versions copied into the Nextcloud core
        $this->removeLegacyCoreIcons($output);

        $output->info('The mimetype was successfully unregistered.');
    }
}
