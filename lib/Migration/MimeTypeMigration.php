<?php

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
    const CUSTOM_MIMETYPEMAPPING = 'mimetypemapping.json';
    const CUSTOM_MIMETYPEALIASES = 'mimetypealiases.json';

    protected $mimeTypeLoader;

    public function __construct(IMimeTypeLoader $mimeTypeLoader)
    {
        $this->mimeTypeLoader = $mimeTypeLoader;
    }
}
