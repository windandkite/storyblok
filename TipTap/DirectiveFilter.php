<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\TipTap;

use Magento\Cms\Model\Template\FilterProvider;
use Throwable;

/**
 * Resolves a single Magento CMS directive found in rich text.
 */
class DirectiveFilter
{
    /**
     * @param FilterProvider $filterProvider
     */
    public function __construct(
        private readonly FilterProvider $filterProvider,
    ) {}

    /**
     * Resolve one "{{...}}" directive, or null when it isn't one Magento can resolve.
     *
     * Magento keeps unrecognised directives (with "{{" still in them) and throws a TypeError on some malformed ones,
     * e.g. "{{<img ...>}}". Callers treat null as plain text, so neither reaches the page as raw HTML or breaks it.
     *
     * @param string $directive
     *
     * @return string|null
     */
    public function resolve(string $directive): ?string
    {
        try {
            $value = $this->filterProvider->getPageFilter()->filter($directive);
        } catch (Throwable) {
            return null;
        }

        return str_contains($value, '{{') ? null : $value;
    }
}
