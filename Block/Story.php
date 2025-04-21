<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\View\Element\Template;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\Story as StoryblokStory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\ViewModel\Asset;

class Story extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly StoryRepository $storyRepository,
        private readonly Asset $assetViewModel,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getData($key = '', $index = null)
    {
        return parent::getData($key, $index) ?? $this->getStory()->getData($key, $index);
    }

    public function getStory(): StoryblokStory
    {
        $story = $this->getData('story');

        if (!$story && $slug = $this->getData('slug')) {
            $story = $this->storyRepository->getBySlug($slug);
            $this->setData('story', $story);
        }

        return $story;
    }

    public function getContent(): StoryblokBlock
    {
        return $this->getStory()->getContent();
    }

    public function getContentHtml(): string
    {
        $blockName = 'content-'
            . $this->getContent()->getComponent()
            . '-'
            . md5(json_encode($this->getContent()->getData()));
        $block = $this->getLayout()
            ->createBlock(Block::class, $blockName)
            ->setData('block', $this->getContent())
            ->setData('asset_view_model', $this->assetViewModel);

        return $block->toHtml();
    }

    public function getCacheKeyInfo(): array
    {
        return [
            ...parent::getCacheKeyInfo(),
            'storyblok_story',
            'storyblok_cv_' . $this->getStory()->getCacheVersion(),
            'storyblok_id_' . $this->getStory()->getId(),
            'storyblok_slug_' . $this->getStory()->getSlug(),
        ];
    }
}
