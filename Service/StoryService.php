<?php

namespace WindAndKite\Storyblok\Service;

use Exception;
use Storyblok\Api\Request\StoriesRequest;
use Storyblok\Api\Request\StoryRequest;
use Storyblok\Api\Response\StoriesResponse;
use Storyblok\Api\Response\StoryResponse;
use WindAndKite\Storyblok\Api\StoryServiceInterface;

class StoryService extends AbstractService implements StoryServiceInterface
{
    /**
     * @inheritDoc
     * @throws Exception
     */
    public
    function getBySlug(
        string $slug,
        ?StoryRequest $request = null,
    ): StoryResponse {
        return $this->storyblokClientWrapper->getStory($slug, $request);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public
    function getStories(
       ?StoriesRequest $request = null,
    ): StoriesResponse {
        return $this->storyblokClientWrapper->getStories($request);
    }
}
