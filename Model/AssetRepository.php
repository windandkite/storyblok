<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use WindAndKite\Storyblok\Api\AssetRepositoryInterface;
use WindAndKite\Storyblok\Api\Data\AssetInterface;

class AssetRepository implements AssetRepositoryInterface
{
    public function __construct(
        private readonly StoryblokClientWrapper $storyBlockClientWrapper,
        private readonly AssetFactory $assetFactory,
    ) {}

    public function getByFilename(
        string $filename,
    ): AssetInterface {
        try {
            $assetResponse = $this->storyBlockClientWrapper->getAssetByFilename($filename);
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
        $resultArray = [];
        $reflection = new \ReflectionClass($asset);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $propertyValue = $property->getValue($asset);

            if ($propertyValue instanceof \DateTimeImmutable) {
                $resultArray[$propertyName] = $propertyValue->format('Y-m-d H:i:s');
            } else {
                $resultArray[$propertyName] = $propertyValue;
            }
        }

        return $resultArray;
    }
}
