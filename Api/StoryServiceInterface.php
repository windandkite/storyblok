<?php

namespace WindAndKite\Storyblok\Api;

interface StoryServiceInterface
{
    /**
     * Get a Storyblok story by its slug.
     *
     * @param string $slug
     * @param \Storyblok\Api\Request\StoryRequest|null $request
     *
     * @return \Storyblok\Api\Response\StoryResponse
     */
    public function getBySlug(
        string $slug,
        ?\Storyblok\Api\Request\StoryRequest $request = null,
    ): \Storyblok\Api\Response\StoryResponse;

    /**
     * Get multiple Storyblok stories.
     *
     * @param \Storyblok\Api\Request\StoriesRequest|null $request
     *
     * @return \Storyblok\Api\Response\StoriesResponse
     */
    public function getStories(
        ?\Storyblok\Api\Request\StoriesRequest $request = null,
    ): \Storyblok\Api\Response\StoriesResponse;
}
