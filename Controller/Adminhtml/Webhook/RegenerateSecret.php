<?php

namespace WindAndKite\Storyblok\Controller\Adminhtml\Webhook;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class RegenerateSecret extends Action
{
    private const XML_PATH_WEBHOOK_SECRET = 'storyblok/general/webhook_secret';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly WriterInterface $configWriter,
        private readonly LoggerInterface $logger
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

    /**
     * Check the permission to run it
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('WindAndKite_Storyblok::config');
    }

    private function generateWebhookSecret(): string
    {
        $secret = bin2hex(random_bytes(32));
        $this->configWriter->save(self::XML_PATH_WEBHOOK_SECRET, $secret);

        return $secret;
    }
}
