<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class SeoMetaData implements ArgumentInterface
{
    /**
     * Defines the desired meta tags and their Storyblok content key fallbacks.
     * The key is the desired 'name' attribute for the HTML <meta> tag (e.g., 'og:title').
     * The value is an ordered array of Storyblok content keys to check (e.g., ['og_title', 'title']).
     *
     * @var array<string, array<string>>
     */
    private const META_DEFINITIONS = [
        'og:title' => ['og_title', 'title'],
        'og:description' => ['og_description', 'description'],
        'og:image' => ['og_image'],
        'twitter:title' => ['twitter_title', 'og_title', 'title'],
        'twitter:description' => ['twitter_description', 'og_description', 'description'],
        'twitter:image' => ['twitter_image', 'og_image'],
    ];

    /**
     * Prepares and returns a filtered list of meta tags for HTML output.
     *
     * @param array|string|null $storyblokMetatags The raw metaData array from Storyblok content.
     *
     * @return array Associative array of ['meta_name' => 'meta_value'] suitable for direct output.
     */
    public function getPreparedMetatags(
        $storyblokMetatags = []
    ): array {
        if (is_string($storyblokMetatags)) {
            $storyblokMetatags = json_decode($storyblokMetatags, true);
        }

        if (empty($storyblokMetatags)) {
            return [];
        }

        $findFirstNonEmptyValue = function (
            array $sourceData,
            array $keysToSearch
        ): ?string {
            foreach ($keysToSearch as $key) {
                if (!empty($sourceData[$key])) {
                    return (string)$sourceData[$key];
                }
            }

            return null;
        };

        $outputMetaData = [];

        foreach (self::META_DEFINITIONS as $outputName => $fallbackKeys) {
            $value = $findFirstNonEmptyValue($storyblokMetatags, $fallbackKeys);

            if ($value !== null) {
                $outputMetaData[$outputName] = $value;
            }
        }

        return $outputMetaData;
    }
}
