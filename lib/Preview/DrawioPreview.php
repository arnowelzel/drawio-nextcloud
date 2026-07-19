<?php
namespace OCA\Drawio\Preview;

use OCA\Drawio\AppConfig;
use OCA\Drawio\AppInfo\Application;
use OCP\Preview\IProviderV2;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IImage;
use OCP\Image;
use Psr\Log\LoggerInterface;

class DrawioPreview implements IProviderV2
{
    /**
     * Capabilities mimetype
     *
     * @var Array
     */
    public static $capabilities = [
        "application/x-drawio",
        "application/x-drawio-wb"
    ];

    public function __construct(
        private LoggerInterface $logger,
        private IAppData $appData,
        private AppConfig $appConfig)
    {
        $this->logger = $logger;
        $this->appData = $appData;
        $this->appConfig = $appConfig;
    }

    /**
     * Return mime type
     */
    public static function getMimeTypeRegex()
    {
        $mimeTypeRegex = "";
        foreach (self::$capabilities as $format) {
            if (!empty($mimeTypeRegex)) {
                $mimeTypeRegex = $mimeTypeRegex . "|";
            }
            $mimeTypeRegex = $mimeTypeRegex . str_replace("/", "\/", $format);
        }
        $mimeTypeRegex = "/" . $mimeTypeRegex . "/";

        return $mimeTypeRegex;
    }

    public function getMimeType(): string
    {
        $m = self::getMimeTypeRegex();
        return $m;
    }

    public function isAvailable(FileInfo $file): bool
    {
        $prevFile = $this->getPreviewFile($file->getId());
        return ($this->appConfig->GetPreviews() === 'yes') && $prevFile !== false &&
            $prevFile->getMtime() >= $file->getMtime();
    }

    public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage
    {
        $thumbnail = $this->getPreviewFile($file->getId());

        if ($this->appConfig->GetPreviews() === 'no' || $thumbnail === false) {
            return null;
        }

        $image = new Image();
        $image->loadFromData($thumbnail->getContent());

        if ($image->valid()) {
            $image->scaleDownToFit($maxX, $maxY);
            return $image;
        }

        return null;
    }

    private function getPreviewFile($fileId): \OCP\Files\SimpleFS\ISimpleFile|false
    {
        try {
            return $this->appData->getFolder('previews')->getFile($fileId . '.png');
        } catch (NotFoundException $e) {
            // ignore
            return false;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ["message" => "Can't get preview file", "app" => Application::APP_ID, 'exception' => $e]);
            return false;
        }
    }
}
