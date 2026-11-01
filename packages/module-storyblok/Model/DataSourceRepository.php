<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Storyblok\Api\Domain\Value\Datasource as DatasourceValue;
use Storyblok\Api\Domain\Value\DatasourceDimension;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Request\DatasourcesRequest;
use WindAndKite\Storyblok\Api\DataSourceRepositoryInterface;

class DataSourceRepository implements DataSourceRepositoryInterface
{
    public function __construct(
        private StoryblokClientWrapper $storyBlockClientWrapper,
        private DataSourceFactory $dataSourceFactory,
        private SearchResultsInterfaceFactory $searchResultsFactory
    ) {}

    public function getBySlug(
        string $slug,
    ): DataSource {
        $response = $this->storyBlockClientWrapper->getDataSourceApi()->bySlug($slug);

        return $this->createDataSource($response->datasource);
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
            $dataSources[] = $this->createDataSource($dataSource);
        }

        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($dataSources);
        $searchResults->setTotalCount($response->total->value);

        return $searchResults;
    }

    private function createDataSource(DatasourceValue $dataSource): DataSource
    {
        return $this->dataSourceFactory->create(['data' => [
            DataSource::KEY_ID => $dataSource->id->value,
            DataSource::KEY_NAME => $dataSource->name,
            DataSource::KEY_SLUG => $dataSource->slug,
            DataSource::KEY_DIMENSIONS => array_map(static fn (DatasourceDimension $dimension) => [
                'id' => $dimension->id->value,
                'name' => $dimension->name,
                'entry_value' => $dimension->entryValue,
            ], $dataSource->dimensions),
        ]]);
    }
}
