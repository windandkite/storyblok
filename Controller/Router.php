<?php

namespace WindAndKite\Storyblok\Controller;

use Exception;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Request\StoryRequest;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use WindAndKite\Storyblok\Scope\Config;

class Router implements RouterInterface
{
    public const STORYBLOK_EDITOR_KEY = '_storyblok';

    /**
     * @param ActionFactory $actionFactory
     * @param StoryRepositoryInterface $storyRepository
     * @param Config $config
     */
    public function __construct(
        private ActionFactory $actionFactory,
        private StoryRepositoryInterface $storyRepository,
        private Config $config,
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

        $storyRequest = null;

        if ($request->getParam(self::STORYBLOK_EDITOR_KEY) || $this->config->isDevModeEnabled()) {
            $storyRequest = new StoryRequest(version: Version::Draft);
        }

        try {
            $storyData = $this->storyRepository->getBySlug($identifier, $storyRequest);

            if (!$storyData->getId()) {
                return null;
            }

            $request->setParams(['story' => $storyData]);
            $request->setModuleName('storyblok')->setControllerName('page')->setActionName('view');

            return $this->actionFactory->create(Forward::class);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
