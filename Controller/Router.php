<?php

namespace WindAndKite\Storyblok\Controller;

use Exception;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Model\StoryFactory;

class Router implements RouterInterface
{
    private const CACHE_IDENTIFIER = 'windandkite_storyblok_route_';

    /**
     * @param ActionFactory $actionFactory
     * @param StoryRepositoryInterface $storyRepository
     * @param StoreManagerInterface $storeManager
     * @param CacheInterface $cache
     * @param SerializerInterface $jsonSerializer
     * @param LoggerInterface $logger
     * @param Config $config
     * @param StoryFactory $storyFactory
     */
    public function __construct(
        private ActionFactory $actionFactory,
        private StoryRepositoryInterface $storyRepository,
        private StoreManagerInterface $storeManager,
        private CacheInterface $cache,
        private SerializerInterface $jsonSerializer,
        private LoggerInterface $logger,
        private Config $config,
        private StoryFactory $storyFactory,
    ) {}

    /**
     * Match application action by request
     *
     * @param RequestInterface $request
     *
     * @return ActionInterface|null
     * @throws Exception
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        if (
            !$this->config->isModuleEnabled()
            || !$this->config->isPageRoutingEnabled()
            || !$this->config->getApiToken()
        ) {
            return null;
        }

        $identifier = trim($request->getPathInfo(), '/');

        if (empty($identifier)) {
            return null;
        }

        if ($this->config->isRestrictFolderEnabled()) {
            $folderPath = $this->config->getFolderPath();

            if ($folderPath) {
                $identifier = $folderPath . $identifier;
            }
        }

        $cacheKey = self::CACHE_IDENTIFIER . $identifier . '_' . $this->storeManager->getStore()->getId();

        try {
            if ($request->getParam('_storyblok')) {
                throw new NoSuchEntityException(__('Bypass Cache Loading'));
            }
            $cachedData = $this->cache->load($cacheKey);

            if ($cachedData) {
                try {
                    $storyData = $this->jsonSerializer->unserialize($cachedData);
                    $story = $this->storyFactory->create();
                    $story->setData($storyData);

                    $request->setParams(['story' => $story]);
                    $request->setModuleName('storyblok')->setControllerName('page')->setActionName('view');

                    return $this->actionFactory->create(Forward::class);
                } catch (Exception $e) {
                    $this->logger->warning(
                        sprintf('Error unserializing Storyblok route cache for "%s": %s', $identifier, $e->getMessage())
                    );
                    $this->cache->remove($cacheKey);
                }
            }
        } catch (NoSuchEntityException $e) {
            // Cache miss, continue to fetch from Storyblok
        }

        try {
            $storyData = $this->storyRepository->getBySlug($identifier);

            if (!$storyData->getId()) {
                return null;
            }

            $request->setParams(['story' => $storyData]);
            $request->setModuleName('storyblok')->setControllerName('page')->setActionName('view');

            try {
                $this->cache->save(
                    $this->jsonSerializer->serialize($storyData->getData()),
                    $cacheKey,
                );
            } catch (Exception $e) {
                $this->logger->warning(
                    sprintf('Error saving Storyblok route cache for "%s": %s', $identifier, $e->getMessage())
                );
            }

            return $this->actionFactory->create(Forward::class);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
