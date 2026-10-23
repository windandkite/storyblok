<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\TipTap;

use Magento\Cms\Model\Template\FilterProvider;
use Storyblok\Tiptap\Mark\Link as LinkMark;

/**
 * Resolves Magento CMS directives in rich text link URLs, e.g. mailto:{{config path="..."}}.
 *
 * The resulting href is escaped by TipTap when the attributes are rendered.
 */
class Link extends LinkMark
{
    /**
     * @param FilterProvider $filterProvider
     * @param array $options
     */
    public function __construct(
        private readonly FilterProvider $filterProvider,
        array $options = [],
    ) {
        parent::__construct($options);
    }

    /**
     * Render the link, resolving any directives in its href.
     *
     * @param object $mark
     * @param array $HTMLAttributes
     *
     * @return array
     */
    public function renderHTML($mark, $HTMLAttributes = [])
    {
        $result = parent::renderHTML($mark, $HTMLAttributes);
        // Storyblok may store the braces URL-encoded (%7B%7B).
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- no framework equivalent
        $href = rawurldecode((string)($result[1]['href'] ?? ''));

        if (str_contains($href, '{{')) {
            $result[1]['href'] = $this->filterProvider->getPageFilter()->filter($href);
        }

        return $result;
    }
}
