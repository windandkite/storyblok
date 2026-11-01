<?php

namespace WindAndKite\Storyblok\Controller\Adminhtml\Webhook;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;
use WindAndKite\Storyblok\Scope\Config;

class RegenerateSecret extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'WindAndKite_Storyblok::config';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private WriterInterface $configWriter,
        private LoggerInterface $logger,
        private TypeListInterface $cacheTypeList,
        private EncryptorInterface $encryptor,
    ) {
        parent::__construct($context);
    }

    /**
     * Generate new webhook secret
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $newSecret = $this->generateWebhookSecret();
            $this->messageManager->addSuccessMessage(__('Webhook secret has been regenerated.'));
            $resultJson->setData(['success' => true, 'secret' => $newSecret]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $resultJson->setData(['success' => false, 'error' => __('Failed to regenerate webhook secret.')]);
            $this->messageManager->addErrorMessage(__('Failed to regenerate webhook secret.'));
        }

        return $resultJson;
    }

    private function generateWebhookSecret(): string
    {
        $secret = bin2hex(random_bytes(32));
        $this->configWriter->save(Config::XML_PATH_WEBHOOK_SECRET, $this->encryptor->encrypt($secret));
        // Webhooks read the cached config: without this they keep checking the old secret.
        $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);

        return $secret;
    }
}
