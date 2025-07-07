<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\UrlInterface;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;

class StoryblokBridgeConfig implements ArgumentInterface
{
    /**
     * @param UrlInterface $urlBuilder
     * @param StoryblokSessionManager $storyblokSessionManager
     * @param Config $scopeConfig
     */
    public function __construct(
        private UrlInterface $urlBuilder,
        private StoryblokSessionManager $storyblokSessionManager,
        private Config $scopeConfig,
    ) {}

    /**
     * Checks if the Storyblok Bridge should be initialized on the frontend.
     * This is typically true for valid editor sessions or if dev mode is enabled.
     *
     * @return bool
     */
    public function shouldInitializeStoryblokBridge(): bool
    {
        return $this->storyblokSessionManager->isValidEditorSession()
            || $this->scopeConfig->isDevModeEnabled();
    }

    /**
     * Get the AJAX URL for Storyblok content updates.
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        return $this->urlBuilder->getUrl(
            'storyblok/story/ajax',
            ['_query' => [StoryblokSessionManager::STORYBLOK_VISUAL_EDITOR_PARAM => true]]
        );
    }
}
