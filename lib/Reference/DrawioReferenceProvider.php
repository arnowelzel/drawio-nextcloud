<?php

declare(strict_types=1);

namespace OCA\Drawio\Reference;

use OCA\Drawio\AppInfo\Application;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;

class DrawioReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

    private const RICH_OBJECT_TYPE = 'drawio_diagram';

    public function __construct(
        private readonly IL10N $l10n,
        private readonly IURLGenerator $urlGenerator,
        private readonly IRootFolder $rootFolder,
        private readonly IUserSession $userSession,
        private readonly IShareManager $shareManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getId(): string {
        return 'drawio-diagram';
    }

    public function getTitle(): string {
        return $this->l10n->t('Diagrams');
    }

    public function getOrder(): int {
        return 10;
    }

    public function getIconUrl(): string {
        return $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
        );
    }

    public function getSupportedSearchProviderIds(): array {
        return ['files'];
    }

    public function matchReference(string $referenceText): bool {
        $baseUrl = $this->urlGenerator->getAbsoluteURL('/apps/drawio/edit');
        $baseUrlIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/drawio/edit');

        return str_starts_with($referenceText, $baseUrl)
            || str_starts_with($referenceText, $baseUrlIndex);
    }

    public function resolveReference(string $referenceText): ?IReference {
        if (!$this->matchReference($referenceText)) {
            return null;
        }

        $params = $this->parseUrlParams($referenceText);
        $fileId = $params['fileId'] ?? null;
        $shareToken = $params['shareToken'] ?? null;

        if ($fileId === null) {
            return null;
        }

        try {
            if (is_string($shareToken) && $shareToken !== '') {
                return $this->resolveWithShareToken($referenceText, (int)$fileId, $shareToken);
            }

            $user = $this->userSession->getUser();

            if ($user === null) {
                return null;
            }

            $file = $this->rootFolder->getUserFolder($user->getUID())->getFirstNodeById((int)$fileId);

            return $file === null ? null : $this->buildReference($referenceText, $file);
        } catch (\Exception $e) {
            $this->logger->debug('Could not resolve diagram reference: ' . $e->getMessage(), [
                'app' => Application::APP_ID,
            ]);

            return null;
        }
    }

    public function getCachePrefix(string $referenceId): string {
        $params = $this->parseUrlParams($referenceId);

        return ($params['fileId'] ?? '') . '-' . ($params['shareToken'] ?? '');
    }

    public function getCacheKey(string $referenceId): ?string {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUrlParams(string $url): array {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $params);

        return $params;
    }

    private function resolveWithShareToken(string $referenceText, int $fileId, string $shareToken): ?IReference {
        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (\Exception) {
            return null;
        }

        $node = $share->getNode();

        if ($node instanceof Folder) {
            $node = $node->getFirstNodeById($fileId);

            if ($node === null) {
                return null;
            }
        }

        return $this->buildReference($referenceText, $node);
    }

    private function buildReference(string $referenceText, Node $file): IReference {
        $reference = new Reference($referenceText);
        $reference->setTitle($file->getName());
        $reference->setDescription($this->l10n->t('Diagram'));

        $previewUrl = $this->urlGenerator->getAbsoluteURL(
            $this->urlGenerator->linkToRoute('core.Preview.getPreviewByFileId', [
                'fileId' => $file->getId(),
                'x' => 600,
                'y' => 400,
                'a' => true,
            ])
        );
        $reference->setImageUrl($previewUrl);

        $reference->setRichObject(self::RICH_OBJECT_TYPE, [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mime' => $file->getMimeType(),
            'previewUrl' => $previewUrl,
            'editUrl' => $referenceText,
        ]);

        return $reference;
    }
}
