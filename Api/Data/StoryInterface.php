<?php

namespace WindAndKite\Storyblok\Api\Data;

/**
 * Interface StoryInterface
 *
 * @api
 */
interface StoryInterface
{
    // Identifier Constants
    public const KEY_ID = 'id';
    public const KEY_UUID = 'uuid';
    public const KEY_PARENT_ID = 'parent_id';
    public const KEY_GROUP_ID = 'group_id';
    public const KEY_RELEASE_ID = 'release_id';

    // Content Constants
    public const KEY_CONTENT = 'content';
    public const KEY_NAME = 'name';
    public const KEY_SLUG = 'slug';
    public const KEY_FULL_SLUG = 'full_slug';
    public const KEY_DEFAULT_FULL_SLUG = 'default_full_slug';
    public const KEY_PATH = 'path';

    // Date and Time Constants
    public const KEY_CREATED_AT = 'created_at';
    public const KEY_PUBLISHED_AT = 'published_at';
    public const KEY_FIRST_PUBLISHED_AT = 'first_published_at';
    public const KEY_UPDATED_AT = 'updated_at';
    public const KEY_SORT_BY_DATE = 'sort_by_date';

    // Status Constants
    public const KEY_POSITION = 'position';
    public const KEY_IS_STARTPAGE = 'is_startpage';

    // Localization Constants
    public const KEY_LANG = 'lang';
    public const KEY_ALTERNATIVES = 'alternatives';
    public const KEY_TRANSLATED_SLUGS = 'translated_slugs';

    // Metadata Constants
    public const KEY_META_DATA = 'meta_data';
    public const KEY_TAGS = 'tag_list';
    public const KEY_CACHE_VERSION = 'cache_version';

    // Relations
    public const KEY_RELS = 'rels';

    /**
     * Get ID.
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Get UUID.
     *
     * @return string|null
     */
    public function getUuid(): ?string;

    /**
     * Get Parent ID.
     *
     * @return int|null
     */
    public function getParentId(): ?int;

    /**
     * Get Group ID.
     *
     * @return string|null
     */
    public function getGroupId(): ?string;

    /**
     * Get Release ID.
     *
     * @return string|null
     */
    public function getReleaseId(): ?string;

    /**
     * Get content.
     *
     * @return \WindAndKite\Storyblok\Api\Data\BlockInterface
     */
    public function getContent(): \WindAndKite\Storyblok\Api\Data\BlockInterface;

    /**
     * Get name.
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Get slug.
     *
     * @return string|null
     */
    public function getSlug(): ?string;

    /**
     * Get full slug.
     *
     * @return string|null
     */
    public function getFullSlug(): ?string;

    /**
     * Get default full slug.
     *
     * @return string|null
     */
    public function getDefaultFullSlug(): ?string;

    /**
     * Get path.
     *
     * @return string|null
     */
    public function getPath(): ?string;

    /**
     * Get created at.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get published at.
     *
     * @return string|null
     */
    public function getPublishedAt(): ?string;

    /**
     * Get first published at.
     *
     * @return string|null
     */
    public function getFirstPublishedAt(): ?string;

    /**
     * Get updated at.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Get sort by date.
     *
     * @return string|null
     */
    public function getSortByDate(): ?string;

    /**
     * Get position.
     *
     * @return int|null
     */
    public function getPosition(): ?int;

    /**
     * Get is startpage.
     *
     * @return bool|null
     */
    public function getIsStartpage(): ?bool;

    /**
     * Get language.
     *
     * @return string|null
     */
    public function getLang(): ?string;

    /**
     * Get alternatives.
     *
     * @return array|null
     */
    public function getAlternatives(): ?array;

    /**
     * Get translated slugs.
     *
     * @return array|null
     */
    public function getTranslatedSlugs(): ?array;

    /**
     * Get meta data.
     *
     * @return array|null
     */
    public function getMetaData(): ?array;

    /**
     * Get tags.
     *
     * @return array|null
     */
    public function getTags(): ?array;

    /**
     * Get Cache Version
     *
     * @return string|null
     */
    public function getCacheVersion(): ?string;

    /**
     * Get Story Relations
     *
     * @return array|null
     */
    public function getRelatedStories(): ?array;

    /**
     * Render Story Html
     *
     * @return string
     */
    public function toHtml(): string;
}
