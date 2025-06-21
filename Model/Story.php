<?php

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\DataObject;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Service\StoryRenderer;

class Story extends DataObject implements StoryInterface
{
    /**
     * @param BlockFactory $blockFactory
     * @param StoryFactory $storyFactory
     * @param StoryRenderer $storyRenderer
     * @param array $data
     */
    public function __construct(
        protected BlockFactory $blockFactory,
        protected StoryFactory $storyFactory,
        private StoryRenderer $storyRenderer,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?int
    {
        return $this->getData(self::KEY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getUuid(): ?string
    {
        return $this->getData(self::KEY_UUID);
    }

    /**
     * @inheritDoc
     */
    public function getParentId(): ?int
    {
        return $this->getData(self::KEY_PARENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function getGroupId(): ?string
    {
        return $this->getData(self::KEY_GROUP_ID);
    }

    /**
     * @inheritDoc
     */
    public function getReleaseId(): ?string
    {
        return $this->getData(self::KEY_RELEASE_ID);
    }

    /**
     * @inheritDoc
     */
    public function getContent(): Block
    {
        $contentBlock = $this->blockFactory->create();
        $contentBlock->setData($this->getData(self::KEY_CONTENT));

        return $contentBlock;
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::KEY_NAME);
    }

    /**
     * @inheritDoc
     */
    public function getSlug(): ?string
    {
        return $this->getData(self::KEY_SLUG);
    }

    /**
     * @inheritDoc
     */
    public function getFullSlug(): ?string
    {
        return $this->getData(self::KEY_FULL_SLUG);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultFullSlug(): ?string
    {
        return $this->getData(self::KEY_DEFAULT_FULL_SLUG);
    }

    /**
     * @inheritDoc
     */
    public function getPath(): ?string
    {
        return $this->getData(self::KEY_PATH);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::KEY_CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getPublishedAt(): ?string
    {
        return $this->getData(self::KEY_PUBLISHED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getFirstPublishedAt(): ?string
    {
        return $this->getData(self::KEY_FIRST_PUBLISHED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::KEY_UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getSortByDate(): ?string
    {
        return $this->getData(self::KEY_SORT_BY_DATE);
    }

    /**
     * @inheritDoc
     */
    public function getPosition(): ?int
    {
        return $this->getData(self::KEY_POSITION);
    }

    /**
     * @inheritDoc
     */
    public function getIsStartpage(): ?bool
    {
        return $this->getData(self::KEY_IS_STARTPAGE);
    }

    /**
     * @inheritDoc
     */
    public function getLang(): ?string
    {
        return $this->getData(self::KEY_LANG);
    }

    /**
     * @inheritDoc
     */
    public function getAlternatives(): ?array
    {
        return $this->getData(self::KEY_ALTERNATIVES);
    }

    /**
     * @inheritDoc
     */
    public function getTranslatedSlugs(): ?array
    {
        return $this->getData(self::KEY_TRANSLATED_SLUGS);
    }

    /**
     * @inheritDoc
     */
    public function getMetaData(): ?array
    {
        return $this->getData(self::KEY_META_DATA);
    }

    /**
     * @inheritDoc
     */
    public function getTags(): ?array
    {
        return $this->getData(self::KEY_TAGS);
    }

    /**
     * @inheritDoc
     */
    public function getCacheVersion(): ?string
    {
        return $this->getData(self::KEY_CACHE_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function getRelatedStories(): ?array
    {
        if (!$this->hasData(self::KEY_RELATED_STORIES)) {
            $stories = [];

            foreach ($this->getData(self::KEY_RELS) ?? [] as $relation) {
                $story = $this->storyFactory->create();
                $story->setData($relation);
                $stories[] = $story;
            }

            $this->setData(self::KEY_RELATED_STORIES, $stories);
        }

        return $this->getData(self::KEY_RELATED_STORIES);
    }

    /**
     * @inheritDoc
     */
    public function toHtml(): string
    {
        return $this->storyRenderer->renderStory($this);
    }

    public function getMetaTags(
        ?string $key = null,
    ): ? string {
        $seoPluginData = $this->getContent()?->getMetatags();

        if ($key) {
            return $seoPluginData[$key] ?? null;
        }

        return $seoPluginData;
    }

    public function getMetaTitle(): ?string
    {
        return $this->getMetaTags('title') ?? $this->getData('meta_title') ?? $this->getName();
    }

    public function getMetaDescription(): ?string
    {
        return $this->getMetaTags('description') ?? $this->getData('meta_description');
    }
}
