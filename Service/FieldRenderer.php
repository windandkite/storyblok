<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\View\LayoutInterface;
use Psr\Log\LoggerInterface;
use Storyblok\Tiptap\Extension\Storyblok as StoryblokTipTapExtension;
use Tiptap\Editor;
use WindAndKite\Storyblok\Model\BlockFactory;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\ViewModel\Asset;

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
     */
    public function __construct(
        private LoggerInterface $logger,
        private LayoutInterface $layout,
        private BlockFactory $blockFactory,
        private Asset $assetViewModel,
    ) {}

    /**
     * Renders a Storyblok field based on its type.
     *
     * @param mixed $fieldValue The value of the Storyblok field. Can be a single value or an array.
     * @return string The rendered output.
     */
    public function renderField(mixed $fieldValue): string {
        if (empty($fieldValue)) {
            return '';
        }

        if (!is_array($fieldValue)) {
            return ''; // Or handle non-array values differently (log, throw exception, etc.)
        }

        if ($this->isRichText($fieldValue)) {
            return $this->renderRichTextField($fieldValue);
        }

        if ($this->isBlock($fieldValue)) {
            return $this->renderBlockField($fieldValue);
        }

        $result = '';

        foreach ($fieldValue as $child) {
            $result .= $this->renderField($child);
        }

        return $result;
    }

    /**
     * Checks if a field value represents a Storyblok block.
     *
     * @param array $fieldValue The field value.
     * @return bool True if it's a block, false otherwise.
     */
    private function isBlock(
        array $fieldValue
    ): bool {
        return isset($fieldValue['_uid']) && isset($fieldValue['_editable']);
    }

    private function isRichText(
        array $fieldValue
    ): bool {
        return isset($fieldValue['type']) && $fieldValue['type'] === self::FIELD_TYPE_RICH_TEXT;
    }

    /**
     * Renders a Storyblok rich text field.
     *
     * @param mixed $fieldValue The value of the rich text field.
     * @return string The rendered HTML output.
     */
    public function renderRichTextField(
        array $fieldValue,
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
                            'renderer' => [$this, 'renderBlockField']
                        ]
                    ])
                ]
            ]
        );
        $editor->setContent($fieldValue);

        return $editor->getHtml();
    }

    /**
     * Renders a Storyblok block.
     *
     * @param array $fieldValue The value of the block field.
     * @return string The rendered HTML output.
     */
    public function renderBlockField(
        array $fieldValue,
    ): string {
        if (!$this->isBlock($fieldValue)) {
            $this->logger->warning(
                __('Field value is not a valid Storyblok block.'),
                ['fieldValue' => $fieldValue]
            );

            return '';
        }

        $storyblokBlock = $this->blockFactory->create();
        $storyblokBlock->setData($fieldValue);
        $blockName = 'block_' . $storyblokBlock->getComponent() . '-' . $storyblokBlock->getUid() . '-' . uniqid();

        return $this->layout->createBlock(Block::class, $blockName)
            ->setData('block', $storyblokBlock)
            ->setData('asset_view_model', $this->assetViewModel)
            ->toHtml();
    }
}
