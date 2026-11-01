<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Service\StoryblokCacheService;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\BlockHydrationManager;
use WindAndKite\Storyblok\Service\StoryRequestService;
use WindAndKite\Storyblok\ViewModel\Asset;

class Story extends AbstractStoryblok implements IdentityInterface
{
    protected const TEMPLATE_DIR = 'story';

    private readonly BlockHydrationManager $blockHydrationManager;

    /**
     * @param StoryRepository $storyRepository
     * @param Asset $assetViewModel
     * @param Config $scopeConfig
     * @param StoryRequestService $storyRequestService
     * @param Template\Context $context
     * @param array $data
     * @param BlockHydrationManager|null $blockHydrationManager Optional, so subclasses written before it still work.
     */
    public function __construct(
        StoryRepository $storyRepository,
        Asset $assetViewModel,
        Config $scopeConfig,
        StoryRequestService $storyRequestService,
        Template\Context $context,
        array $data = [],
        ?BlockHydrationManager $blockHydrationManager = null,
    ) {
        parent::__construct($storyRepository, $assetViewModel, $scopeConfig, $storyRequestService, $context, $data);
        $this->blockHydrationManager = $blockHydrationManager
            ?? ObjectManager::getInstance()->get(BlockHydrationManager::class);
    }

    public function getData($key = '', $index = null)
    {
        if ($key === 'story') {
            return parent::getData($key, $index);
        }

        return parent::getData($key, $index) ?? $this->getData('story')?->getData($key, $index);
    }

    public function getContent(): ?StoryblokBlock
    {
        return $this->getStory()?->getContent();
    }

    public function getContentHtml(
        array $data = [],
    ): string {
        $story = $this->getStory();
        $content = $this->getContent();

        if (!$story || !$content) {
            return '';
        }

        $blockName = 'content-'
            . $this->getContent()->getComponent()
            . '-'
            . md5(json_encode($story->getData()));

        $data['block'] = $content;
        $data['story'] = $story;

        $block = $this->getLayout()
            ->createBlock(Block::class, $blockName)
            ->setData($data);

        // The content type is hydrated like the bloks inside it (FieldRenderer::createBlockInstance()).
        foreach ($this->blockHydrationManager->getForComponent((string)$content->getComponent()) as $hydrator) {
            $block = $hydrator->populate($block);
        }

        return $block->toHtml();
    }

    public function getIdentities(): array
    {
        if (!$this->getStory()) {
            // Not published yet: refresh the page when it is.
            return [StoryblokCacheService::NOT_FOUND_TAG];
        }

        return [
            'storyblok_story',
            'storyblok_cv_' . $this->getStory()->getCacheVersion(),
            'storyblok_story_id_' . $this->getStory()->getId(),
            'storyblok_story_slug_' . $this->getStory()->getFullSlug(),
            // Resolved relations and links, so publishing one of those stories refreshes this page too.
            ...StoryblokCacheService::getRelatedStoryTags(
                $this->getStory()->getData(StoryInterface::KEY_RELS) ?? [],
                $this->getStory()->getData(StoryInterface::KEY_LINKS) ?? [],
            ),
        ];
    }

    public function getComponent(): ?string
    {
        return $this->getContent()?->getComponent();
    }
}
