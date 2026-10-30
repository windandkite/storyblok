<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

interface DataSourceRepositoryInterface
{
    public function getBySlug(
        string $slug,
    ): \WindAndKite\Storyblok\Api\Data\DataSourceInterface;

    public function getList(
        int $page = 1,
        int $perPage = \Storyblok\Api\Request\DatasourcesRequest::PER_PAGE,
    ): \Magento\Framework\Api\SearchResultsInterface;
}
