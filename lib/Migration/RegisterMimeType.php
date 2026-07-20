<?php
namespace OCA\Drawio\Migration;

use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;

class RegisterMimeType extends MimeTypeMigration
{
    public function __construct(
        IMimeTypeLoader $mimeTypeLoader
    )
    {
        parent::__construct($mimeTypeLoader);
    }

    public function getName(): string
    {
        return 'Register MIME types for Diagramming';
    }

    private function registerForExistingFiles(): void
    {
        $mimeTypeId = $this->mimeTypeLoader->getId('application/x-drawio');
        $this->mimeTypeLoader->updateFilecache('drawio', $mimeTypeId);

        $mimeTypeId = $this->mimeTypeLoader->getId('application/x-drawio-wb');
        $this->mimeTypeLoader->updateFilecache('dwb', $mimeTypeId);
    }

    private function registerForNewFiles(): void
    {
        // Only the extension to MIME type mapping is registered. The icon
        // aliases are deliberately not written: the app does not ship icons into
        // the Nextcloud core anymore, so they would have no effect other than
        // breaking the code integrity check when core/js/mimetypelist.js is
        // regenerated.
        $this->appendToFile(\OC::$configDir . self::CUSTOM_MIMETYPEMAPPING, [
            'drawio' => ['application/x-drawio'],
            'dwb' => ['application/x-drawio-wb']
        ]);
    }

    public function run(IOutput $output): void
    {
        $output->info('Registering the mimetype...');

        // Register the mime type for existing files
        $this->registerForExistingFiles();

        // Register the mime type for new files
        $this->registerForNewFiles();

        $output->info('The mimetype was successfully registered.');
    }
}
