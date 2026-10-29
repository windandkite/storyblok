<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

use Magento\Framework\Api\SearchCriteria;
use Storyblok\Api\Domain\Value\Dto\Pagination;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Domain\Value\Field\FieldCollection;
use Storyblok\Api\Domain\Value\Resolver\LinkLevel;
use Storyblok\Api\Domain\Value\Resolver\LinkType;
use Storyblok\Api\Domain\Value\Resolver\RelationCollection;
use Storyblok\Api\Request\StoriesRequest;

class StoriesSearchCriteria extends SearchCriteria implements StoriesSearchCriteriaInterface
{
    /**
     * @inheritDoc
     */
    public function getLanguage(): string
    {
        return $this->_get(self::LANGUAGE) ?? self::DEFAULT_LANGUAGE;
    }

    /**
     * @inheritDoc
     */
    public function setLanguage(
        string $language
    ): StoriesSearchCriteriaInterface {
        return $this->setData(self::LANGUAGE, $language);
    }

    /**
     * @inheritDoc
     */
    public function getExcludeFields(): FieldCollection
    {
        $excludeFields = $this->_get(self::EXCLUDING_FIELDS) ?? [];

        return new FieldCollection($excludeFields);
    }

    /**
     * @inheritDoc
     */
    public function setExcludeFields(
        ?array $excludeFields
    ): StoriesSearchCriteriaInterface {
        return $this->setData(self::EXCLUDING_FIELDS, $excludeFields);
    }

    /**
     * @inheritDoc
     */
    public function getResolvedRelations(): RelationCollection
    {
        $relations = $this->_get(self::RESOLVE_RELATIONS) ?? [];

        return new RelationCollection($relations);
    }

    /**
     * @inheritDoc
     */
    public function setResolvedRelations(
        array $relations
    ): StoriesSearchCriteriaInterface {
        return $this->setData(self::RESOLVE_RELATIONS, $relations);
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): ?Version
    {
        return $this->_get(self::VERSION) ?? null;
    }

    /**
     * @inheritDoc
     */
    public function setVersion(
        Version $version
    ): StoriesSearchCriteriaInterface {
        return $this->setData(self::VERSION, $version);
    }

    /**
     * @inheritDoc
     */
    public function getResolvedLinksType(): ?LinkType
    {
        return $this->_get(self::RESOLVE_LINKS);
    }

    /**
     * @inheritDoc
     */
    public function setResolvedLinksType(
        LinkType $type,
    ): StoriesSearchCriteriaInterface {
        return $this->setData(self::RESOLVE_LINKS, $type);
    }

    /**
     * @inheritDoc
     */
    public function getResolvedLinksLevel(): LinkLevel
    {
        return $this->_get(self::RESOLVE_LINKS_LEVEL) ?? LinkLevel::Default;
    }

    /**
     * @inheritDoc
     */
    public function setResolvedLinksLevel(
        LinkLevel $level
    ): StoriesSearchCriteriaInterface {
        return $this->setData(self::RESOLVE_LINKS_LEVEL, $level);
    }

    /**
     * @inheritDoc
     */
    public function convertToRequest(): StoriesRequest
    {

        return new StoriesRequest(
            $this->getLanguage(),
            new Pagination(
                page: $this->getCurrentPage(),
                perPage: $this->getPageSize()
            ),
            $this->getSortBy(),
            $this->filterConverter->convertFilters($this),
            $this->getExcludeFields(),
            $this->getWithTags(),
            $this->getExcludeIds(),
            $this->getResolvedRelations(),
            $this->getVersion(),
            $this->getSearchTerm(),
            $this->getResolvedLinksType(),
            $this->getExcludeSlugs()
        );
    }

    public function getSpecialFilters(): array
    {
        $specialFilters = [];

        foreach ($this->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                if (in_array($filter->getField(), self::SPECIAL_FILTERS, true)) {
                    $specialFilters[$filter->getField()] = $filter->getValue();
                }
            }
        }

        return $specialFilters;
    }
}
