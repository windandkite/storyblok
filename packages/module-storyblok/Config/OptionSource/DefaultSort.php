<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Config\OptionSource;

use Magento\Framework\Api\SortOrder;
use Magento\Framework\Data\OptionSourceInterface;

class DefaultSort implements OptionSourceInterface
{
    public const SEPARATOR = '::';

    // Sortable Core Fields
    public const FIELD_FIRST_PUBLISHED_AT = 'first_published_at';
    public const FIELD_PUBLISHED_AT = 'published_at';
    public const FIELD_UPDATED_AT = 'updated_at';
    public const FIELD_POSITION = 'position';
    public const FIELD_NAME = 'name';
    public const FIELD_CUSTOM = 'custom';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::FIELD_FIRST_PUBLISHED_AT . self::SEPARATOR . SortOrder::SORT_DESC,
                'label' => __('First Published At (Newest First)')
            ],
            [
                'value' => self::FIELD_FIRST_PUBLISHED_AT . self::SEPARATOR . SortOrder::SORT_ASC,
                'label' => __('First Published At (Oldest First)')
            ],
            [
                'value' => self::FIELD_PUBLISHED_AT . self::SEPARATOR . SortOrder::SORT_DESC,
                'label' => __('Published At (Newest First)')
            ],
            [
                'value' => self::FIELD_PUBLISHED_AT . self::SEPARATOR . SortOrder::SORT_ASC,
                'label' => __('Published At (Oldest First)')
            ],
            [
                'value' => self::FIELD_UPDATED_AT . self::SEPARATOR . SortOrder::SORT_DESC,
                'label' => __('Updated At (Most Recent)')
            ],
            [
                'value' => self::FIELD_POSITION . self::SEPARATOR . SortOrder::SORT_ASC,
                'label' => __('Storyblok Position (Ascending only)')
            ],
            [
                'value' => self::FIELD_NAME . self::SEPARATOR . SortOrder::SORT_ASC,
                'label' => __('Name (A-Z)')
            ],
            [
                'value' => self::FIELD_CUSTOM,
                'label' => __('-- Custom Multi-Sort Grid --')
            ],
        ];
    }
}
