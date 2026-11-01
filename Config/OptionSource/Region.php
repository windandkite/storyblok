<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Config\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;

class Region implements OptionSourceInterface
{
    /**
     * Content Delivery API host for each Storyblok region.
     */
    public const BASE_URLS = [
        'eu' => 'https://api.storyblok.com',
        'us' => 'https://api-us.storyblok.com',
        'ca' => 'https://api-ca.storyblok.com',
        'ap' => 'https://api-ap.storyblok.com',
        'cn' => 'https://app.storyblokchina.cn',
    ];

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'eu', 'label' => __('Europe')],
            ['value' => 'us', 'label' => __('United States')],
            ['value' => 'ca', 'label' => __('Canada')],
            ['value' => 'ap', 'label' => __('Australia')],
            ['value' => 'cn', 'label' => __('China')],
        ];
    }
}
