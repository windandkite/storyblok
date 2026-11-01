<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\TipTap;

use Storyblok\Tiptap\Mark\Link as LinkMark;

/**
 * Resolves Magento CMS directives in rich text link URLs, e.g. mailto:{{config path="..."}}.
 *
 * Only the directives are replaced; the rest of the URL is left byte for byte, so encoded delimiters such as %26
 * keep their meaning. The resulting href is escaped by TipTap when the attributes are rendered.
 */
class Link extends LinkMark
{
    /**
     * @param DirectiveFilter $directiveFilter
     * @param array $options
     */
    public function __construct(
        private readonly DirectiveFilter $directiveFilter,
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

        if (isset($result[1]['href'])) {
            // Storyblok may store the braces, quotes and spaces URL-encoded (%7B%7B ... %7D%7D).
            $result[1]['href'] = preg_replace_callback(
                '/(?:{{|%7B%7B).*?(?:}}|%7D%7D)/i',
                // phpcs:ignore Magento2.Functions.DiscouragedFunction -- no framework equivalent
                fn (array $match) => $this->directiveFilter->resolve(rawurldecode($match[0])) ?? $match[0],
                (string)$result[1]['href']
            );
        }

        return $result;
    }
}
