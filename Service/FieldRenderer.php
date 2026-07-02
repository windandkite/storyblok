<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\View\LayoutInterface;
use Psr\Log\LoggerInterface;
use Storyblok\Tiptap\Extension\Storyblok as StoryblokTipTapExtension;
use Tiptap\Editor;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Model\BlockFactory;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Block\Block;

/**
 * Class StoryblokFieldRenderer
 *
 * This service handles the rendering of Storyblok fields.
 * It focuses on rendering blocks and rich text, leaving the rendering
 * of other field types to the template developers.
 */
class FieldRenderer implements FieldRendererInterface
{
    /**
     * @param LoggerInterface $logger
     * @param LayoutInterface $layout
     * @param BlockFactory $blockFactory
     * @param BlockHydrationManager $blockHydrationManager
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LayoutInterface $layout,
        private readonly BlockFactory $blockFactory,
        private readonly BlockHydrationManager $blockHydrationManager,
    ) {}

    /**
     * Renders a Storyblok field based on its type.
     *
     * @param mixed $fieldValue The value of the Storyblok field. Can be a single value or an array.
     * @param StoryInterface|null $story
     *
     * @return string The rendered output.
     */
    public function renderField(
        mixed $fieldValue,
        ?StoryInterface $story = null,
    ): string {
        if (empty($fieldValue)) {
            return '';
        }

        if (!is_array($fieldValue)) {
            return '';
        }

        if ($this->isRichText($fieldValue)) {
            return $this->renderRichTextField($fieldValue, $story);
        }

        if ($this->isBlock($fieldValue)) {
            return $this->renderBlockField($fieldValue, $story);
        }

        $result = '';

        foreach ($fieldValue as $child) {
            $result .= $this->renderField($child, $story);
        }

        return $result;
    }

    /**
     * Checks if a field value represents a Storyblok block.
     *
     * @param array $fieldValue The field value.
     *
     * @return bool True if it's a block, false otherwise.
     */
    private function isBlock(
        array $fieldValue,
    ): bool {
        return isset($fieldValue['_uid']) && isset($fieldValue['component']);
    }

    /**
     * Checks if a field value represents a Storyblok rich text field.
     *
     * @param array $fieldValue
     *
     * @return bool
     */
    private function isRichText(
        array $fieldValue,
    ): bool {
        return isset($fieldValue['type']) && $fieldValue['type'] === self::FIELD_TYPE_RICH_TEXT;
    }

    /**
     * Renders a Storyblok rich text field.
     *
     * @param array $fieldValue The value of the rich text field.
     * @param StoryInterface|null $story
     *
     * @return string The rendered HTML output.
     */
    public function renderRichTextField(
        array $fieldValue,
        ?StoryInterface $story = null,
    ): string {
        if ($fieldValue['type'] !== self::FIELD_TYPE_RICH_TEXT) {
            $this->logger->warning(
                __('Field value is not a valid Storyblok rich text field.'),
                ['fieldValue' => $fieldValue]
            );

            return '';
        }

        $editor = new Editor(
            [
                'extensions' => [
                    new StoryblokTipTapExtension([
                        'blokOptions' => [
                            'renderer' => function (array $fieldValue) use ($story) {
                                return $this->renderBlockField($fieldValue, $story);
                            },
                        ],
                    ]),
                ],
            ]
        );
        $editor->setContent($fieldValue);

        return $editor->getHtml();
    }

    /**
     * Renders a Storyblok block.
     *
     * @param array $fieldValue The value of the block field.
     * @param StoryInterface|null $story
     *
     * @return string The rendered HTML output.
     */
    public function renderBlockField(
        array $fieldValue,
        ?StoryInterface $story = null,
    ): string {
        if (!$this->isBlock($fieldValue)) {
            $this->logger->warning(
                __('Field value is not a valid Storyblok block.'),
                ['fieldValue' => $fieldValue]
            );

            return '';
        }

        return $this->createBlockInstance($fieldValue, $story)->toHtml();
    }

    /**
     * Creates and hydrates a Storyblok block instance.
     *
     * @param array $fieldValue
     * @param StoryInterface|null $story
     *
     * @return Block
     */
    public function createBlockInstance(
        array $fieldValue,
        ?StoryInterface $story = null,
    ): Block {
        $storyblokBlock = $this->blockFactory->create();
        $storyblokBlock->setData($fieldValue);
        $blockName = 'block_' . $storyblokBlock->getComponent() . '-' . $storyblokBlock->getUid() . '-' . uniqid();

        $blockInstance = $this->layout->createBlock(Block::class, $blockName)
            ->setData('block', $storyblokBlock)
            ->setData('story', $story);

        $hydrators = $this->blockHydrationManager->getForComponent($storyblokBlock->getComponent());

        foreach ($hydrators as $hydrator) {
            $blockInstance = $hydrator->populate($blockInstance);
        }

        return $blockInstance;
    }
}
