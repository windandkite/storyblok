<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Domain\Value\Total;
use Storyblok\Api\Response\StoriesResponse;
use WindAndKite\Storyblok\Service\SearchCriteriaConverter;
use WindAndKite\Storyblok\Service\StoryblokCacheService;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;

class StoryblokCacheServiceTest extends TestCase
{
    public function testDraftsAreNeverCached(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('load');

        $this->assertNull($this->service($cache, isDraft: true)->loadStoryResponse('key'));
        $this->assertTrue($this->service($this->createStub(CacheInterface::class))->isCacheEnabled());
    }

    public function testListsAreSavedOnceAndClearedByPublishesAndRelatedStories(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('save')->with(
            $this->anything(),
            'list-key',
            $this->callback(static fn (array $tags) => !array_diff([
                StoryblokCacheService::LISTS_TAG,
                'storyblok_story_id_1',
                'storyblok_story_id_9',
                'storyblok_story_id_7',
                // The full slug, as the webhook sends.
                'storyblok_story_slug_blog/a',
            ], $tags))
        );

        $this->service($cache)->saveStoriesResponse('list-key', new StoriesResponse(new Total(1), new Pagination(), [
            'stories' => [['id' => 1, 'slug' => 'a', 'full_slug' => 'blog/a']],
            'cv' => 5,
            'rels' => [['id' => 9]],
            'links' => [['id' => 7]],
        ]));
    }

    public function testPagesArePurgedByTagNotFlushed(): void
    {
        $events = $this->createMock(EventManager::class);
        $events->expects($this->once())->method('dispatch')->with(
            'clean_cache_by_tags',
            $this->callback(static fn (array $data) => $data['object'] instanceof IdentityInterface
                && $data['object']->getIdentities() === ['storyblok_story_id_1'])
        );
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clean')->with(['storyblok_story_id_1']);

        $this->service($cache, events: $events)->cleanCacheByTags(['storyblok_story_id_1']);
    }

    private function service(CacheInterface $cache, bool $isDraft = false, ?EventManager $events = null): StoryblokCacheService
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $session = $this->createStub(StoryblokSessionManager::class);
        $session->method('isDraftRequest')->willReturn($isDraft);

        return new StoryblokCacheService(
            $cache,
            new Json(),
            $storeManager,
            $this->createStub(SearchCriteriaConverter::class),
            $session,
            $events ?? $this->createStub(EventManager::class),
        );
    }
}
