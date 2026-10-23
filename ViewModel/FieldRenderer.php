<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Api\FieldRendererInterface;

/**
 * Exposes the field renderer to templates so child bloks can be rendered one at a time,
 * e.g. to wrap each item of a blocks field in an <li>.
 */
class FieldRenderer implements ArgumentInterface
{
    /**
     * @param FieldRendererInterface $fieldRenderer
     */
    public function __construct(
        private readonly FieldRendererInterface $fieldRenderer,
    ) {}

    /**
     * @param mixed $fieldValue A single blok, an array of bloks or a rich text document.
     * @param StoryInterface|null $story Pass $block->getStory() so children keep the story context.
     *
     * @return string
     */
    public function renderField(
        mixed $fieldValue,
        ?StoryInterface $story = null,
    ): string {
        return $this->fieldRenderer->renderField($fieldValue, $story);
    }

    /**
     * Create the hydrated block for a single child blok without rendering it, e.g. to read data a
     * hydrator added (such as a product) before calling toHtml().
     *
     * @param array $blok
     * @param StoryInterface|null $story
     *
     * @return Block
     */
    public function createBlock(
        array $blok,
        ?StoryInterface $story = null,
    ): Block {
        return $this->fieldRenderer->createBlockInstance($blok, $story);
    }
}
