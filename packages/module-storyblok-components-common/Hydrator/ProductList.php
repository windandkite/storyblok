<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Hydrator;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Block\Block;

/**
 * Loads the products for a "product_list" blok with a single collection query into its "product_items"
 * data key, and adds the product / category cache tags so the page is purged when they change.
 *
 * Sources: category (by ID), skus (one per line, editor order kept), newest. "recently_viewed" is
 * per-visitor and rendered client-side by Hyvä, so it isn't hydrated here.
 */
class ProductList implements BlockHydratorInterface
{
    public const SOURCE_CATEGORY = 'category';
    public const SOURCE_SKUS = 'skus';
    public const SOURCE_NEWEST = 'newest';
    public const SOURCE_RECENTLY_VIEWED = 'recently_viewed';

    private const MAX_LIMIT = 48;

    /**
     * @param CollectionFactory $collectionFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param Visibility $visibility
     * @param CatalogConfig $catalogConfig
     * @param StockHelper $stockHelper
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility,
        private readonly CatalogConfig $catalogConfig,
        private readonly StockHelper $stockHelper,
        private readonly ProductResource $productResource,
    ) {}

    /**
     * @param Block $blockInstance
     *
     * @return Block
     */
    public function populate(Block $blockInstance): Block
    {
        $blok = $blockInstance->getBlock();
        $source = (string)$blok->getData('source') ?: self::SOURCE_CATEGORY;
        $limit = max(1, min(self::MAX_LIMIT, (int)($blok->getData('limit') ?: 8)));

        $skus = $source === self::SOURCE_SKUS ? $this->parseSkus((string)$blok->getData('skus'), $limit) : [];

        $collection = match ($source) {
            self::SOURCE_CATEGORY => $this->forCategory($blockInstance, (int)$blok->getData('category_id')),
            self::SOURCE_SKUS => $skus ? $this->createCollection()->addAttributeToFilter('sku', ['in' => $skus]) : null,
            // ponytail: tagged with the listed products only, so a newly created product appears once the
            // page cache expires or a listed product changes. Add a broader tag if that's too slow.
            self::SOURCE_NEWEST => $this->createCollection(),
            default => null,
        };

        // Chosen products that can't be shown are tagged too, so enabling or restocking them refreshes the page.
        foreach ($skus ? $this->productResource->getProductsIdsBySkus($skus) : [] as $id) {
            $blockInstance->addIdentities([Product::CACHE_TAG . '_' . $id]);
        }

        if (!$collection) {
            return $blockInstance->setData('product_items', []);
        }

        $this->applySort($collection, (string)$blok->getData('sort'), $source, $skus);
        $collection->setPageSize($limit)->setCurPage(1);

        /** @var Product[] $products */
        $products = array_values($collection->getItems());

        foreach ($products as $product) {
            $blockInstance->addIdentities($product->getIdentities());
        }

        return $blockInstance->setData('product_items', $products);
    }

    /**
     * @return Collection
     */
    private function createCollection(): Collection
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $collection = $this->collectionFactory->create();

        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite();

        // Respects "Display Out of Stock Products".
        $this->stockHelper->addIsInStockFilterToCollection($collection);

        return $collection;
    }

    /**
     * @param Block $blockInstance
     * @param int $categoryId
     *
     * @return Collection|null
     */
    private function forCategory(Block $blockInstance, int $categoryId): ?Collection
    {
        if (!$categoryId) {
            return null;
        }

        try {
            $category = $this->categoryRepository->get($categoryId, $this->storeManager->getStore()->getId());
        } catch (NoSuchEntityException) {
            return null;
        }

        if (!$category->getIsActive()) {
            return null;
        }

        // cat_c_p_{id} is cleaned when the category's product assignments or positions change.
        $blockInstance->addIdentities([Product::CACHE_PRODUCT_CATEGORY_TAG . '_' . $categoryId]);

        return $this->createCollection()->addCategoryFilter($category);
    }

    /**
     * @param string $skus
     * @param int $limit
     *
     * @return string[]
     */
    private function parseSkus(string $skus, int $limit): array
    {
        $parsed = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $skus) ?: []), 'strlen');

        return array_slice(array_values(array_unique($parsed)), 0, $limit);
    }

    /**
     * "position" keeps the source's natural order: category position, the editor's SKU order, or newest first.
     *
     * @param Collection $collection
     * @param string $sort
     * @param string $source
     * @param string[] $skus
     *
     * @return void
     */
    private function applySort(Collection $collection, string $sort, string $source, array $skus): void
    {
        match (true) {
            $sort === 'newest', $source === self::SOURCE_NEWEST && !in_array($sort, ['price_asc', 'price_desc', 'name'], true)
                => $collection->addAttributeToSort('created_at', Collection::SORT_ORDER_DESC),
            $sort === 'price_asc' => $collection->addAttributeToSort('price', Collection::SORT_ORDER_ASC),
            $sort === 'price_desc' => $collection->addAttributeToSort('price', Collection::SORT_ORDER_DESC),
            $sort === 'name' => $collection->addAttributeToSort('name', Collection::SORT_ORDER_ASC),
            $source === self::SOURCE_CATEGORY => $collection->addAttributeToSort('position', Collection::SORT_ORDER_ASC),
            $source === self::SOURCE_SKUS => $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.sku, ' . $collection->getConnection()->quote($skus) . ')')
            ),
            default => null,
        };
    }
}
