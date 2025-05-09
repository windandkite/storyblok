<?php

namespace WindAndKite\Storyblok\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception as WebapiException;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Rest\Request;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryblokCacheService;

class Index implements CsrfAwareActionInterface, HttpPostActionInterface
{

    /**
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param StoryblokCacheService $cacheService
     * @param SerializerInterface $serializer
     * @param Config $config
     * @param Request $request
     */
    public function __construct(
        private JsonFactory $resultJsonFactory,
        private LoggerInterface $logger,
        private StoryblokCacheService $cacheService,
        private SerializerInterface $serializer,
        private Config $config,
        private Request $request,
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

        $calculatedSignature = hash_hmac('sha1', $payload, $secret);

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
            $cacheTags = ['storyblok_story_id_' . $storyId];


            // If cv is present, invalidate cache based on cv
            if ($cv) {
                $cacheTags[] = 'storyblok_cv_' . $cv;
            }

            // If slug is present, invalidate cache based on slug pattern (more complex)
            if ($slug) {
                $cacheTags[] = 'storyblok_story_slug_' . $slug;
            }

            $this->cacheService->cleanCacheByTags($cacheTags);

        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook: ' . $e->getMessage());
        }
    }

    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    public function validateForCsrf(
        RequestInterface $request
    ): ?bool {
        return true;
    }
}
