<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Test\Unit\Hydrator;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use WindAndKite\StoryblokComponentsCommon\Hydrator\ProductList;

class ProductListTest extends HydratorTestCase
{
    /**
     * @var array<int, array{0: string, 1: array}> Calls made on the collection, in order.
     */
    private array $calls = [];

    private array $skuIds = [];

    public function testSkusKeepTheTypedOrder(): void
    {
        $block = $this->hydrate(['source' => 'skus', 'skus' => " bench-dining\natlas-pouf, bed-walnut-cane\n\natlas-pouf ", 'limit' => '2']);

        $this->assertSame(['sku', ['in' => ['bench-dining', 'atlas-pouf']]], array_slice($this->call('addAttributeToFilter', 'sku'), 0, 2), 'trimmed, de-duplicated, limited before querying');
        $this->assertSame(["FIELD(e.sku, 'bench-dining', 'atlas-pouf')"], $this->call('order'), 'editor order kept');
        $this->assertSame([2], $this->call('setPageSize'));
        $this->assertCount(2, $block->getData('product_items'));
        $this->assertSame(['cat_p_1', 'cat_p_2'], $block->getIdentities());
    }

    public function testChosenSkusAreTaggedEvenWhenNotShown(): void
    {
        // atlas-pouf exists but is out of stock, so the collection leaves it out.
        $this->skuIds = ['bench-dining' => 1, 'atlas-pouf' => 9];
        $block = $this->hydrate(['source' => 'skus', 'skus' => 'bench-dining, atlas-pouf']);

        $this->assertSame(['cat_p_1', 'cat_p_9', 'cat_p_2'], $block->getIdentities());
    }

    public function testExplicitSortReplacesSkuOrder(): void
    {
        $this->hydrate(['source' => 'skus', 'skus' => 'a', 'sort' => 'price_asc']);

        $this->assertSame(['price', 'ASC'], $this->call('addAttributeToSort'));
        $this->assertNull($this->call('order'));
    }

    public function testCategoryUsesPositionAndCategoryTag(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getIsActive')->willReturn(true);

        $block = $this->hydrate(['source' => 'category', 'category_id' => '4'], $category);

        $this->assertSame([$category], $this->call('addCategoryFilter'));
        $this->assertSame(['position', 'ASC'], $this->call('addAttributeToSort'));
        $this->assertSame(['cat_c_p_4', 'cat_p_1', 'cat_p_2'], $block->getIdentities(), 'purged when the category\'s products change');
    }

    public function testNewestSortsByCreationDate(): void
    {
        $this->hydrate(['source' => 'newest']);

        $this->assertSame(['created_at', 'DESC'], $this->call('addAttributeToSort'));
        $this->assertSame([8], $this->call('setPageSize'), 'default limit');
    }

    public function testLimitIsClamped(): void
    {
        $this->hydrate(['source' => 'newest', 'limit' => '500']);
        $this->assertSame([48], $this->call('setPageSize'));
    }

    public function testNothingToQuery(): void
    {
        $inactive = $this->createStub(Category::class);
        $inactive->method('getIsActive')->willReturn(false);

        foreach ([
            'recently viewed is rendered client-side' => [['source' => 'recently_viewed'], null],
            'no SKUs' => [['source' => 'skus', 'skus' => " \n "], null],
            'no category' => [['source' => 'category'], null],
            'missing category' => [['source' => 'category', 'category_id' => '999'], false],
            'inactive category' => [['source' => 'category', 'category_id' => '5'], $inactive],
        ] as $case => [$fields, $category]) {
            $block = $this->hydrate($fields, $category);

            $this->assertSame([], $block->getData('product_items'), $case);
            $this->assertNull($this->call('setPageSize'), $case . ': no query');
        }
    }

    /**
     * @param array $fields
     * @param Category|false|null $category False: the category doesn't exist.
     *
     * @return \WindAndKite\Storyblok\Block\Block
     */
    private function hydrate(array $fields, Category|false|null $category = null): \WindAndKite\Storyblok\Block\Block
    {
        $this->calls = [];

        $products = [];

        foreach ([1, 2] as $id) {
            $product = $this->createStub(Product::class);
            $product->method('getIdentities')->willReturn(['cat_p_' . $id]);
            $products[] = $product;
        }

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quote')->willReturnCallback(static fn ($value) => implode(', ', array_map(static fn ($item) => "'" . $item . "'", (array)$value)));

        $select = $this->createStub(Select::class);
        $select->method('order')->willReturnCallback(function ($expression) use ($select) {
            $this->calls[] = ['order', [(string)$expression]];

            return $select;
        });

        $collection = $this->createStub(Collection::class);

        foreach (['setStoreId', 'addStoreFilter', 'addAttributeToSelect', 'addAttributeToFilter', 'setVisibility', 'addMinimalPrice', 'addFinalPrice', 'addTaxPercents', 'addUrlRewrite', 'addCategoryFilter', 'addAttributeToSort', 'setPageSize', 'setCurPage'] as $method) {
            $collection->method($method)->willReturnCallback(function (...$args) use ($method, $collection) {
                $this->calls[] = [$method, $args];

                return $collection;
            });
        }

        $collection->method('getSelect')->willReturn($select);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getItems')->willReturn($products);

        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $categories = $this->createStub(CategoryRepositoryInterface::class);

        if ($category === false) {
            $categories->method('get')->willThrowException(new NoSuchEntityException());
        } elseif ($category !== null) {
            $categories->method('get')->willReturn($category);
        }

        $visibility = $this->createStub(Visibility::class);
        $visibility->method('getVisibleInCatalogIds')->willReturn([2, 4]);
        $catalogConfig = $this->createStub(CatalogConfig::class);
        $catalogConfig->method('getProductAttributes')->willReturn(['name']);

        $productResource = $this->createStub(ProductResource::class);
        $productResource->method('getProductsIdsBySkus')->willReturn($this->skuIds);

        $hydrator = new ProductList($collectionFactory, $categories, $this->storeManager(), $visibility, $catalogConfig, $this->createStub(StockHelper::class), $productResource);

        return $hydrator->populate($this->blockFor($fields));
    }

    /**
     * Arguments of the last call to $method (optionally whose first argument is $first), or null.
     */
    private function call(string $method, ?string $first = null): ?array
    {
        foreach (array_reverse($this->calls) as [$name, $args]) {
            if ($name === $method && ($first === null || ($args[0] ?? null) === $first)) {
                return $args;
            }
        }

        return null;
    }
}
