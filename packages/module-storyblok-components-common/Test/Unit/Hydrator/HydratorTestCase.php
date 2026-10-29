<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Test\Unit\Hydrator;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Model\Block as Blok;
use WindAndKite\Storyblok\Model\BlockFactory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;
use WindAndKite\Storyblok\Service\StoryRequestService;
use WindAndKite\Storyblok\ViewModel\Asset;

/**
 * A real Storyblok Block holding a blok's fields, as FieldRenderer::createBlockInstance() makes before hydrating.
 */
abstract class HydratorTestCase extends TestCase
{
    protected function blockFor(array $fields): Block
    {
        $block = new Block(
            $this->createStub(BlockFactory::class),
            $this->createStub(FieldRendererInterface::class),
            $this->createStub(SerializerInterface::class),
            $this->createStub(StoryblokSessionManager::class),
            $this->createStub(StoryRepository::class),
            $this->createStub(Asset::class),
            $this->createStub(Config::class),
            $this->createStub(Context::class),
            $this->createStub(StoryRequestService::class),
        );

        return $block->setData('block', new Blok(['_uid' => 'u1', 'component' => 'x'] + $fields));
    }

    protected function storeManager(int $storeId = 1, int $websiteId = 1): StoreManagerInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return $storeManager;
    }
}
