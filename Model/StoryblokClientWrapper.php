<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Exception;
use Psr\Log\LoggerInterface;
use Storyblok\Api\AssetsApi;
use Storyblok\Api\DatasourceEntriesApi;
use Storyblok\Api\DatasourcesApi;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\LinksApi;
use Storyblok\Api\SpacesApi;
use Storyblok\Api\StoriesApi;
use Storyblok\Api\StoryblokClient;
use Storyblok\Api\TagsApi;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use WindAndKite\Storyblok\Scope\Config;

class StoryblokClientWrapper
{
    private ?StoryblokClient $client = null;
    private ?StoriesApi $storiesApi = null;
    private ?AssetsApi $assetsApi = null;
    private ?DatasourcesApi $dataSourceApi = null;
    private ?DatasourceEntriesApi $dataSourceEntriesApi = null;
    private ?LinksApi $linksApi = null;
    private ?SpacesApi $spacesApi = null;
    private ?TagsApi $tagsApi = null;

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private Config $config,
        private LoggerInterface $logger
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
     * @return StoryblokClient
     *
     * @throws Exception
     */
    private function getClient(): StoryblokClient
    {
        if (!$this->client) {
            throw new Exception('Storyblok client is not initialized.');
        }

        return $this->client;
    }

    /**
     * @return StoriesApi
     *
     * @throws Exception
     */
    public function getStoriesApi(): StoriesApi
    {
        if (!$this->storiesApi) {
            $version = $this->config->isDevModeEnabled() ? Version::Draft->value : Version::Published->value;

            $this->storiesApi = new StoriesApi($this->getClient(), $version);
        }

        return $this->storiesApi;
    }

    /**
     * @return AssetsApi
     *
     * @throws Exception
     */
    public function getAssetsApi(): AssetsApi
    {
        if (!$this->assetsApi) {
            $this->assetsApi = new AssetsApi($this->getClient());
        }

        return $this->assetsApi;
    }

    /**
     * @return DatasourcesApi
     *
     * @throws Exception
     */
    public function getDataSourceApi(): DatasourcesApi
    {
        if (!$this->dataSourceApi) {
            $this->dataSourceApi = new DatasourcesApi($this->getClient());
        }

        return $this->dataSourceApi;
    }

    /**
     * @return DataSourceEntriesApi
     *
     * @throws Exception
     */
    public function getDataSourceEntriesApi(): DataSourceEntriesApi
    {
        if (!$this->dataSourceEntriesApi) {
            $this->dataSourceEntriesApi = new DataSourceEntriesApi($this->getClient());
        }

        return $this->dataSourceEntriesApi;
    }

    /**
     * @return LinksApi
     *
     * @throws Exception
     */
    public function getLinksApi(): LinksApi
    {
        if (!$this->linksApi) {
            $version = $this->config->isDevModeEnabled() ? Version::Draft->value : Version::Published->value;
            $this->linksApi = new LinksApi($this->getClient(), $version);
        }

        return $this->linksApi;
    }

    /**
     * @return SpacesApi
     *
     * @throws Exception
     */
    public function getSpacesApi(): SpacesApi
    {
        if (!$this->spacesApi) {
           $this->spacesApi = new SpacesApi($this->getClient());
        }

        return $this->spacesApi;
    }

    /**
     * @return TagsApi
     *
     * @throws Exception
     */
    public function getTagsApi(): TagsApi
    {
        if (!$this->tagsApi) {
            $this->tagsApi = new TagsApi($this->getClient());
        }

        return $this->tagsApi;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $options
     *
     * @return ResponseInterface
     *
     * @throws TransportExceptionInterface
     */
    public function request(
        string $method,
        string $endpoint,
        array $options = []
    ): ResponseInterface {
        return $this->getClient()->request($method, $endpoint, $options);
    }
}
