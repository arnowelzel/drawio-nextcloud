<?php

declare(strict_types=1);

namespace OCA\Drawio\Migration;

use OCP\Migration\IOutput;

class RegisterMimeType extends MimeTypeMigration
{
    public function getName(): string
    {
        return 'Register MIME types for Diagramming';
    }

    public function run(IOutput $output): void
    {
        $output->info('Registering the mimetype...');

        $this->registerForExistingFiles();
        $this->registerForNewFiles();

        $output->info('The mimetype was successfully registered.');
    }

    /**
     * Give existing .drawio/.dwb files the correct MIME type
     */
    private function registerForExistingFiles(): void
    {
        $this->mimeTypeLoader->updateFilecache('drawio', $this->mimeTypeLoader->getId('application/x-drawio'));
        $this->mimeTypeLoader->updateFilecache('dwb', $this->mimeTypeLoader->getId('application/x-drawio-wb'));
    }

    /**
     * Make the server detect the MIME types of newly created files
     */
    private function registerForNewFiles(): void
    {
        $configDir = \OC::$configDir;

        $this->appendToFile($configDir . self::CUSTOM_MIMETYPEALIASES, [
            'application/x-drawio' => 'drawio',
            'application/x-drawio-wb' => 'dwb',
        ]);
        $this->appendToFile($configDir . self::CUSTOM_MIMETYPEMAPPING, [
            'drawio' => ['application/x-drawio'],
            'dwb' => ['application/x-drawio-wb'],
        ]);
    }
}
