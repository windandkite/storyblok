<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Hydrator;

use Magento\Framework\Exception\NoSuchEntityException;
use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Service\StoryblokCacheService;
use WindAndKite\Storyblok\Service\StoryRequestService;

/**
 * Loads the story chosen in a "reference" blok (Storyblok "Stories" option field, stored as a UUID)
 * into the "referenced_story" data key, and tags the page with it so publishing that story purges it.
 */
class Reference implements BlockHydratorInterface
{
    /**
     * @param StoryRepositoryInterface $storyRepository
     * @param StoryRequestService $storyRequestService
     */
    public function __construct(
        private readonly StoryRepositoryInterface $storyRepository,
        private readonly StoryRequestService $storyRequestService,
    ) {}

    /**
     * @param Block $blockInstance
     *
     * @return Block
     */
    public function populate(Block $blockInstance): Block
    {
        $uuid = trim((string)$blockInstance->getBlock()->getData('story'));

        if (!preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/i', $uuid)) {
            return $blockInstance->setData('referenced_story', null);
        }

        try {
            // Same version (draft in the visual editor) and language as the story being rendered. Its own language
            // first: Visual Editor updates don't carry the editor's language in the request.
            $parent = $blockInstance->getData('story');
            $story = $this->storyRepository->getByUuid($uuid, $this->storyRequestService->getStoryRequest(array_filter([
                'language' => $parent instanceof StoryInterface ? $parent->getData('lang') : null,
            ])));
        } catch (NoSuchEntityException) {
            // Cleaned when any story is published, so the page picks up the story once it exists.
            return $blockInstance
                ->addIdentities([StoryblokCacheService::NOT_FOUND_TAG])
                ->setData('referenced_story', null);
        }

        return $blockInstance
            ->addIdentities(['storyblok_story_id_' . $story->getId()])
            ->setData('referenced_story', $story);
    }
}
