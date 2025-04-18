<?php

namespace WindAndKite\Storyblok\Model;

use Exception;
use Psr\Log\LoggerInterface;
use Storyblok\Api\AssetsApi;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Request\StoriesRequest;
use Storyblok\Api\Request\StoryRequest;
use Storyblok\Api\Response\AssetResponse;
use Storyblok\Api\Response\StoriesResponse;
use Storyblok\Api\Response\StoryResponse;
use Storyblok\Api\StoriesApi;
use Storyblok\Api\StoryblokClient;
use WindAndKite\Storyblok\Scope\Config;

class StoryblokClientWrapper
{
    private ?StoryblokClient $client = null;
    private ?StoriesApi $storiesApi = null;
    private ?AssetsApi $assetsApi = null;

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
    private function getStoriesApi(): StoriesApi
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
    private function getAssetsApi(): AssetsApi
    {
        if (!$this->client) {
            throw new Exception('Storyblok client is not initialized.');
        }

        if (!$this->assetsApi) {
            $this->assetsApi = new AssetsApi($this->client);
        }

        return $this->assetsApi;
    }

    /**
     * Get a Storyblok story by its slug.
     *
     * @param string $slug
     * @param StoryRequest|null $request
     *
     * @return StoryResponse
     * @throws Exception
     */
    public function getStory(
        string $slug,
        ?StoryRequest $request = null,
    ): StoryResponse {
        return $this->getStoriesApi()->bySlug($slug, $request);
    }

    /**
     * Get multiple Storyblok stories.
     *
     * @param StoriesRequest|null $request
     *
     * @return StoriesResponse
     * @throws Exception
     */
    public function getStories(
        ?StoriesRequest $request = null,
    ): StoriesResponse {
        return $this->getStoriesApi()->all($request);
    }

    /**
     * Get details about a Storyblok asset by its filename.
     *
     * @param string $filename
     *
     * @return AssetResponse
     * @throws Exception
     */
    public function getAssetByFilename(
        string $filename
    ): AssetResponse {
        return $this->getAssetsApi()->get($filename);
    }
}
