<?php

namespace WindAndKite\Storyblok\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Rest\Request;
use WindAndKite\Storyblok\Scope\Config;

class Index implements HttpPostActionInterface
{

    /**
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param Config $config
     * @param Request $request
     */
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly Config $config,
        private readonly Request $request,
    ) {}

    /**
     * Webhook endpoint.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $payload = $this->request->getContent();
            $data = $this->serializer->unserialize($payload);

            if (!$this->isValidRequest($data)) {
                $this->logger->error('Invalid webhook request: ' . $payload);
                throw new WebapiException(__('Invalid request'), WebapiException::HTTP_UNAUTHORIZED);
            }

            $this->processWebhook($data);

            $resultJson->setData(['status' => 'OK']);
            return $resultJson;

        } catch (WebapiException $e) {
            $resultJson->setHttpResponseCode($e->getHttpCode());
            $resultJson->setData(['error' => $e->getMessage()]);
            return $resultJson;

        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $resultJson->setHttpResponseCode(500);
            $resultJson->setData(['error' => 'Internal Server Error']);
            return $resultJson;
        }
    }

    /**
     * Validates if the request is valid.
     *
     * @param array $data
     * @return bool
     */
    private function isValidRequest(array $data): bool
    {
        $signatureHeader = $this->request->getHeader('webhook-signature');

        if (!$signatureHeader) {
            return false;
        }

        $payload = $this->request->getContent();
        $secret = $this->config->getWebhookSecret();

        $calculatedSignature = 'sha1=' . hash_hmac('sha1', $payload, $secret);

        return hash_equals($signatureHeader, $calculatedSignature);
    }

    /**
     * Processes the webhook payload.
     *
     * @param array $data
     * @return void
     */
    private function processWebhook(array $data): void
    {
        $this->logger->info('Webhook received and processed: ' . $this->serializer->serialize($data));

        try {
            $storyId = $data['story_id'] ?? null;
            $slug = $data['text'] ?? null;
            $cv = $data['cv'] ?? null;

            if (!$storyId) {
                $this->logger->warning('Webhook payload missing story_id.');
                return;
            }

            // Invalidate cache tags based on story_id
            $cacheTags = ['storyblok_story_' . $storyId];
            $this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, $cacheTags);

            // If cv is present, invalidate cache based on cv
            if ($cv) {
                $cacheTags[] = 'storyblok_cv_' . $cv;
                $this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, $cacheTags);
            }

            // If slug is present, invalidate cache based on slug pattern (more complex)
            if ($slug) {
                $cacheTags[] = 'storyblok_slug_' . $slug;
                $this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, $cacheTags);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook: ' . $e->getMessage());
        }
    }
}
