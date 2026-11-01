<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use WindAndKite\Storyblok\Api\AssetRepositoryInterface;
use WindAndKite\Storyblok\Api\Data\AssetInterface;

class AssetRepository implements AssetRepositoryInterface
{
    public function __construct(
        private StoryblokClientWrapper $storyBlockClientWrapper,
        private AssetFactory $assetFactory,
    ) {}

    public function getByFilename(
        string $filename,
    ): AssetInterface {
        try {
            $assetResponse = $this->storyBlockClientWrapper->getAssetsApi()->get($filename);
            $asset = $this->assetFactory->create();
            $asset->setData($this->convertAssetResponseToArray($assetResponse->asset));

            return $asset;
        } catch (\Exception $e) {
            throw new NoSuchEntityException(
                __('Unable to retrieve Storyblok asset (%1): %2', $filename, $e->getMessage())
            );
        }
    }

    private function convertAssetResponseToArray(
        \Storyblok\Api\Domain\Value\Asset $asset,
    ): array {
        return [
            AssetInterface::KEY_FILENAME => $asset->filename,
            AssetInterface::KEY_CREATED_AT => $asset->createdAt->format('Y-m-d H:i:s'),
            AssetInterface::KEY_UPDATED_AT => $asset->updatedAt->format('Y-m-d H:i:s'),
            AssetInterface::KEY_EXPIRE_AT => $asset->expiresAt?->format('Y-m-d H:i:s'),
            AssetInterface::KEY_CONTENT_LENGTH => $asset->contentLength,
            AssetInterface::KEY_SIGNED_URL => $asset->signedUrl,
            AssetInterface::KEY_CONTENT_TYPE => $asset->contentType,
        ];
    }
}
