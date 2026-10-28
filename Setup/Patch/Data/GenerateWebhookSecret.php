<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use WindAndKite\Storyblok\Scope\Config;

/**
 * Generates a webhook secret on install, so webhooks work as soon as the URL is added in Storyblok.
 * Installs that already have one keep it. The secret is default scope only: a website-scoped secret becomes the
 * default if there isn't one, and scoped values are removed.
 */
class GenerateWebhookSecret implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor,
    ) {}

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        $scoped = ['path = ?' => Config::XML_PATH_WEBHOOK_SECRET, 'scope != ?' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT];

        $defaultSecret = $connection->fetchOne(
            $connection->select()
                ->from($table, ['value'])
                ->where('path = ?', Config::XML_PATH_WEBHOOK_SECRET)
                ->where('scope = ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
        );

        // The database is checked directly: config may be cached from before the secret was encrypted.
        // ScopeConfig covers a secret set in env.php or config.php.
        if (!$defaultSecret && !$this->scopeConfig->getValue(Config::XML_PATH_WEBHOOK_SECRET)) {
            $select = $connection->select()->from($table, ['value'])->where("value IS NOT NULL AND value != ''");

            foreach ($scoped as $condition => $value) {
                $select->where($condition, $value);
            }

            // If websites had different secrets, only the first is kept: Storyblok needs the new URL for the others.
            // Stored values are already encrypted (see EncryptWebhookSecret).
            $secret = $connection->fetchOne($select->order('config_id')->limit(1))
                ?: $this->encryptor->encrypt(bin2hex(random_bytes(32)));
            $this->configWriter->save(Config::XML_PATH_WEBHOOK_SECRET, $secret);
        }

        // Scoped secrets are no longer read or shown in the admin.
        $connection->delete($table, $scoped);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [EncryptWebhookSecret::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
