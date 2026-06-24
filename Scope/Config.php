<?php
namespace WindAndKite\Storyblok\Scope;

use InvalidArgumentException;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use WindAndKite\Storyblok\Config\OptionSource\DefaultSort;

class Config
{
    private const XML_PATH_ENABLED = 'storyblok/general/enabled';
    private const XML_PATH_API_TOKEN = 'storyblok/general/api_token';
    private const XML_PATH_WEBHOOK_SECRET = 'storyblok/general/webhook_secret';
    private const XML_PATH_DEV_MODE = 'storyblok/general/dev_mode';
    private const XML_PATH_PAGE_ROUTING_ENABLED = 'storyblok/page_routing/enabled';
    private const XML_PATH_RESTRICT_FOLDER = 'storyblok/page_routing/restrict_folder';
    private const XML_PATH_FOLDER_PATH = 'storyblok/page_routing/folder_path';
    private const XML_PATH_RESTRICT_CONTENT_TYPES = 'storyblok/page_routing/restrict_content_types';
    private const XML_PATH_LANGUAGE = 'storyblok/page_routing/language';
    private const XML_PATH_ALLOWED_FULL_PAGE_CONTENT_TYPES = 'storyblok/page_routing/allowed_full_page_content_types';
    private const XML_PATH_SITEMAP_ENABLED = 'storyblok/sitemap/enabled';
    private const XML_PATH_SITEMAP_PRIORITY = 'storyblok/sitemap/priority';
    private const XML_PATH_SITEMAP_CHANGEFREQ = 'storyblok/sitemap/changefreq';
    private const XML_PATH_SITEMAP_EXCLUDE_FOLDERS = 'storyblok/sitemap/exclude_folders';
    private const XML_PATH_ENABLE_STORY_LISTS = 'storyblok/story_lists/enable';
    private const XML_PATH_STORIES_PER_PAGE = 'storyblok/story_lists/per_page';
    private const XML_PATH_DEFAULT_SORT_TYPE = 'storyblok/story_lists/default_sort_type';
    private const XML_PATH_DEFAULT_SORT_CUSTOM = 'storyblok/story_lists/default_sort_custom';
    private const XML_PATH_ENABLE_HOME = 'storyblok/home/enable_home';
    private const XML_PATH_HOME_SLUG = 'storyblok/home/home_slug';

    /**
     * There is and never will be an admin config field for this, to set this up please use
     * bin/magento config:set storyblok/dev/webhook_secret <your-secret>
     * You can also use -le or -lc to lock this value to your env.php or config.php file
     */
    private const XML_PATH_DEV_WEBHOOK_SECRET = 'storyblok/dev/webhook_secret';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private SerializerInterface $serializer,
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

    public function getLanguage(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): ?string {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_LANGUAGE,
            $scopeType,
            $scopeCode
        );

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isRestrictContentTypesEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESTRICT_CONTENT_TYPES,
            $scopeType,
            $scopeCode
        );
    }

    public function getAllowedFullPageContentTypes(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null
    ): array {
        $contentTypesString = $this->scopeConfig->getValue(
            self::XML_PATH_ALLOWED_FULL_PAGE_CONTENT_TYPES,
            $scopeType,
            $scopeCode
        );

        return $this->serializer->unserialize($contentTypesString ?? '[]');
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

    /**
     * Returns a normalized, predictable array of sorting rules with appropriate 'content.' prefixes applied.
     *
     * @param string $scopeType
     * @param null|int|string $scopeCode
     * @return array<array{field: string, direction: string}>
     */
    public function getDefaultListSort(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): array {
        $sortType = (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_SORT_TYPE,
            $scopeType,
            $scopeCode
        );

        $fallbackField = DefaultSort::FIELD_FIRST_PUBLISHED_AT;
        $fallbackDir = strtolower(SortOrder::SORT_DESC);

        if ($sortType !== DefaultSort::FIELD_CUSTOM) {
            $separator = DefaultSort::SEPARATOR;
            $parts = explode($separator, $sortType ?: ($fallbackField . $separator . $fallbackDir), 2);
            $field = $parts[0] !== '' ? $parts[0] : $fallbackField;
            $direction = strtolower($parts[1] ?? $fallbackDir);

            return [[ 'field' => $field, 'direction' => $direction ]];
        }

        $customSortString = $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_SORT_CUSTOM,
            $scopeType,
            $scopeCode
        );

        $customRows = [];

        if (!empty($customSortString)) {
            try {
                $customRows = $this->serializer->unserialize($customSortString);
            } catch (InvalidArgumentException) {
                // Use Default Empty Array
            }
        }

        $customRows = is_array($customRows) ? $customRows : [];
        $sortRules = [];

        foreach ($customRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $preset = $row['preset_field'] ?? DefaultSort::FIELD_CUSTOM;
            $field = $row['field_name'] ?? $preset;
            $direction = strtolower($row['direction'] ?? SortOrder::SORT_ASC);

            if ($field) {
                if ($preset === DefaultSort::FIELD_CUSTOM && !str_starts_with($field, 'content.')) {
                    $field = 'content.' . $field;
                }

                $sortRules[] = [
                    'field' => $field,
                    'direction' => $direction
                ];
            }
        }

        return $sortRules ?: [['field' => $fallbackField, 'direction' => $fallbackDir]];
    }

    public function getDeveloperWebhookSecret(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): ?string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DEV_WEBHOOK_SECRET,
            $scopeType,
            $scopeCode
        );
    }

    public function isHomeEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_HOME,
            $scopeType,
            $scopeCode,
        );
    }

    public function getHomeSlug(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        null|int|string $scopeCode = null,
    ): ?string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_HOME_SLUG,
            $scopeType,
            $scopeCode
        );
    }
}
