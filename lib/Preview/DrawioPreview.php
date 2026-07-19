<?php

declare(strict_types=1);

namespace OCA\Drawio\Preview;

use OCA\Drawio\AppConfig;
use OCA\Drawio\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IImage;
use OCP\Image;
use OCP\Preview\IProviderV2;
use Psr\Log\LoggerInterface;

class DrawioPreview implements IProviderV2
{
    /**
     * MIME types this provider generates previews for
     *
     * @var list<string>
     */
    public const CAPABILITIES = [
        'application/x-drawio',
        'application/x-drawio-wb',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly IAppData $appData,
        private readonly AppConfig $appConfig,
    ) {
    }

    /**
     * Regular expression matching every MIME type this provider handles
     */
    public static function getMimeTypeRegex(): string
    {
        $quoted = array_map(
            static fn (string $mimeType): string => str_replace('/', '\/', $mimeType),
            self::CAPABILITIES
        );

        return '/' . implode('|', $quoted) . '/';
    }

    public function getMimeType(): string
    {
        return self::getMimeTypeRegex();
    }

    public function isAvailable(FileInfo $file): bool
    {
        $preview = $this->getPreviewFile($file->getId());

        return $this->appConfig->GetPreviews() === 'yes'
            && $preview !== null
            && $preview->getMtime() >= $file->getMtime();
    }

    public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage
    {
        if ($this->appConfig->GetPreviews() !== 'yes') {
            return null;
        }

        $thumbnail = $this->getPreviewFile($file->getId());

        if ($thumbnail === null) {
            return null;
        }

        $image = new Image();
        $image->loadFromData($thumbnail->getContent());

        if (!$image->valid()) {
            return null;
        }

        $image->scaleDownToFit($maxX, $maxY);

        return $image;
    }

    private function getPreviewFile(int $fileId): ?ISimpleFile
    {
        try {
            return $this->appData->getFolder('previews')->getFile($fileId . '.png');
        } catch (NotFoundException) {
            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'message' => "Can't get preview file",
                'app' => Application::APP_ID,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
