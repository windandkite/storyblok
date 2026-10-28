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
    // Lower Storyblok plans can't sign webhooks, so the secret can also be passed in the webhook URL.
    public const KEY_PARAM = 'key';

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
            $payload = (string)$this->request->getContent();

            if (!$this->isValidRequest()) {
                // Only the size: the body is untrusted and unbounded.
                $this->logger->warning(sprintf('Invalid webhook request (%d bytes).', strlen($payload)));
                throw new WebapiException(__('Invalid request'), 0, WebapiException::HTTP_UNAUTHORIZED);
            }

            try {
                $data = $this->serializer->unserialize($payload);
            } catch (\InvalidArgumentException) {
                $data = null;
            }

            if (!is_array($data)) {
                throw new WebapiException(__('Invalid request'), 0, WebapiException::HTTP_BAD_REQUEST);
            }

            $this->processWebhook($data);

            $resultJson->setData(['status' => 'OK']);
            return $resultJson;

        } catch (WebapiException $e) {
            $resultJson->setHttpResponseCode($e->getHttpCode());
            $resultJson->setData(['error' => $e->getMessage()]);
            return $resultJson;

        } catch (\Throwable $e) {
            $this->logger->critical($e->getMessage());
            $resultJson->setHttpResponseCode(500);
            $resultJson->setData(['error' => 'Internal Server Error']);
            return $resultJson;
        }
    }

    /**
     * Whether the request comes from Storyblok: a valid "webhook-signature" (plans that can sign webhooks), else the
     * secret as the "key" URL parameter. Nothing is accepted until a secret has been generated.
     *
     * @return bool
     */
    private function isValidRequest(): bool
    {
        $developerValidationKeyHeader = $this->request->getHeader('developer-validation-key');

        if (is_string($developerValidationKeyHeader) && $developerValidationKeyHeader !== '') {
            $developerSecret = $this->config->getDeveloperWebhookSecret();

            if ($developerSecret && hash_equals($developerSecret, $developerValidationKeyHeader)) {
                $this->logger->info('Webhook validated via developer key.');
                return true;
            }
        }

        $secret = (string)$this->config->getWebhookSecret();

        if ($secret === '') {
            return false;
        }

        $signatureHeader = $this->request->getHeader('webhook-signature');

        if (is_string($signatureHeader) && $signatureHeader !== '') {
            return hash_equals(hash_hmac('sha1', (string)$this->request->getContent(), $secret), $signatureHeader);
        }

        $key = $this->request->getParam(self::KEY_PARAM);

        return is_string($key) && hash_equals($secret, $key);
    }

    /**
     * Processes the webhook payload.
     *
     * @param array $data
     * @return void
     */
    private function processWebhook(array $data): void
    {
        $this->logger->info(sprintf(
            'Storyblok webhook: %s story %s.',
            is_scalar($data['action'] ?? null) ? $data['action'] : 'unknown action',
            is_scalar($data['story_id'] ?? null) ? $data['story_id'] : '?'
        ));

        try {
            $storyId = $data['story_id'] ?? null;
            $slug = $data['full_slug'] ?? null;
            $cv = $data['cv'] ?? null;

            if (!$storyId) {
                $this->logger->warning('Webhook payload missing story_id.');

                return;
            }

            // Invalidate cache tags based on story_id
            $cacheTags = ['storyblok_story_id_' . $storyId];

            // Always invalidate all listing pages when a story is published
            $cacheTags[] = StoryblokCacheService::LISTS_TAG;

            // A published story may live at a slug that was cached as not found
            $cacheTags[] = StoryblokCacheService::NOT_FOUND_TAG;


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
