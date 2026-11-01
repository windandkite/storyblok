<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Plugin;

use Magento\Framework\App\Response\HeaderProvider\XFrameOptions;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;

/**
 * Interceptor for @see XFrameOptions
 */
class AllowVisualEditorFramePlugin
{
    public function __construct(
        private readonly StoryblokSessionManager $storyblokSessionManager,
    ) {}

    /**
     * The Visual Editor loads the storefront in an iframe on app.storyblok.com, which X-Frame-Options (SAMEORIGIN by
     * default) blocks. Only requests with a valid editor token skip it; csp_whitelist.xml allows Storyblok as a
     * frame ancestor.
     *
     * @param XFrameOptions $subject
     * @param bool $result
     *
     * @return bool
     * @see XFrameOptions::canApply
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCanApply(
        XFrameOptions $subject,
        bool $result
    ): bool {
        return $result && !$this->storyblokSessionManager->isValidEditorSession();
    }
}
