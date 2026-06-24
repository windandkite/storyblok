<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Config\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;

class DynamicSortFields implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => DefaultSort::FIELD_CUSTOM, 'label' => __('Custom Field Name...')],
            ['value' => DefaultSort::FIELD_FIRST_PUBLISHED_AT, 'label' => __('First Published At')],
            ['value' => DefaultSort::FIELD_PUBLISHED_AT, 'label' => __('Published At')],
            ['value' => DefaultSort::FIELD_UPDATED_AT, 'label' => __('Updated At')],
            ['value' => DefaultSort::FIELD_POSITION, 'label' => __('Position')],
            ['value' => DefaultSort::FIELD_NAME, 'label' => __('Name')],
        ];
    }
}
