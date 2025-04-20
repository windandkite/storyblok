<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Request\DatasourcesRequest;
use WindAndKite\Storyblok\Api\DataSourceRepositoryInterface;

class DataSourceRepository implements DataSourceRepositoryInterface
{
    public function __construct(
        private readonly StoryblokClientWrapper $storyBlockClientWrapper,
        private readonly DataSourceFactory $dataSourceFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {}

    public function getBySlug(
        string $slug,
    ): DataSource {
        $dataSource = $this->storyBlockClientWrapper->getDataSourceApi()->bySlug($slug);

        return $this->dataSourceFactory->create($dataSource);
    }

    public function getList(
        int $page = 1,
        int $perPage = DatasourcesRequest::PER_PAGE,
    ): SearchResultsInterface {
        if ($page !== 1 || $perPage !== DatasourcesRequest::PER_PAGE) {
            $request = new DatasourcesRequest(pagination: new Pagination(page: $page, perPage: $perPage));
        } else {
            $request = new DatasourcesRequest();
        }

        $response = $this->storyBlockClientWrapper->getDataSourceApi()->all($request);
        $dataSources = [];

        foreach ($response->datasources as $dataSource) {
            $dataSource = $this->dataSourceFactory->create($dataSource);
            $dataSources[] = $dataSource;
        }

        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($dataSources);
        $searchResults->setTotalCount(count($dataSources));

        return $searchResults;
    }
}
