<?php

declare(strict_types=1);

namespace OCA\Drawio\Migration;

use OCP\Migration\IOutput;

class UnregisterMimeType extends MimeTypeMigration
{
    public function getName(): string
    {
        return 'Unregister MIME type for Diagramming';
    }

    public function run(IOutput $output): void
    {
        $output->info('Unregistering the mimetype...');

        $this->unregisterForExistingFiles();
        $this->unregisterForNewFiles();

        $output->info('The mimetype was successfully unregistered.');
    }

    /**
     * Reset existing .drawio/.dwb files to the generic MIME type
     */
    private function unregisterForExistingFiles(): void
    {
        $mimeTypeId = $this->mimeTypeLoader->getId('application/octet-stream');

        $this->mimeTypeLoader->updateFilecache('drawio', $mimeTypeId);
        $this->mimeTypeLoader->updateFilecache('dwb', $mimeTypeId);
    }

    private function unregisterForNewFiles(): void
    {
        $configDir = \OC::$configDir;

        $this->removeFromFile($configDir . self::CUSTOM_MIMETYPEALIASES, [
            'application/x-drawio' => 'drawio',
            'application/x-drawio-wb' => 'dwb',
        ]);
        $this->removeFromFile($configDir . self::CUSTOM_MIMETYPEMAPPING, [
            'drawio' => ['application/x-drawio'],
            'dwb' => ['application/x-drawio-wb'],
        ]);
    }
}
