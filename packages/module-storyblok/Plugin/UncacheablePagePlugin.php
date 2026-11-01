<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Plugin;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\View\Layout;
use WindAndKite\Storyblok\Service\PageCacheability;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;

/**
 * Interceptor for @see Layout
 */
class UncacheablePagePlugin
{
    public function __construct(
        private readonly StoryblokSessionManager $storyblokSessionManager,
        private readonly PageCacheability $pageCacheability,
        private readonly HttpResponse $response,
    ) {}

    /**
     * Draft pages (Visual Editor, share links, dev mode) and pages missing Storyblok content are never cached.
     * Magento only sends public cache headers for cacheable layouts, so this keeps them out of both the built-in
     * cache and Varnish. Drafts are also kept out of search engines.
     *
     * @param Layout $subject
     * @param bool $result
     *
     * @return bool
     * @see Layout::isCacheable
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsCacheable(
        Layout $subject,
        bool $result
    ): bool {
        if ($this->storyblokSessionManager->isDraftRequest()) {
            $this->response->setHeader('X-Robots-Tag', 'noindex, nofollow', true);

            return false;
        }

        return $result && !$this->pageCacheability->isUncacheable();
    }
}
