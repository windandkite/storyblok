<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Model;

use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Storyblok\Api\StoriesApi;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use WindAndKite\Storyblok\Exception\StoryblokUnavailableException;
use WindAndKite\Storyblok\Model\StoryblokClientWrapper;
use WindAndKite\Storyblok\Model\StoryFactory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Service\SearchCriteriaConverter;
use WindAndKite\Storyblok\Service\PageCacheability;
use WindAndKite\Storyblok\Service\StoryblokCacheService;

class StoryRepositoryTest extends TestCase
{
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

    public function testApiFailuresAreAnOutageNotA404(): void
    {
        $cache = $this->cache(false);
        $cache->expects($this->never())->method('saveStoryNotFound');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $pageCacheability = $this->createMock(PageCacheability::class);
        $pageCacheability->expects($this->once())->method('markUncacheable');

        $this->expectException(StoryblokUnavailableException::class);
        $this->repository($cache, $this->apiThrowing(500), $logger, $pageCacheability)->getBySlug('broken');
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
        ?PageCacheability $pageCacheability = null,
    ): StoryRepository {
        return new StoryRepository(
            $client,
            $this->createStub(StoryFactory::class),
            $logger,
            $this->createStub(SearchResultsInterfaceFactory::class),
            $this->createStub(SearchCriteriaConverter::class),
            $cache,
            $pageCacheability ?? $this->createStub(PageCacheability::class),
        );
    }
}
