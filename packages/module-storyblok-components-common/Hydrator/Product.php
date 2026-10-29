<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Hydrator;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Block\Block;

/**
 * Loads the catalog product for a "product" blok's SKU into the block's "product" data key.
 * Missing, disabled, invisible, other-website and (per config) out-of-stock products hydrate to null.
 */
class Product implements BlockHydratorInterface
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param StockConfigurationInterface $stockConfiguration
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockConfigurationInterface $stockConfiguration,
    ) {}

    /**
     * @param Block $blockInstance
     *
     * @return Block
     */
    public function populate(Block $blockInstance): Block
    {
        $sku = trim((string)$blockInstance->getBlock()->getData('sku'));

        $product = $sku !== '' ? $this->loadProduct($sku) : null;

        if ($product) {
            $blockInstance->addIdentities($product->getIdentities());
        }

        return $blockInstance->setData('product', $product);
    }

    /**
     * ponytail: one repository load per blok (the repository caches per request); a product_grid of N
     * SKUs costs N loads. Prefetch with a single collection in a product_grid hydrator if grids get large.
     *
     * @param string $sku
     *
     * @return CatalogProduct|null
     */
    private function loadProduct(string $sku): ?CatalogProduct
    {
        try {
            $store = $this->storeManager->getStore();
            /** @var CatalogProduct $product */
            $product = $this->productRepository->get($sku, false, $store->getId());
        } catch (NoSuchEntityException) {
            return null;
        }

        $isAvailable = (int)$product->getStatus() === Status::STATUS_ENABLED
            && $product->isVisibleInCatalog()
            && in_array((int)$store->getWebsiteId(), array_map('intval', $product->getWebsiteIds()), true)
            && ($product->isSalable() || $this->stockConfiguration->isShowOutOfStock($store->getId()));

        return $isAvailable ? $product : null;
    }
}
