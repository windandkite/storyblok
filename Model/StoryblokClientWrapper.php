<?php

namespace WindAndKite\Storyblok\Model;

use Exception;
use Psr\Log\LoggerInterface;
use Storyblok\Api\AssetsApi;
use Storyblok\Api\DatasourcesApi;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\StoriesApi;
use Storyblok\Api\StoryblokClient;
use WindAndKite\Storyblok\Scope\Config;

class StoryblokClientWrapper
{
    private ?StoryblokClient $client = null;
    private ?StoriesApi $storiesApi = null;
    private ?AssetsApi $assetsApi = null;

    private ?DatasourcesApi $dataSourceApi = null;

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
        if (!$this->config->isModuleEnabled()) {
            return;
        }

        $apiToken = $this->config->getApiToken();

        if (!$apiToken) {
            $this->logger->warning('Storyblok API Token is not configured.');

            return;
        }

        $this->client = new StoryblokClient('https://api.storyblok.com', $apiToken);
    }

    /**
     * @return StoriesApi
     * @throws Exception
     */
    public function getStoriesApi(): StoriesApi
    {
        if (!$this->client) {
            throw new Exception('Storyblok client is not initialized.');
        }

        if (!$this->storiesApi) {
            $version = $this->config->isDevModeEnabled() ? Version::Draft->value : Version::Published->value;

            $this->storiesApi = new StoriesApi($this->client, $version);
        }

        return $this->storiesApi;
    }

    /**
     * @return AssetsApi
     * @throws Exception
     */
    public function getAssetsApi(): AssetsApi
    {
        if (!$this->client) {
            throw new Exception('Storyblok client is not initialized.');
        }

        if (!$this->assetsApi) {
            $this->assetsApi = new AssetsApi($this->client);
        }

        return $this->assetsApi;
    }

    public function getDataSourceApi(): DatasourcesApi
    {
        if (!$this->client) {
            throw new Exception('Storyblok client is not initialized.');
        }

        if (!$this->dataSourceApi) {
            $this->dataSourceApi = new DatasourcesApi($this->client);
        }

        return $this->dataSourceApi;
    }
}
