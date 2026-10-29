<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\TipTap;

use Tiptap\Nodes\Image as ImageNode;
use WindAndKite\Storyblok\ViewModel\Asset;

/**
 * Renders rich text images resized by Storyblok's image service, with their dimensions, a srcset and lazy loading.
 *
 * Without this they're output at full size, with no dimensions: multi-MB downloads and layout shift.
 */
class Image extends ImageNode
{
    // Rich text is usually no wider than the content column.
    private const MAX_WIDTH = 1200;
    private const SRCSET_WIDTHS = [400, 800, 1200];

    /**
     * @param Asset $asset
     * @param array $options
     */
    public function __construct(
        private readonly Asset $asset,
        array $options = [],
    ) {
        parent::__construct($options);
    }

    /**
     * @param object $node
     * @param array $HTMLAttributes
     *
     * @return array
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        [$tag, $attributes] = parent::renderHTML($node, $HTMLAttributes);
        $attributes += ['loading' => 'lazy', 'decoding' => 'async'];
        $src = (string)($attributes['src'] ?? '');

        // Only original Storyblok assets, which carry their size in the URL, e.g.
        // https://a.storyblok.com/f/123/1920x1080/abc/a.jpg?cv=1. Already transformed URLs (…/a.jpg/m/600x0) are
        // left as they are. A query string or fragment goes after the transformation.
        if (
            !preg_match('#^(https://a\.storyblok\.com/f/\d+/(\d+)x(\d+)/[^/?\#]+/[^/?\#]+)([?\#].*)?$#', $src, $match)
            || !(int)$match[2]
        ) {
            return [$tag, $attributes, 0];
        }

        $src = $match[1];
        $suffix = $match[4] ?? '';
        [$width, $height] = [(int)$match[2], (int)$match[3]];
        $displayWidth = min($width, self::MAX_WIDTH);
        $attributes['width'] = $displayWidth;
        $attributes['height'] = (int)round($height * $displayWidth / $width);

        // The image service can't resize SVGs: keep the original URL, with just the dimensions.
        if (str_ends_with(strtolower($src), '.svg')) {
            return [$tag, $attributes, 0];
        }

        $widths = array_unique([...array_filter(self::SRCSET_WIDTHS, fn (int $w) => $w < $displayWidth), $displayWidth]);

        $attributes['src'] = $this->resize($src, $displayWidth) . $suffix;
        $attributes['srcset'] = implode(', ', array_map(
            fn (int $w) => $this->resize($src, $w) . $suffix . ' ' . $w . 'w',
            $widths
        ));
        $attributes['sizes'] = '(min-width: ' . $displayWidth . 'px) ' . $displayWidth . 'px, 100vw';

        return [$tag, $attributes, 0];
    }

    private function resize(string $src, int $width): string
    {
        return $this->asset->transformImage(['fieldtype' => 'asset', 'filename' => $src], ['width' => $width]);
    }
}
