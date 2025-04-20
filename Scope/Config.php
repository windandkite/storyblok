<?php
namespace WindAndKite\Storyblok\Scope;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'storyblok/general/enabled';
    private const XML_PATH_API_TOKEN = 'storyblok/general/api_token';
    private const XML_PATH_DEV_MODE = 'storyblok/general/dev_mode';
    private const XML_PATH_PAGE_ROUTING_ENABLED = 'storyblok/page_routing/enabled';
    private const XML_PATH_RESTRICT_FOLDER = 'storyblok/page_routing/restrict_folder';
    private const XML_PATH_FOLDER_PATH = 'storyblok/page_routing/folder_path';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {}

    public function isModuleEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            $scopeType,
            $scopeCode,
        );
    }

    public function getApiToken(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null,
    ): ?string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_TOKEN,
            $scopeType,
            $scopeCode,
        );
    }

    public function isDevModeEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEV_MODE,
            $scopeType,
            $scopeCode,
        );
    }

    public function isPageRoutingEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null,
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PAGE_ROUTING_ENABLED,
            $scopeType,
            $scopeCode,
        );
    }

    public function isRestrictFolderEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESTRICT_FOLDER,
            $scopeType,
            $scopeCode
        );
    }

    public function getFolderPath(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): ?string {
        return $this->scopeConfig->getValue(
            self::XML_PATH_FOLDER_PATH,
            $scopeType,
            $scopeCode
        );
    }
}
