<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Setup\Patch\Data;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use WindAndKite\Storyblok\Scope\Config;

/**
 * Encrypts webhook secrets saved in plain text before the setting used the Encrypted backend.
 */
class EncryptWebhookSecret implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EncryptorInterface $encryptor,
    ) {}

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['config_id', 'value'])
                ->where('path = ?', Config::XML_PATH_WEBHOOK_SECRET)
                ->where("value IS NOT NULL AND value != ''")
        );

        foreach ($rows as $configId => $value) {
            if (!preg_match(Config::ENCRYPTED_VALUE_PATTERN, $value)) {
                $connection->update($table, ['value' => $this->encryptor->encrypt($value)], ['config_id = ?' => $configId]);
            }
        }

        return $this;
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
