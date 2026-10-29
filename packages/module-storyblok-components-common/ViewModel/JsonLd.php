<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Safe JSON-LD output. Holds no schema knowledge: templates pass the schema.org node they need.
 */
class JsonLd implements ArgumentInterface
{
    /**
     * @param array $node A schema.org node, e.g. ['@type' => 'FAQPage', 'mainEntity' => [...]].
     *
     * @return string A <script type="application/ld+json"> element, or '' for an empty node.
     */
    public function render(array $node): string
    {
        if (!$node) {
            return '';
        }

        // JSON_HEX_TAG turns < and > into < / >, so editor text can never close the script element.
        return '<script type="application/ld+json">'
            . json_encode(
                ['@context' => 'https://schema.org'] + $node,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
            . '</script>';
    }

    /**
     * Rendered HTML reduced to plain text for schema text properties.
     *
     * @param string $html
     *
     * @return string
     */
    public function toPlainText(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/li|\/h\d)[^>]*>/i', "$0\n", $html)), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace(['/[ \t]+/', '/\s*\n\s*/'], [' ', "\n"], $text));
    }
}
