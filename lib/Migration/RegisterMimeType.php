<?php
namespace OCA\Drawio\Migration;

use OCA\Drawio\AppInfo\Application;
use OCP\Files\IMimeTypeLoader;
use OCP\IAppConfig;
use OCP\Migration\IOutput;

class RegisterMimeType extends MimeTypeMigration
{
    // Set once the leftovers of app versions up to 4.2.x have been removed, so
    // that an administrator who deliberately restores the core icons and
    // aliases afterwards keeps them.
    const CLEANUP_FLAG = 'LegacyCoreCleanupDone';

    public function __construct(
        IMimeTypeLoader $mimeTypeLoader,
        private IAppConfig $appConfig
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

    /**
     * Undo what app versions up to 4.2.x changed outside of this app, which
     * makes "occ integrity:check-core" report EXTRA_FILE for the icons. Runs
     * once per instance.
     *
     * core/js/mimetypelist.js cannot be repaired from here: its original
     * content is only known to the Nextcloud release, so restoring it stays a
     * manual step for the administrator.
     */
    private function cleanUpLegacyCoreChanges(IOutput $output): void
    {
        if ($this->appConfig->getValueString(Application::APP_ID, self::CLEANUP_FLAG) === 'yes') {
            return;
        }

        $this->removeLegacyCoreIcons($output);
        $this->removeFromFile(\OC::$configDir . self::CUSTOM_MIMETYPEALIASES, self::LEGACY_ALIASES);

        $this->appConfig->setValueString(Application::APP_ID, self::CLEANUP_FLAG, 'yes');
    }

    public function run(IOutput $output): void
    {
        $output->info('Registering the mimetype...');

        // Register the mime type for existing files
        $this->registerForExistingFiles();

        // Register the mime type for new files
        $this->registerForNewFiles();

        // Clean up what older versions changed in the Nextcloud core
        $this->cleanUpLegacyCoreChanges($output);

        $output->info('The mimetype was successfully registered.');
    }
}
