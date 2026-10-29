<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface StoriesSearchCriteriaInterface extends SearchCriteriaInterface
{
    // Storyblok API Params
    public const STARTS_WITH = 'starts_with';
    public const SEARCH_TERM = 'search_term';
    public const SORTY_BY = 'sort_by';
    public const PER_PAGE = 'per_page';
    public const PAGE = 'page';
    public const BY_SLUGS = 'by_slugs';
    public const EXCLUDING_SLUGS = 'excluding_slugs';
    public const PUBLISHED_AT_GT = 'published_at_gt';
    public const PUBLISHED_AT_LT = 'published_at_lt';
    public const FIRST_PUBLISHED_AT_GT = 'first_published_at_gt';
    public const FIRST_PUBLISHED_AT_LT = 'first_published_at_lt';
    public const UPDATED_AT_GT = 'updated_at_gt';
    public const UPDATED_AT_LT = 'updated_at_lt';
    public const IN_WORKFLOW_STAGES = 'in_workflow_stages';
    public const CONTENT_TYPE = 'content_type';
    public const LEVEL = 'level';
    public const RESOLVE_RELATIONS = 'resolve_relations';
    public const EXCLUDING_IDS = 'excluding_ids';
    public const BY_UUIDS = 'by_uuids';
    public const BY_UUIDS_ORDERED = 'by_uuids_ordered';
    public const WITH_TAG = 'with_tag';
    public const IS_STARTPAGE = 'is_startpage';
    public const RESOLVE_LINKS = 'resolve_links';
    public const RESOLVE_LINKS_LEVEL = 'resolve_links_level';
    public const FROM_RELEASE = 'from_release';
    public const FALLBACK_LANGUAGE = 'fallback_lang';
    public const LANGUAGE = 'language';
    public const FILTER_QUERY = 'filter_query';
    public const EXCLUDING_FIELDS = 'excluding_fields';
    public const RESOLVE_ASSETS = 'resolve_assets';
    public const RESOLVE_LEVEL = 'resolve_level';

    // Storyblok API Client Params
    public const VERSION = 'version';

    // Default Values
    public const DEFAULT_LANGUAGE = 'default';

    /**
     * Get the language for content retrieval.
     *
     * @return string|null
     */
    public function getLanguage(): ?string;

    /**
     * Set the language for content retrieval.
     *
     * @param string $language
     *
     * @return \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface
     */
    public function setLanguage(
        string $language
    ): \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;

    /**
     * Get the collection of fields to exclude from the response.
     *
     * @return \Storyblok\Api\Domain\Value\Field\FieldCollection
     */
    public function getExcludeFields(): \Storyblok\Api\Domain\Value\Field\FieldCollection;

    /**
     * Set the collection of fields to exclude from the response.
     *
     * @param array|null $excludeFields
     *
     * @return \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface
     */
    public function setExcludeFields(
        ?array $excludeFields
    ): \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;

    /**
     * Get the collection of relations to resolve.
     *
     * @return \Storyblok\Api\Domain\Value\Resolver\RelationCollection
     */
    public function getResolvedRelations(): \Storyblok\Api\Domain\Value\Resolver\RelationCollection;

    /**
     * Set the collection of relations to resolve.
     *
     * @param array $relations
     *
     * @return \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface
     */
    public function setResolvedRelations(
        array $relations
    ): \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;

    /**
     * Get the version of the content to retrieve.
     *
     * @return \Storyblok\Api\Domain\Value\Dto\Version|null
     */
    public function getVersion(): ?\Storyblok\Api\Domain\Value\Dto\Version;

    /**
     * Set the version of the content to retrieve.
     *
     * @param \Storyblok\Api\Domain\Value\Dto\Version $version
     *
     * @return \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface
     */
    public function setVersion(
        \Storyblok\Api\Domain\Value\Dto\Version $version
    ): \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;

    /**
     * Get the type of links to resolve.
     *
     * @return \Storyblok\Api\Domain\Value\Resolver\LinkType|null
     */
    public function getResolvedLinksType(): ?\Storyblok\Api\Domain\Value\Resolver\LinkType;


    /**
     * Set the type of links to resolve.
     *
     * @param \Storyblok\Api\Domain\Value\Resolver\LinkType|null $type
     *
     * @return \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface
     */
    public function setResolvedLinksType(
        \Storyblok\Api\Domain\Value\Resolver\LinkType $type
    ): \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;

    /**
     * Get the level of link resolution.
     *
     * @return \Storyblok\Api\Domain\Value\Resolver\LinkLevel
     */
    public function getResolvedLinksLevel(): \Storyblok\Api\Domain\Value\Resolver\LinkLevel;

    /**
     * Set the level of link resolution.
     *
     * @param \Storyblok\Api\Domain\Value\Resolver\LinkLevel $level
     *
     * @return \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface
     */
    public function setResolvedLinksLevel(
        \Storyblok\Api\Domain\Value\Resolver\LinkLevel $level
    ): \WindAndKite\Storyblok\Api\StoriesSearchCriteriaInterface;
}
