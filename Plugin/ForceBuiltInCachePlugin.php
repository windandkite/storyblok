<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Plugin;

use Magento\PageCache\Model\Config;
use WindAndKite\Storyblok\Scope\Config as StoryblokScopeConfig;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;

/**
 * Interceptor for @see Config
 */
class ForceBuiltInCachePlugin
{
    public function __construct(
        private readonly StoryblokSessionManager $storyblokSessionManager,
        private readonly StoryblokScopeConfig $scopeConfig,
    ) {}

    /**
     * Intercepted method getType.
     *
     * Force the Page Cache type to 'Built-In' during Storyblok sessions. This prevents Magento from wrapping blocks in
     * ESI tags (<esi:include />) when the page is loaded within the Storyblok iframe. By forcing Built-in mode, Magento
     * renders block content inline, ensuring:
     *  1. The browser's HTML5 parser doesn't disfigure the layout due to self-closing ESI tags.
     *  2. Content-specific blocks (like menus and carts) are visible and editable within the Storyblok preview.
     *
     * @param Config $subject
     * @param int $result
     *
     * @return int
     * @see Config::getType
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetType(
        Config $subject,
        int $result
    ): int {
        if ($this->storyblokSessionManager->isValidEditorSession() || $this->scopeConfig->isDevModeEnabled()) {
            return Config::BUILT_IN;
        }

        return $result;
    }
}
