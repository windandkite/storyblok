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
    /**
     * @var StoryblokClient[] One per token and region, so each website can use its own space.
     */
    private array $clients = [];

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private Config $config,
        private LoggerInterface $logger
    ) {}

    /**
     * The client for the current store's space. Resolved on each call, so store emulation (e.g. sitemaps) uses the
     * emulated store's token.
     *
     * @return StoryblokClient
     *
     * @throws Exception
     */
    private function getClient(): StoryblokClient
    {
        $apiToken = $this->config->isModuleEnabled() ? (string)$this->config->getApiToken() : '';

        if ($apiToken === '') {
            throw new Exception('Storyblok API Token is not configured.');
        }

        $baseUrl = $this->config->getApiBaseUrl();

        return $this->clients[$baseUrl . '|' . $apiToken] ??= new StoryblokClient($baseUrl, $apiToken);
    }

    /**
     * @return StoriesApi
     *
     * @throws Exception
     */
    public function getStoriesApi(): StoriesApi
    {
        return new StoriesApi($this->getClient(), $this->getDefaultVersion());
    }

    /**
     * @return AssetsApi
     *
     * @throws Exception
     */
    public function getAssetsApi(): AssetsApi
    {
        return new AssetsApi($this->getClient());
    }

    /**
     * @return DatasourcesApi
     *
     * @throws Exception
     */
    public function getDataSourceApi(): DatasourcesApi
    {
        return new DatasourcesApi($this->getClient());
    }

    /**
     * @return DataSourceEntriesApi
     *
     * @throws Exception
     */
    public function getDataSourceEntriesApi(): DataSourceEntriesApi
    {
        return new DataSourceEntriesApi($this->getClient());
    }

    /**
     * @return LinksApi
     *
     * @throws Exception
     */
    public function getLinksApi(): LinksApi
    {
        return new LinksApi($this->getClient(), $this->getDefaultVersion());
    }

    /**
     * @return SpacesApi
     *
     * @throws Exception
     */
    public function getSpacesApi(): SpacesApi
    {
        return new SpacesApi($this->getClient());
    }

    /**
     * @return TagsApi
     *
     * @throws Exception
     */
    public function getTagsApi(): TagsApi
    {
        return new TagsApi($this->getClient());
    }

    private function getDefaultVersion(): string
    {
        return $this->config->isDevModeEnabled() ? Version::Draft->value : Version::Published->value;
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
