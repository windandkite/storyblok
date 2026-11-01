<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\View\LayoutInterface;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Block\Story;

class StoryRenderer
{
    public function __construct(
        private LayoutInterface $layout,
    ) {}

    /**
     * @param StoryInterface $story
     *
     * @return string
     */
    public function renderStory(
        StoryInterface $story,
    ): string {
        $block = $this->layout->createBlock(
            Story::class,
            $story->getUuid(),
            [
                'data' => [
                    'story' => $story,
                ],
            ]
        );
        $block->setStory($story);

        return $block->toHtml();
    }

    /**
     * @param array $stories
     *
     * @return string
     */
    public function renderStories(
        array $stories,
    ): string {
        $result = '';

        foreach ($stories as $story) {
            if (!$story instanceof StoryInterface) {
                continue;
            }

            $result .= $this->renderStory($story);
        }

        return $result;
    }
}
