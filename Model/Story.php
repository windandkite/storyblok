<?php

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\DataObject;
use WindAndKite\Storyblok\Api\Data\StoryInterface;

class Story extends DataObject implements StoryInterface
{
    /**
     * @param BlockFactory $blockFactory
     * @param array $data
     */
    public function __construct(
        protected readonly BlockFactory $blockFactory,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    /**
     * Get ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->getData(self::KEY_ID);
    }

    /**
     * Get UUID.
     *
     * @return string|null
     */
    public function getUuid(): ?string
    {
        return $this->getData(self::KEY_UUID);
    }

    /**
     * Get Parent ID.
     *
     * @return int|null
     */
    public function getParentId(): ?int
    {
        return $this->getData(self::KEY_PARENT_ID);
    }

    /**
     * Get Group ID.
     *
     * @return string|null
     */
    public function getGroupId(): ?string
    {
        return $this->getData(self::KEY_GROUP_ID);
    }

    /**
     * Get Release ID.
     *
     * @return string|null
     */
    public function getReleaseId(): ?string
    {
        return $this->getData(self::KEY_RELEASE_ID);
    }

    /**
     * Get content.
     *
     * @return Block
     */
    public function getContent(): Block
    {
        $contentBlock = $this->blockFactory->create();
        $contentBlock->setData($this->getData(self::KEY_CONTENT));

        return $contentBlock;
    }

    /**
     * Get name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getData(self::KEY_NAME);
    }

    /**
     * Get slug.
     *
     * @return string|null
     */
    public function getSlug(): ?string
    {
        return $this->getData(self::KEY_SLUG);
    }

    /**
     * Get full slug.
     *
     * @return string|null
     */
    public function getFullSlug(): ?string
    {
        return $this->getData(self::KEY_FULL_SLUG);
    }

    /**
     * Get default full slug.
     *
     * @return string|null
     */
    public function getDefaultFullSlug(): ?string
    {
        return $this->getData(self::KEY_DEFAULT_FULL_SLUG);
    }

    /**
     * Get path.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->getData(self::KEY_PATH);
    }

    /**
     * Get created at.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::KEY_CREATED_AT);
    }

    /**
     * Get published at.
     *
     * @return string|null
     */
    public function getPublishedAt(): ?string
    {
        return $this->getData(self::KEY_PUBLISHED_AT);
    }

    /**
     * Get first published at.
     *
     * @return string|null
     */
    public function getFirstPublishedAt(): ?string
    {
        return $this->getData(self::KEY_FIRST_PUBLISHED_AT);
    }

    /**
     * Get updated at.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::KEY_UPDATED_AT);
    }

    /**
     * Get sort by date.
     *
     * @return string|null
     */
    public function getSortByDate(): ?string
    {
        return $this->getData(self::KEY_SORT_BY_DATE);
    }

    /**
     * Get position.
     *
     * @return int|null
     */
    public function getPosition(): ?int
    {
        return $this->getData(self::KEY_POSITION);
    }

    /**
     * Get is startpage.
     *
     * @return bool|null
     */
    public function getIsStartpage(): ?bool
    {
        return $this->getData(self::KEY_IS_STARTPAGE);
    }

    /**
     * Get language.
     *
     * @return string|null
     */
    public function getLang(): ?string
    {
        return $this->getData(self::KEY_LANG);
    }

    /**
     * Get alternatives.
     *
     * @return array|null
     */
    public function getAlternatives(): ?array
    {
        return $this->getData(self::KEY_ALTERNATIVES);
    }

    /**
     * Get translated slugs.
     *
     * @return array|null
     */
    public function getTranslatedSlugs(): ?array
    {
        return $this->getData(self::KEY_TRANSLATED_SLUGS);
    }

    /**
     * Get meta data.
     *
     * @return array|null
     */
    public function getMetaData(): ?array
    {
        return $this->getData(self::KEY_META_DATA);
    }

    /**
     * Get tags.
     *
     * @return array|null
     */
    public function getTags(): ?array
    {
        return $this->getData(self::KEY_TAGS);
    }
}
