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
     * A link to this page's draft for people without a Storyblok account, only in the Visual Editor.
     *
     * @return string|null
     */
    public function getShareUrl(): ?string
    {
        if (!$this->storyblokSessionManager->isValidEditorSession()) {
            return null;
        }

        $path = $this->storyblokSessionManager->getSharePath();
        $expires = time() + $this->scopeConfig->getShareLinkDays() * 86400;

        return $this->urlBuilder->getDirectUrl($path, ['_query' => array_filter([
            StoryblokSessionManager::SHARE_PARAM => $this->storyblokSessionManager->createShareParam($path, $expires),
            StoryblokSessionManager::STORYBLOK_LANGUAGE_PARAM => $this->storyblokSessionManager->getRequestedLanguage(),
        ])]);
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
            ['_query' => [
                StoryblokSessionManager::STORYBLOK_VISUAL_EDITOR_PARAM => true,
                // The endpoint renders posted content, so it needs the editor's token too.
                StoryblokSessionManager::STORYBLOK_TOKEN_PARAM
                    => $this->storyblokSessionManager->getEditorTokenParams(),
            ]]
        );
    }
}
