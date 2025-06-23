<?php

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\PageCache\Model\Cache\Type;
use Magento\Store\Model\StoreManagerInterface;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Domain\Value\Total;
use Storyblok\Api\Request\StoryRequest;
use Storyblok\Api\Response\StoriesResponse;
use Storyblok\Api\Response\StoryResponse;
use WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;
use WindAndKite\Storyblok\Scope\Config;
use Zend_Cache;

class StoryblokCacheService
{
    private const CACHE_IDENTIFIER = 'storyblok_';
    private const STORY_CACHE_PREFIX = 'story_';
    private const STORY_LIST_CACHE_PREFIX = 'list_';

    public function __construct(
        private CacheInterface $cache,
        private SerializerInterface $serializer,
        private StoreManagerInterface $storeManager,
        private RequestInterface $request,
        private Config $scopeConfig,
        private Type $fpc,
        private SearchCriteriaConverter $searchCriteriaConverter
    ) {}

    private function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    public function generateStoryCacheKey(
        string $identifier,
        ?StoryRequest $storyRequest = null
    ): string {
        if ($storyRequest) {
            $identifier .= '-' . md5($this->serializer->serialize($storyRequest->toArray()));
        }

        return self::CACHE_IDENTIFIER . self::STORY_CACHE_PREFIX . $identifier . '_' . $this->getStoreId();
    }

    public function generateStoryListCacheKey(
        StoriesSearchCriteriaInterface $searchCriteria,
        $identifier = ''
    ): string {
        [$storiesRequest, $additionalFilters] = $this->searchCriteriaConverter->convert($searchCriteria);
        $storiesData = array_merge($storiesRequest->toArray(), $additionalFilters);

        if (is_array($identifier)) {
            $identifier = md5($this->serializer->serialize($identifier));
        }

        $listIdentifier = $identifier . md5($this->serializer->serialize($storiesData));

        return self::CACHE_IDENTIFIER . self::STORY_LIST_CACHE_PREFIX . $listIdentifier . '_' . $this->getStoreId();
    }

    public function loadStoryResponse(
        string $cacheKey
    ): ?StoryResponse {
        if (!$this->isCacheEnabled()) {
            return null;
        }

        $cachedData = $this->cache->load($cacheKey);

        if ($cachedData) {
            return new StoryResponse($this->serializer->unserialize($cachedData));
        }

        return null;
    }

    public function saveStoryResponse(
        string $cacheKey,
        StoryResponse $storyResponse
    ): void {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $this->cache->save(
            $this->serializer->serialize((array)$storyResponse),
            $cacheKey,
            [
                'storyblok_story_id_' . $storyResponse->story['id'],
                'storyblok_story_slug_' . $storyResponse->story['slug'],
                'storyblok_cv_' . $storyResponse->cv,
            ]
        );
    }

    public function removeStoryResponse(
        string $cacheKey
    ): void {
        $this->cache->remove($cacheKey);
    }

    /**
     * Loads a list of story IDs from the cache.
     *
     * @param string $cacheKey
     * @return StoriesResponse|null
     */
    public function loadStoriesResponse(
        string $cacheKey
    ): ?StoriesResponse {
        if (!$this->isCacheEnabled()) {
            return null;
        }

        $cachedData = $this->cache->load($cacheKey);

        if ($cachedData) {
            $response = $this->serializer->unserialize($cachedData);

            $total = $response['total'] ?? null;
            $pagination = $response['pagination'] ?? null;

            if ($total === null || $pagination === null) {
                return null;
            }

            unset($response['total']);
            unset($response['pagination']);

            return new StoriesResponse(
                new Total($total['value']),
                new Pagination(...array_values($pagination)),
                $response,
            );
        }

        return null;
    }

    /**
     * Saves a list of story IDs to the cache.
     *
     * @param string $cacheKey
     * @param StoriesResponse $response
     *
     * @return void
     */
    public function saveStoriesResponse(
        string $cacheKey,
        StoriesResponse $response,
    ): void {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $cacheTags = ['storyblok_cv_' . $response->cv];

        foreach ($response->stories as $story) {
            $cacheTags[] = 'storyblok_story_id_' . $story['id'];
            $cacheTags[] = 'storyblok_story_slug_' . $story['slug'];
            $this->generateStoryCacheKey($story['slug']);
            $storyResponse = new StoryResponse([
                'story' => $story,
                'cv' => $response->cv ?? 0,
                'rels' => $response->rels ?? [],
                'links' => $response->links ?? [],
            ]);
            $this->saveStoryResponse($cacheKey, $storyResponse);
        }

        $this->cache->save(
            $this->serializer->serialize($response),
            $cacheKey,
            $cacheTags,
        );
    }

    public function removeStoryList(string $cacheKey): void
    {
        $this->cache->remove($cacheKey);
    }

    public function isCacheEnabled(): bool
    {
        return !$this->request->getParam('_storyblok') && !$this->scopeConfig->isDevModeEnabled();
    }

    public function cleanCacheByTags(
        array $tags = [],
    ): void {
        $this->fpc->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);

        foreach ($tags as $tag) {
            $this->cache->clean($tag);
        }
    }
}
