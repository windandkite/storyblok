<?php
namespace WindAndKite\Storyblok\Scope;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'storyblok/general/enabled';
    private const XML_PATH_API_TOKEN = 'storyblok/general/api_token';
    private const XML_PATH_WEBHOOK_SECRET = 'storyblok/general/webhook_secret';
    private const XML_PATH_DEV_MODE = 'storyblok/general/dev_mode';
    private const XML_PATH_PAGE_ROUTING_ENABLED = 'storyblok/page_routing/enabled';
    private const XML_PATH_RESTRICT_FOLDER = 'storyblok/page_routing/restrict_folder';
    private const XML_PATH_FOLDER_PATH = 'storyblok/page_routing/folder_path';
    private const XML_PATH_SITEMAP_ENABLED = 'storyblok/sitemap/enabled';
    private const XML_PATH_SITEMAP_PRIORITY = 'storyblok/sitemap/priority';
    private const XML_PATH_SITEMAP_CHANGEFREQ = 'storyblok/sitemap/changefreq';
    private const XML_PATH_SITEMAP_EXCLUDE_FOLDERS = 'storyblok/sitemap/exclude_folders';
    private const XML_PATH_ENABLE_STORY_LISTS = 'storyblok/story_lists/enable';
    private const XML_PATH_STORIES_PER_PAGE = 'storyblok/story_lists/per_page';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {}

    public function isModuleEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            $scopeType,
            $scopeCode,
        );
    }

    public function getApiToken(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): null|int|string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_TOKEN,
            $scopeType,
            $scopeCode,
        );
    }

    public function getWebhookSecret(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): ?string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_SECRET,
            $scopeType,
            $scopeCode,
        );
    }

    public function isDevModeEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEV_MODE,
            $scopeType,
            $scopeCode,
        );
    }

    public function isPageRoutingEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PAGE_ROUTING_ENABLED,
            $scopeType,
            $scopeCode,
        );
    }

    public function isRestrictFolderEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESTRICT_FOLDER,
            $scopeType,
            $scopeCode
        );
    }

    public function getFolderPath(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): null|int|string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_FOLDER_PATH,
            $scopeType,
            $scopeCode
        );
    }

    public function isSitemapEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SITEMAP_ENABLED,
            $scopeType,
            $scopeCode
        );
    }

    public function getSitemapPriority(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): string {
        return $this->scopeConfig->getValue(
             self::XML_PATH_SITEMAP_PRIORITY,
            $scopeType,
            $scopeCode
        );
    }

    public function getSitemapChangefreq(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SITEMAP_CHANGEFREQ,
            $scopeType,
            $scopeCode
        );
    }

    public function getSitemapExcludeFolders(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): array {
        $excludedFoldersString = $this->scopeConfig->getValue(
            self::XML_PATH_SITEMAP_EXCLUDE_FOLDERS,
            $scopeType,
            $scopeCode
        );

        if (empty($excludedFoldersString)) {
            return [];
        }

        $excludedFolders = explode(",", $excludedFoldersString);

        return array_map('trim', $excludedFolders);
    }

    public function isStoryListsEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_STORY_LISTS,
            $scopeType,
            $scopeCode,
        );
    }

    public function getStoryListPerPage(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): ?int {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_STORIES_PER_PAGE,
            $scopeType,
            $scopeCode
        );

        return ($value === null)? null : (int)$value;
    }
}
