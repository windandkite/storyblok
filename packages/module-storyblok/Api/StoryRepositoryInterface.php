<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

interface StoryRepositoryInterface
{
    public function getBySlug(
        string $slug,
        ?\Storyblok\Api\Request\StoryRequest $request = null,
    ): \WindAndKite\Storyblok\Api\Data\StoryInterface;

    public function getById(
        int $id,
        ?\Storyblok\Api\Request\StoryRequest $request = null,
    ): \WindAndKite\Storyblok\Api\Data\StoryInterface;

    public function getByUuid(
        string $uuid,
        ?\Storyblok\Api\Request\StoryRequest $request = null,
    ): \WindAndKite\Storyblok\Api\Data\StoryInterface;

    public function getList(
        \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface $searchCriteria,
    ): \Magento\Framework\Api\SearchResultsInterface;

    public function getListByContentType(
        string $contentType,
        \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface $searchCriteria,
    ): \Magento\Framework\Api\SearchResultsInterface;

    public function getListByUuids(
        array $uuids,
        \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface $searchCriteria,
        bool $keepOrder = true,
    ): \Magento\Framework\Api\SearchResultsInterface;


}
