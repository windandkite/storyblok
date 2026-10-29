<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Model;

use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Storyblok\Api\Response\StoryResponse;
use Storyblok\Api\StoriesApi;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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
            // Typed so a UUID can never share a cache entry with a slug or numeric ID.
            ->with(self::UUID, null, 'uuid')
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

        $result = $this->repository($cache, $client, $this->createStub(LoggerInterface::class), $storyFactory)
            ->getByUuid(self::UUID);

        $this->assertSame(5, $result->getData('id'));
        $this->assertSame(123, $result->getData('cache_version') ?? $result->getData(Story::KEY_CACHE_VERSION));
    }

    public function testA404IsCachedAndNotLogged(): void
    {
        $cache = $this->cache(false);
        $cache->expects($this->once())->method('saveStoryNotFound')->with('key');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->expectException(NoSuchEntityException::class);
        $this->repository($cache, $this->apiThrowing(404), $logger)->getBySlug('missing');
    }

    public function testACached404SkipsTheApi(): void
    {
        $client = $this->createMock(StoryblokClientWrapper::class);
        $client->expects($this->never())->method('getStoriesApi');

        $cache = $this->cache(true);
        $cache->expects($this->never())->method('saveStoryNotFound');

        $this->expectException(NoSuchEntityException::class);
        $this->repository($cache, $client, $this->createStub(LoggerInterface::class))->getById(5);
    }

    public function testOtherErrorsAreLoggedAndNotCached(): void
    {
        $cache = $this->cache(false);
        $cache->expects($this->never())->method('saveStoryNotFound');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->expectException(NoSuchEntityException::class);
        $this->repository($cache, $this->apiThrowing(500), $logger)->getBySlug('broken');
    }

    public function testCacheErrorsAreLoggedAndStillReportNotFound(): void
    {
        $cache = $this->createMock(StoryblokCacheService::class);
        $cache->method('generateStoryCacheKey')->willReturn('key');
        $cache->expects($this->once())->method('loadStoryResponse')
            ->willThrowException(new \InvalidArgumentException('Unable to unserialize value.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Unable to unserialize value.');

        $this->expectException(NoSuchEntityException::class);
        $this->repository($cache, $this->createStub(StoryblokClientWrapper::class), $logger)->getBySlug('corrupt');
    }

    private function cache(bool $isNotFound): StoryblokCacheService
    {
        $cache = $this->createMock(StoryblokCacheService::class);
        $cache->method('generateStoryCacheKey')->willReturn('key');
        $cache->method('loadStoryResponse')->willReturn(null);
        $cache->method('isStoryNotFound')->willReturn($isNotFound);

        return $cache;
    }

    private function apiThrowing(int $statusCode): StoryblokClientWrapper
    {
        $http = new MockHttpClient(new MockResponse('{}', ['http_code' => $statusCode]));
        $storyblokClient = new \Storyblok\Api\StoryblokClient('https://api.storyblok.com', 'token');
        (new \ReflectionProperty($storyblokClient, 'client'))->setValue($storyblokClient, $http);

        $client = $this->createStub(StoryblokClientWrapper::class);
        $client->method('getStoriesApi')->willReturn(new StoriesApi($storyblokClient));

        return $client;
    }

    private function repository(
        StoryblokCacheService $cache,
        StoryblokClientWrapper $client,
        LoggerInterface $logger,
        ?StoryFactory $storyFactory = null,
    ): StoryRepository {
        return new StoryRepository(
            $client,
            $storyFactory ?? $this->createStub(StoryFactory::class),
            $logger,
            $this->createStub(SearchResultsInterfaceFactory::class),
            $this->createStub(SearchCriteriaConverter::class),
            $cache,
        );
    }
}
