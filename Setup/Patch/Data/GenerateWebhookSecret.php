<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use WindAndKite\Storyblok\Scope\Config;

/**
 * Generates a webhook secret on install, so webhooks work as soon as the URL is added in Storyblok.
 * Installs that already have one (at any scope, or in env.php/config.php) keep it.
 */
class GenerateWebhookSecret implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
    ) {}

    public function apply(): self
    {
        if (!$this->hasSecret()) {
            $this->configWriter->save(Config::XML_PATH_WEBHOOK_SECRET, bin2hex(random_bytes(32)));
        }

        return $this;
    }

    private function hasSecret(): bool
    {
        if ($this->scopeConfig->getValue(Config::XML_PATH_WEBHOOK_SECRET)) {
            return true;
        }

        $connection = $this->moduleDataSetup->getConnection();
        $select = $connection->select()
            ->from($this->moduleDataSetup->getTable('core_config_data'), ['value'])
            ->where('path = ?', Config::XML_PATH_WEBHOOK_SECRET)
            ->where("value IS NOT NULL AND value != ''")
            ->limit(1);

        return (bool)$connection->fetchOne($select);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
