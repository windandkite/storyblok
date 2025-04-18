<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Exception;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchResultsInterface;
use Storyblok\Api\Domain\Value\Field\FieldCollection;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use WindAndKite\Storyblok\Api\StoryServiceInterface;
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

class StoryRepository implements StoryRepositoryInterface
{
    /**
     * @param StoryServiceInterface $storyService
     * @param StoryFactory $storyFactory
     * @param LoggerInterface $logger
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        private readonly StoryServiceInterface $storyService,
        private readonly StoryFactory $storyFactory,
        private readonly LoggerInterface $logger,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {}

    /**
     * Retrieve Story by slug.
     *
     * @param string $slug
     *
     * @return Story
     * @throws NoSuchEntityException
     */
    public function getBySlug(string $slug): Story
    {
        try {
            $storyData = $this->storyService->getBySlug($slug)->story;
            $story = $this->storyFactory->create();
            $story->setData($storyData);

            return $story;
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());

            throw new NoSuchEntityException(__('Story with slug "%1" not found.', $slug));
        }
    }

    /**
     * Retrieve stories matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
    ): SearchResultsInterface {
        $storiesRequest = $this->convertSearchCriteriaToStoriesRequest($searchCriteria);
        $storiesData = $this->storyService->getStories($storiesRequest);
        $stories = [];

        foreach ($storiesData as $storyData) {
            $story = $this->storyFactory->create();
            $story->setData($storyData);
            $stories[] = $story;
        }

        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($stories);
        $searchResults->setTotalCount(count($stories));
        $searchResults->setSearchCriteria($searchCriteria);

        return $searchResults;
    }

    /**
     * Converts Magento's SearchCriteriaInterface to Storyblok's StoriesRequest.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return StoriesRequest
     */
    private function convertSearchCriteriaToStoriesRequest(SearchCriteriaInterface $searchCriteria): StoriesRequest
    {
        $args = $this->initializeStoriesRequestArgs();

        $this->handlePagination($searchCriteria, $args);
        $this->handleSorting($searchCriteria, $args);
        $this->handleFilters($searchCriteria, $args);

        return new StoriesRequest(...$args);
    }

    /**
     * Initializes the base arguments for the StoriesRequest.
     *
     * @return array
     */
    private function initializeStoriesRequestArgs(): array
    {
        return [
            'language' => 'default', // Or get from config
            'pagination' => new Pagination(page: 1, perPage: StoriesRequest::PER_PAGE),
            'sortBy' => null,
            'filters' => [],
            'excludeFields' => new FieldCollection(),
            'withTags' => new TagCollection(),
            'excludeIds' => new IdCollection(),
            'withRelations' => new RelationCollection(),
            'resolveLinks' => new ResolveLinks(),
            'version' => null,
            'searchTerm' => null,
        ];
    }

    /**
     * Handles pagination logic from SearchCriteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param array                 &$args
     */
    private function handlePagination(
        SearchCriteriaInterface $searchCriteria,
        array &$args,
    ): void {
        $pageSize = $searchCriteria->getPageSize();
        $currentPage = $searchCriteria->getCurrentPage();

        if ($pageSize !== null || $currentPage !== null) {
            $args['pagination'] = new Pagination(
                page: $currentPage ?? 1, perPage: $pageSize ?? StoriesRequest::PER_PAGE
            );
        }
    }

    /**
     * Handles sorting logic from SearchCriteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param array                 &$args
     */
    private function handleSorting(
        SearchCriteriaInterface $searchCriteria,
        array &$args
    ): void {
        $sortOrders = $searchCriteria->getSortOrders();
        if ($sortOrders && count($sortOrders) > 0) {
            $sortOrder = reset($sortOrders);
            $field = $sortOrder->getField();
            $direction = $sortOrder->getDirection();
            $args['sortBy'] = new SortBy(
                field: $field, direction: Direction::from(strtolower($direction))
            );
        }
    }

    /**
     * Handles filter logic from SearchCriteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param array                 &$args
     */
    private function handleFilters(
        SearchCriteriaInterface $searchCriteria,
        array &$args
    ): void {
        $filterGroups = $searchCriteria->getFilterGroups();

        if ($filterGroups) {
            $filters = [];

            foreach ($filterGroups as $filterGroup) {
                foreach ($filterGroup->getFilters() as $filter) {
                    $this->addFilter($filter, $filters);
                }
            }

            if (!empty($filters)) {
                $args['filters'] = new FilterCollection($filters);
            }
        }
    }

    /**
     * Adds a single filter to the filters array based on its condition type.
     *
     * @param Filter $filter
     * @param array &$filters
     */
    private function addFilter(
        Filter $filter,
        array &$filters
    ): void {
        $field = $filter->getField();
        $value = $filter->getValue();
        $conditionType = $filter->getConditionType() ?: 'eq';

        switch (strtolower($conditionType)) {
            case 'eq':
            case 'is':
                $filters[] = new IsFilter(field: $field, value: $value);
                break;
            case 'like':
                $filters[] = new LikeFilter(field: $field, value: $value);
                break;
            case 'notlike':
            case 'not_like':
                $filters[] = new NotLikeFilter(field: $field, value: $value);
                break;
            case 'in':
                $filters[] = new InFilter(field: $field, value: explode(',', $value));
                break;
            case 'notin':
            case 'not_in':
                $filters[] = new NotInFilter(field: $field, value: explode(',', $value));
                break;
            case 'gt':
            case 'greaterthan':
                $this->addGreaterThanFilter($field, $value, $filters);
                break;
            case 'lt':
            case 'lessthan':
                $this->addLessThanFilter($field, $value, $filters);
                break;
            case 'allin':
            case 'all_in':
                $filters[] = new AllInArrayFilter(field: $field, value: explode(',', $value));
                break;
            case 'anyin':
            case 'any_in':
                $filters[] = new AnyInArrayFilter(field: $field, value: explode(',', $value));
                break;
            default:
                $this->logger->warning(
                    sprintf('Unsupported filter condition type: %s for field: %s', $conditionType, $field)
                );
                break;
        }
    }

    /**
     * Adds a greater than filter based on the value type.
     *
     * @param string $field
     * @param mixed  $value
     * @param array  &$filters
     */
    private function addGreaterThanFilter(string $field, $value, array &$filters): void
    {
        if (!is_numeric($value) && strtotime($value) === false) {
            $this->logger->warning(
                sprintf('Unsupported data (%s) type for Greater Than filter for field: %s', $value, $field)
            );

            return;
        }

        if (is_numeric($value)) {
            $filters[] = is_int($value) || ctype_digit(strval($value))
                ? new GreaterThanIntFilter(field: $field, value: (int) $value)
                : new GreaterThanFloatFilter(field: $field, value: (float) $value);

            return;
        }

        $filters[] = new GreaterThanDateFilter(field: $field, value: $value);
    }

    /**
     * Adds a less than filter based on the value type.
     *
     * @param string $field
     * @param mixed  $value
     * @param array  &$filters
     */
    private function addLessThanFilter(string $field, $value, array &$filters): void
    {
        if (!is_numeric($value) && strtotime($value) === false) {
            $this->logger->warning(
                sprintf('Unsupported data (%s) type for Less Than filter for field: %s', $value, $field)
            );

            return;
        }

        if (is_numeric($value)) {
            $filters[] = is_int($value) || ctype_digit(strval($value))
                ? new LessThanIntFilter(field: $field, value: (int) $value)
                : new LessThanFloatFilter(field: $field, value: (float) $value);

            return;
        }

        $filters[] = new LessThanDateFilter(field: $field, value: $value);
    }
}
