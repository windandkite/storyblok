<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

interface StoryRepositoryInterface
{
    public function getBySlug(
        string $slug,
    ): \WindAndKite\Storyblok\Api\Data\StoryInterface;

    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
        array $additionalFilters = [],
    ): \Magento\Framework\Api\SearchResultsInterface;
}
