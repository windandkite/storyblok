<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Model;

use Magento\Framework\Api\SearchResultsInterfaceFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Storyblok\Api\Response\StoryResponse;
use WindAndKite\Storyblok\Model\Story;
use WindAndKite\Storyblok\Model\StoryblokClientWrapper;
use WindAndKite\Storyblok\Model\StoryFactory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Service\SearchCriteriaConverter;
use WindAndKite\Storyblok\Service\StoryblokCacheService;

class StoryRepositoryTest extends TestCase
{
    private const UUID = '11111111-2222-3333-4444-555555555555';

    public function testCachedUuidLookupSkipsTheApi(): void
    {
        $cache = $this->createMock(StoryblokCacheService::class);
        $cache->expects($this->once())
            ->method('generateStoryCacheKey')
            // Namespaced so a UUID can never share a cache entry with a slug or numeric ID.
            ->with('uuid_' . self::UUID, null)
            ->willReturn('key');
        $cache->method('loadStoryResponse')->with('key')->willReturn(
            new StoryResponse(['story' => ['id' => 5, 'uuid' => self::UUID, 'slug' => 'usp'], 'cv' => 123, 'links' => []])
        );
        $cache->expects($this->never())->method('saveStoryResponse');

        $client = $this->createMock(StoryblokClientWrapper::class);
        $client->expects($this->never())->method('getStoriesApi');

        // A real Story (only setData/getData are used), built without its factory dependencies.
        $story = (new \ReflectionClass(Story::class))->newInstanceWithoutConstructor();
        $storyFactory = $this->createStub(StoryFactory::class);
        $storyFactory->method('create')->willReturn($story);

        $repository = new StoryRepository(
            $client,
            $storyFactory,
            $this->createStub(LoggerInterface::class),
            $this->createStub(SearchResultsInterfaceFactory::class),
            $this->createStub(SearchCriteriaConverter::class),
            $cache,
        );

        $result = $repository->getByUuid(self::UUID);

        $this->assertSame(5, $result->getData('id'));
        $this->assertSame(123, $result->getData('cache_version') ?? $result->getData(Story::KEY_CACHE_VERSION));
    }
}
