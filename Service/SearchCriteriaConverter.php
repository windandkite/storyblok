<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InvalidArgumentException;
use ReflectionClass;
use Storyblok\Api\Domain\Value\Dto\Direction;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Domain\Value\Dto\SortBy;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Domain\Value\Filter\FilterCollection;
use Storyblok\Api\Domain\Value\Filter\Filters\AllInArrayFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\AnyInArrayFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\Filter as StoryblokFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\GreaterThanDateFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\GreaterThanFloatFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\GreaterThanIntFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\InFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\IsFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LessThanDateFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LessThanFloatFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LessThanIntFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\LikeFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\NotInFilter;
use Storyblok\Api\Domain\Value\Filter\Filters\NotLikeFilter;
use Storyblok\Api\Domain\Value\IdCollection;
use Storyblok\Api\Domain\Value\Resolver\ResolveLinks;
use Storyblok\Api\Domain\Value\Slug\SlugCollection;
use Storyblok\Api\Domain\Value\Tag\TagCollection;
use Storyblok\Api\Request\StoriesRequest;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;
use WindAndKite\Storyblok\Controller\Router;

class SearchCriteriaConverter
{
    private const FILTER_MAPPING = [
        StoriesSearchCriteriaInterface::STARTS_WITH => [
            'eq' => [
                'param' => StoriesSearchCriteriaInterface::STARTS_WITH,
            ],
        ],
        StoriesSearchCriteriaInterface::SEARCH_TERM => [
            'eq' => [
                'param' => StoriesSearchCriteriaInterface::SEARCH_TERM,
            ],
        ],
        'slug' => [
            'in' => [
                'param' => StoriesSearchCriteriaInterface::BY_SLUGS,
                'callback' => 'getArray'
            ],
            'nin' => [
                'param' => StoriesSearchCriteriaInterface::EXCLUDING_SLUGS,
                'callback' => 'createSlugCollection'
            ],
        ],
        'published_at' => [
            'gt' => [
                'param' => StoriesSearchCriteriaInterface::PUBLISHED_AT_GT,
            ],
            'lt' => [
                'param' => StoriesSearchCriteriaInterface::PUBLISHED_AT_LT,
            ],
        ],
        'first_published_at' => [
            'gt' => [
                'param' => StoriesSearchCriteriaInterface::FIRST_PUBLISHED_AT_GT,
            ],
            'lt' => [
                'param' => StoriesSearchCriteriaInterface::FIRST_PUBLISHED_AT_LT,
            ],
        ],
        'updated_at' => [
            'gt' => [
                'param' => StoriesSearchCriteriaInterface::UPDATED_AT_GT,
            ],
            'lt' =>[
                'param' => StoriesSearchCriteriaInterface::UPDATED_AT_LT,
            ],
        ],
        'workflow_stage' => [
            'in' => [
                'param' => StoriesSearchCriteriaInterface::IN_WORKFLOW_STAGES,
                'callback' => 'getArray'
            ],
        ],
        StoriesSearchCriteriaInterface::CONTENT_TYPE => [
            'eq' => [
                'param' => StoriesSearchCriteriaInterface::CONTENT_TYPE,
            ],
        ],
        StoriesSearchCriteriaInterface::LEVEL => [
            'eq' => [
                'param' => StoriesSearchCriteriaInterface::LEVEL,
            ],
        ],
        'id' => [
            'nin' => [
                'param' => StoriesSearchCriteriaInterface::EXCLUDING_IDS,
                'callback' => 'getIdCollection'
            ],
        ],
        'uuid' => [
            'in' => [
                'param' => StoriesSearchCriteriaInterface::BY_UUIDS,
                'callback' => 'getArray'
            ]
        ],
        'tag' => [
            'in' => [
                'param' => StoriesSearchCriteriaInterface::WITH_TAG,
                'callback' => 'getTagCollection'
            ],
        ],
        StoriesSearchCriteriaInterface::IS_STARTPAGE => [
            'eq' => [
                'param' => StoriesSearchCriteriaInterface::IS_STARTPAGE,
            ],
        ],
        'release' => [
            'eq' => [
                'param' => StoriesSearchCriteriaInterface::FROM_RELEASE,
            ],
        ],
    ];

    private array $storyFields = [];

    public function __construct(
        private \WindAndKite\Storyblok\Scope\Config $scopeConfig,
        private StoryblokSessionManager $storyblokSessionManager,
        private RequestInterface $request,
    ) {}

    /**
     * @param StoriesSearchCriteriaInterface $searchCriteria
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function convert(
        StoriesSearchCriteriaInterface $searchCriteria,
    ): array {
        $filterGroups = $searchCriteria->getFilterGroups();
        $standardFilters = [];
        $specialParams = [];

        foreach ($filterGroups as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                if (in_array($filter->getField(), array_keys(self::FILTER_MAPPING))) {
                    [$param, $value] = $this->processFilter($filter);

                    if ($param && isset($value)) {
                        $specialParams[$param] = $value;
                    }
                } else {
                    $standardFilters[] = $this->createStandardFilter($filter);
                }
            }
        }

        $version = $data['version'] ?? $this->storyblokSessionManager->getStoryblokApiVersion();


        $request = new StoriesRequest(
            $searchCriteria->getLanguage(),
            new Pagination(
                page: $searchCriteria->getCurrentPage() ?? 1,
                perPage: $searchCriteria->getPageSize() ?? 25
            ),
            $this->getSortBy($searchCriteria),
            new FilterCollection($standardFilters),
            $searchCriteria->getExcludeFields(),
            $this->getAndRemoveArrayItem($specialParams, StoriesSearchCriteriaInterface::WITH_TAG) ?? new TagCollection(),
            $this->getAndRemoveArrayItem($specialParams, StoriesSearchCriteriaInterface::EXCLUDING_IDS) ?? new IdCollection(),
            $searchCriteria->getResolvedRelations(),
            $version,
            $this->getAndRemoveArrayItem($specialParams, StoriesSearchCriteriaInterface::SEARCH_TERM),
            new ResolveLinks($searchCriteria->getResolvedLinksType(), $searchCriteria->getResolvedLinksLevel()),
            $this->getAndRemoveArrayItem($specialParams, StoriesSearchCriteriaInterface::EXCLUDING_SLUGS) ?? new SlugCollection(),
        );

        return [$request, $specialParams];
    }

    private function getAndRemoveArrayItem(
        array &$array,
        string $key
    ) {
        if (isset($array[$key])) {
            $value = $array[$key];
            unset($array[$key]);

            return $value;
        }

        return null;
    }

    /**
     * Get the sort orders from the search criteria.
     *
     * @return SortBy|null
     */
    private function getSortBy(
        StoriesSearchCriteriaInterface $searchCriteria,
    ): ?SortBy {
        $sortOrders = $searchCriteria->getSortOrders();

        if ($sortOrders && count($sortOrders) > 0) {
            $sortOrder = reset($sortOrders);
            $field = $sortOrder->getField();

            if (!in_array($field, $this->getStoryFields(), true)) {
                $field = 'content.' . $field;
            }

            $direction = $sortOrder->getDirection();

            return new SortBy(
                field: $field,
                direction: Direction::from(strtolower($direction))
            );
        }

        return null;
    }

    private function processFilter(
        Filter $filter,
    ) {
        $param = null;
        $value = null;

        $mapping = self::FILTER_MAPPING[$filter->getField()] ?? null;

        if ($mapping) {
            $conditionType = $filter->getConditionType() ?: 'eq';
            $param = $mapping[$conditionType]['param'] ?? null;
            $callback = $mapping[$conditionType]['callback'] ?? null;

            if (!$param) {
                return [null, null];
            }

            if ($callback && method_exists($this, $callback)) {
                $value = $this->{$callback}($filter->getValue());
            } else {
                $value = $filter->getValue();
            }
        }

        return [$param, $value];
    }

    private function getArray(
        $value
    ): array {
        if (is_array($value)) {
            return $value;
        }

        return array_map('trim', explode(',', (string)$value ?? ''));
    }

    private function createSlugCollection(
        $value
    ): SlugCollection {
        $slugs = $this->getArray($value);

        return new SlugCollection($slugs);
    }

    private function getIdCollection(
        $value
    ): IdCollection {
        $ids = $this->getArray($value);

        return new IdCollection($ids);
    }

    private function getTagCollection(
        $value
    ): TagCollection {
        $tags = $this->getArray($value);

        return new TagCollection($tags);
    }

    /**
     * Convert Magento filter to Storyblok filter.
     *
     * @param Filter $filter
     *
     * @return StoryblokFilter
     * @throws InvalidArgumentException
     */
    private function createStandardFilter(
        Filter $filter
    ): StoryblokFilter {
        $field = $filter->getField();
        $value = $filter->getValue();
        $conditionType = $filter->getConditionType() ?: 'eq';

        return match (strtolower($conditionType)) {
            'eq', 'is' => new IsFilter(field: $field, value: $value),
            'like' => new LikeFilter(field: $field, value: $value),
            'notlike', 'not_like' => new NotLikeFilter(field: $field, value: $value),
            'in' => new InFilter(field: $field, value: explode(',', $value)),
            'notin', 'not_in' => new NotInFilter(field: $field, value: explode(',', $value)),
            'gt', 'greaterthan' => $this->getGreaterThanFilter($field, $value),
            'lt', 'lessthan' => $this->getLessThanFilter($field, $value),
            'allin', 'all_in' => new AllInArrayFilter(field: $field, value: explode(',', $value)),
            'anyin', 'any_in' => new AnyInArrayFilter(field: $field, value: explode(',', $value)),
            default => throw new InvalidArgumentException(
                __('Unsupported filter condition type: %1 for field: %2', $conditionType, $field)
            ),
        };
    }

    /**
     * @param string $field
     * @param $value
     *
     * @return StoryblokFilter
     *
     * @throws InvalidArgumentException
     */
    private function getGreaterThanFilter(
        string $field,
        $value,
    ): StoryblokFilter {
        if (!is_numeric($value) && strtotime($value) === false) {
            throw new InvalidArgumentException(
                __('Unsupported data (%1) type for Greater Than filter for field: %2', $value, $field)
            );
        }

        if (is_numeric($value)) {
            return is_int($value) || ctype_digit(strval($value))
                ? new GreaterThanIntFilter(field: $field, value: (int) $value)
                : new GreaterThanFloatFilter(field: $field, value: (float) $value);
        }

        return new GreaterThanDateFilter(field: $field, value: date_create($value));
    }

    /**
     * @param string $field
     * @param $value
     *
     * @return StoryblokFilter
     *
     * @throws InvalidArgumentException
     */
    private function getLessThanFilter(
        string $field,
        $value,
    ): StoryblokFilter {
        if (!is_numeric($value) && strtotime($value) === false) {
            throw new InvalidArgumentException(
                __('Unsupported data (%1) type for Less Than filter for field: %2', $value, $field)
            );
        }

        if (is_numeric($value)) {
            return is_int($value) || ctype_digit(strval($value))
                ? new LessThanIntFilter(field: $field, value: (int) $value)
                : new LessThanFloatFilter(field: $field, value: (float) $value);
        }

        return new LessThanDateFilter(field: $field, value: date_create($value));
    }

    /**
     * Get the story fields from the StoryInterface class.
     *
     * @return array
     */
    private function getStoryFields(): array
    {
        if (!$this->storyFields) {
            $reflection = new ReflectionClass(StoryInterface::class);
            $this->storyFields = array_values(
                array_filter(
                    $reflection->getConstants(),
                    fn($key) => str_starts_with($key, 'KEY_'),
                    ARRAY_FILTER_USE_KEY
                )
            );
        }

        return $this->storyFields;
    }
}
