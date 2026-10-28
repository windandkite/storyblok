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

        // Only Storyblok assets carry their size in the URL, e.g. https://a.storyblok.com/f/123/1920x1080/abc/a.jpg
        if (!preg_match('#^https://a\.storyblok\.com/f/\d+/(\d+)x(\d+)/#', $src, $size) || !(int)$size[1]) {
            return [$tag, $attributes, 0];
        }

        [, $width, $height] = array_map('intval', $size);
        $displayWidth = min($width, self::MAX_WIDTH);
        $widths = array_unique([...array_filter(self::SRCSET_WIDTHS, fn (int $w) => $w < $displayWidth), $displayWidth]);

        $attributes['src'] = $this->resize($src, $displayWidth);
        $attributes['srcset'] = implode(', ', array_map(fn (int $w) => $this->resize($src, $w) . ' ' . $w . 'w', $widths));
        $attributes['sizes'] = '(min-width: ' . $displayWidth . 'px) ' . $displayWidth . 'px, 100vw';
        $attributes['width'] = $displayWidth;
        $attributes['height'] = (int)round($height * $displayWidth / $width);

        return [$tag, $attributes, 0];
    }

    private function resize(string $src, int $width): string
    {
        return $this->asset->transformImage(['fieldtype' => 'asset', 'filename' => $src], ['width' => $width]);
    }
}
