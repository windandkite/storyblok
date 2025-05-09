<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Exception;
use Laminas\Http\Request;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\InvalidArgumentException;
use ReflectionClass;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Domain\Value\Field\FieldCollection;
use Storyblok\Api\Domain\Value\Id;
use Storyblok\Api\Domain\Value\Total;
use Storyblok\Api\Domain\Value\Uuid;
use Storyblok\Api\Request\StoryRequest;
use Storyblok\Api\Response\StoriesResponse;
use Storyblok\Api\Response\StoryResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Storyblok\Api\Request\StoriesRequest;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Domain\Value\Dto\SortBy;
use Storyblok\Api\Domain\Value\Dto\Direction;
use Storyblok\Api\Domain\Value\Filter\Filters\IsFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LikeFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\NotLikeFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\InFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\NotInFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\GreaterThanIntFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\GreaterThanFloatFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\GreaterThanDateFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LessThanIntFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LessThanFloatFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LessThanDateFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\AllInArrayFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\AnyInArrayFilter;
use Storyblok\Api\Domain\Value\Filter\FilterCollection;
use Storyblok\Api\Domain\Value\Tag\TagCollection;
use Storyblok\Api\Domain\Value\IdCollection;
use Storyblok\Api\Domain\Value\Resolver\RelationCollection;
use Storyblok\Api\Domain\Value\Resolver\ResolveLinks;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\SearchCriteriaConverter;

class StoryRepository implements StoryRepositoryInterface
{
    private const API_ENDPOINT = '/v2/cdn/stories';

    /**
     * @param StoryblokClientWrapper $storyBlockClientWrapper
     * @param StoryFactory $storyFactory
     * @param LoggerInterface $logger
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param SearchCriteriaConverter $searchCriteriaConverter
     */
    public function __construct(
        private StoryblokClientWrapper $storyBlockClientWrapper,
        private StoryFactory $storyFactory,
        private LoggerInterface $logger,
        private SearchResultsInterfaceFactory $searchResultsFactory,
        private SearchCriteriaConverter $searchCriteriaConverter,
    ) {}

    private function rawRequest(
        StoriesRequest $storiesRequest,
        array $additionalFilters = []
    ) {
        $rawResponse = $this->storyBlockClientWrapper->request(
            Request::METHOD_GET,
            self::API_ENDPOINT,
            [
                'query' => [
                    ...$storiesRequest->toArray(),
                    ...$additionalFilters,
                ],
            ]
        );

        return new StoriesResponse(
            Total::fromHeaders($rawResponse->getHeaders()),
            $storiesRequest->pagination,
            $rawResponse->toArray(),
        );
    }

    private function convertStoryResponse(
        StoryResponse $response
    ): StoryInterface {
        $storyData = $response->story;
        $storyData[StoryInterface::KEY_CACHE_VERSION] = $response->cv;
        $storyData[StoryInterface::KEY_RELS] = $response->rels;
        $story = $this->storyFactory->create();
        $story->setData($storyData);

        return $story;
    }

    private function convertStoriesResponse(
        StoriesResponse $response,
        SearchCriteriaInterface $searchCriteria,
    ): SearchResultsInterface {
        $stories = [];

        foreach ($response->stories as $storyData) {
            $story = $this->storyFactory->create();
            $story->setData($storyData);
            $stories[] = $story;
        }

        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($stories);
        $searchResults->setTotalCount($response->total->value);
        $searchResults->setSearchCriteria($searchCriteria);

        return $searchResults;
    }

    /**
     * Retrieve Story by slug.
     *
     * @param string $slug
     * @param StoryRequest|null $request
     *
     * @return StoryInterface
     * @throws NoSuchEntityException
     */
    public function getBySlug(
        string $slug,
        ?StoryRequest $request = null,
    ): StoryInterface {
        try {
            $response = $this->storyBlockClientWrapper->getStoriesApi()->bySlug($slug, $request);

            return $this->convertStoryResponse($response);
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());

            throw new NoSuchEntityException(__('Story with slug "%1" not found.', $slug));
        }
    }

    /**
     * @param int $id
     * @param StoryRequest|null $request
     *
     * @return StoryInterface
     * @throws NoSuchEntityException
     */
    public function getById(
        int $id,
        ?StoryRequest $request = null,
    ): StoryInterface {
        try {
            $storyId = new Id($id);
            $response = $this->storyBlockClientWrapper->getStoriesApi()->byId($storyId, $request);

            return $this->convertStoryResponse($response);
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());

            throw new NoSuchEntityException(__('Story with ID "%1" not found.', $id));
        }
    }

    public function getByUuid(
        string $uuid,
        ?StoryRequest $request = null,
    ): StoryInterface {
        try {
            $storyUuid = new Uuid($uuid);
            $response = $this->storyBlockClientWrapper->getStoriesApi()->byUuid($storyUuid, $request);

            return $this->convertStoryResponse($response);
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());

            throw new NoSuchEntityException(__('Story with UUID "%1" not found.', $uuid));
        }
    }

    /**
     * Retrieve stories matching the specified criteria.
     *
     * @param StoriesSearchCriteriaInterface $searchCriteria
     *
     * @return SearchResultsInterface
     * @throws InvalidArgumentException
     */
    public function getList(
        StoriesSearchCriteriaInterface $searchCriteria,
    ): SearchResultsInterface {
        [$storiesRequest, $additionalFilters] = $this->searchCriteriaConverter->convert($searchCriteria);

        if (!$additionalFilters) {
            $response = $this->storyBlockClientWrapper->getStoriesApi()->all($storiesRequest);
        } else {
            $response = $this->rawRequest($storiesRequest, $additionalFilters);
        }

        return $this->convertStoriesResponse($response, $searchCriteria);
    }

    public function getListByContentType(
        string $contentType,
        StoriesSearchCriteriaInterface $searchCriteria,
    ): SearchResultsInterface {
        [$storiesRequest, $additionalFilters] = $this->searchCriteriaConverter->convert($searchCriteria);

        if (!$additionalFilters) {
            $response = $this->storyBlockClientWrapper->getStoriesApi()->allByContentType($contentType, $storiesRequest);
        } else {
            $additionalFilters['content_type'] = $contentType;
            $response = $this->rawRequest($storiesRequest, $additionalFilters);
        }

        return $this->convertStoriesResponse($response, $searchCriteria);
    }

    public function getListByUuids(
        array $uuids,
        StoriesSearchCriteriaInterface $searchCriteria,
        bool $keepOrder = true,
    ): SearchResultsInterface {
        [$storiesRequest, $additionalFilters] = $this->searchCriteriaConverter->convert($searchCriteria);

        if (!$additionalFilters) {
            $response = $this->storyBlockClientWrapper
                ->getStoriesApi()
                ->allByUuids($uuids, $keepOrder, $storiesRequest);
        } else {
            $additionalFilters[$keepOrder ? 'by_uuids' : 'by_uuids_ordered'] = $uuids;
            $response = $this->rawRequest($storiesRequest, $additionalFilters);
        }

        return $this->convertStoriesResponse($response, $searchCriteria);
    }
}
