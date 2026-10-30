<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Test\Unit\Hydrator;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use WindAndKite\StoryblokComponentsCommon\Hydrator\Product;

class ProductTest extends HydratorTestCase
{
    public function testAvailableProductIsLoadedAndTagged(): void
    {
        $product = $this->product();
        $block = $this->hydrator($product)->populate($this->blockFor(['sku' => ' atlas-pouf ']));

        $this->assertSame($product, $block->getData('product'));
        $this->assertSame(['cat_p_7'], $block->getIdentities(), 'the page is purged when the product changes');
    }

    public function testUnavailableProductsHydrateToNull(): void
    {
        $cases = [
            'disabled' => $this->product(status: Status::STATUS_DISABLED),
            'not visible in catalog' => $this->product(visible: false),
            'other website' => $this->product(websites: [2]),
            'out of stock, hidden by config' => $this->product(salable: false),
        ];

        foreach ($cases as $case => $product) {
            $block = $this->hydrator($product)->populate($this->blockFor(['sku' => 'atlas-pouf']));

            $this->assertNull($block->getData('product'), $case);
            $this->assertSame(['cat_p_7'], $block->getIdentities(), $case . ': tagged, so enabling or restocking it purges the page');
        }
    }

    public function testOutOfStockShownWhenConfigured(): void
    {
        $product = $this->product(salable: false);

        $this->assertSame($product, $this->hydrator($product, showOutOfStock: true)->populate($this->blockFor(['sku' => 'x']))->getData('product'));
    }

    public function testMissingOrEmptySku(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        $hydrator = new Product($repository, $this->storeManager(), $this->createStub(StockConfigurationInterface::class));

        $this->assertNull($hydrator->populate($this->blockFor(['sku' => 'does-not-exist']))->getData('product'));

        $repository->expects($this->never())->method('getById');
        $this->assertNull($hydrator->populate($this->blockFor(['sku' => '  ']))->getData('product'), 'empty SKU: no lookup');
    }

    private function hydrator(CatalogProduct $product, bool $showOutOfStock = false): Product
    {
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('get')->willReturn($product);
        $stock = $this->createStub(StockConfigurationInterface::class);
        $stock->method('isShowOutOfStock')->willReturn($showOutOfStock);

        return new Product($repository, $this->storeManager(), $stock);
    }

    private function product(
        int $status = Status::STATUS_ENABLED,
        bool $visible = true,
        array $websites = [1],
        bool $salable = true,
    ): CatalogProduct {
        $product = $this->createStub(CatalogProduct::class);
        $product->method('getStatus')->willReturn($status);
        $product->method('isVisibleInCatalog')->willReturn($visible);
        $product->method('getWebsiteIds')->willReturn($websites);
        $product->method('isSalable')->willReturn($salable);
        $product->method('getIdentities')->willReturn(['cat_p_7']);

        return $product;
    }
}
