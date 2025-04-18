<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

interface StoryRepositoryInterface
{
    /**
     * Retrieve Story by slug.
     *
     * @param string $slug
     * @return \WindAndKite\Storyblok\Model\Story
     * @throws \Exception
     */
    public function getBySlug(
        string $slug,
    ): \WindAndKite\Storyblok\Model\Story;

    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
    ): \Magento\Framework\Api\SearchResultsInterface;
}
