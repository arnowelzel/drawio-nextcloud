<?php
namespace OCA\Drawio\Migration;

/**
 * NOTE: This class and its subclasses use \OC::$configDir, for which there is
 * no public OCP API. Registering custom MIME types via config/mimetypemapping.json
 * is the approach recommended by the Nextcloud documentation and shared by other
 * apps (Keeweb, Mind Map). These usages should be reviewed if Nextcloud provides
 * a public MIME type registration API (https://github.com/nextcloud/server/issues/9192).
 **/

use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IRepairStep;

abstract class MimeTypeMigration implements IRepairStep
{
    const CUSTOM_MIMETYPEMAPPING = 'mimetypemapping.json';
    const CUSTOM_MIMETYPEALIASES = 'mimetypealiases.json';

    public function __construct(protected IMimeTypeLoader $mimeTypeLoader)
    {
    }
}
