<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\App\Response\Http as HttpResponse;

/**
 * Keeps the current page out of the full-page cache (built-in and Varnish), e.g. when Storyblok content couldn't be
 * loaded, so a page missing that content isn't cached.
 */
class PageCacheability
{
    private bool $uncacheable = false;

    /**
     * @param HttpResponse $response
     */
    public function __construct(
        private readonly HttpResponse $response,
    ) {}

    /**
     * No-cache headers now (overriding public headers already set), and isCacheable() false from here on.
     *
     * @return void
     */
    public function markUncacheable(): void
    {
        $this->uncacheable = true;
        $this->response->setNoCacheHeaders();
    }

    public function isUncacheable(): bool
    {
        return $this->uncacheable;
    }
}
