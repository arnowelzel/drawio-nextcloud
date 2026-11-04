<?php
namespace OCA\Drawio\Migration;

use OCP\Migration\IOutput;

class RegisterMimeType extends MimeTypeMigration
{
    public function getName(): string
    {
        return 'Register MIME types for Diagramming';
    }

    private function registerForExistingFiles(): void
    {
        foreach (['drawio' => 'application/x-drawio', 'dwb' => 'application/x-drawio-wb'] as $ext => $mimeType) {
            // Updating the filecache requires a full table scan, so only do it when the
            // mime type is not registered yet. To force a re-scan, run:
            // occ maintenance:mimetype:update-db --repair-filecache
            if ($this->mimeTypeLoader->exists($mimeType)) {
                continue;
            }
            $mimeTypeId = $this->mimeTypeLoader->getId($mimeType);
            $this->mimeTypeLoader->updateFilecache($ext, $mimeTypeId);
        }
    }

    private function registerForNewFiles(): void
    {
        $configDir = \OC::$configDir;
        $mimetypealiasesFile = $configDir . self::CUSTOM_MIMETYPEALIASES;
        $mimetypemappingFile = $configDir . self::CUSTOM_MIMETYPEMAPPING;

        $this->appendToFile($mimetypealiasesFile, [
            'application/x-drawio' => 'drawio',
            'application/x-drawio-wb' => 'dwb'
        ]);
        $this->appendToFile($mimetypemappingFile, [
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

    private function appendToFile(string $filename, array $data): void
    {
        $obj = [];
        if (file_exists($filename)) {
            $content = file_get_contents($filename);
            $obj = json_decode($content, true);
        }
        foreach ($data as $key => $value) {
            $obj[$key] = $value;
        }
        file_put_contents($filename, json_encode($obj,  JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }
}
