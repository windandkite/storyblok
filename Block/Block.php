<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Exception;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\SerializerInterface;
use WindAndKite\Storyblok\Api\Data\BlockInterface;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\BlockFactory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryRequestService;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;
use WindAndKite\Storyblok\ViewModel\Asset;

class Block extends AbstractStoryblok implements IdentityInterface
{
    private const KEY_CACHE_IDENTITIES = 'cache_identities';

    protected const TEMPLATE_DIR = 'block';

    public function __construct(
        private readonly BlockFactory $blockFactory,
        private readonly FieldRendererInterface $fieldRenderer,
        private readonly SerializerInterface $serializer,
        private readonly StoryblokSessionManager $storyblokSessionManager,
        StoryRepository $storyRepository,
        Asset $assetViewModel,
        Config $scopeConfig,
        Template\Context $context,
        StoryRequestService $storyRequestService,
        array $data = [],
    ) {
        parent::__construct(
            $storyRepository,
            $assetViewModel,
            $scopeConfig,
            $storyRequestService,
            $context,
            $data
        );
    }

    public function getData(
        $key = '', $index = null
    ) {
        if ($key === 'block') {
            return parent::getData($key, $index);
        }

        return parent::getData($key, $index) ?? $this->getBlock()->getData($key, $index);
    }

    public function getBlock(): StoryblokBlock
    {
        return $this->getData('block') ?? $this->blockFactory->create();
    }

    public function getBlokEditableAttributes(): string
    {
        if (!$this->scopeConfig->isDevModeEnabled() && !$this->storyblokSessionManager->isValidEditorSession() && !$this->getStory()?->getForceBridge()) {
            return '';
        }

        $rawEditableComment = $this->getData('_editable');

        if (
            !is_string($rawEditableComment)
            || !str_contains($rawEditableComment, '<!--#storyblok#')
            || !preg_match('/\{[^}]*\}/', $rawEditableComment, $matches)
            || !isset($matches[0]) || empty($matches[0])
        ) {
            return '';
        }

        $dataBlokC = $matches[0];

        try {
            $editableData = $this->serializer->unserialize($dataBlokC);
        } catch (Exception $e) {
            return '';
        }

        if (!isset($editableData['uid'], $editableData['id'], $editableData['name'])) {
            return '';
        }

        $dataBlokUid = $this->getStory()->getId() . '-' . $editableData['uid'];

        return sprintf("data-blok-c='%s' data-blok-uid='%s'", $dataBlokC, $dataBlokUid);
    }

    public function renderField(string $fieldName): ?string
    {
        $story = $this->getStory();

        if (!$story) {
            return null;
        }

        return $this->fieldRenderer->renderField(
            $this->getBlock()->getData($fieldName),
            $story
        );
    }

    /**
     * Render a child blok (or a list of bloks, or rich text) with this block's story, e.g. to wrap each
     * item of a blocks field in your own markup:
     *
     *     <?php foreach ((array)$block->getData('links') as $link): ?>
     *         <li><?= $block->renderBlok($link) ?></li>
     *     <?php endforeach ?>
     *
     * @param array $blok
     * @param StoryInterface|null $story Defaults to this block's story (pass another, e.g. for referenced content).
     *
     * @return string
     */
    public function renderBlok(array $blok, ?StoryInterface $story = null): string
    {
        return $this->fieldRenderer->renderField($blok, $story ?? $this->getStory());
    }

    /**
     * Create (and hydrate) the block for a child blok without rendering it, to read data a hydrator
     * added before calling toHtml().
     *
     * @param array $blok
     * @param StoryInterface|null $story Defaults to this block's story.
     *
     * @return Block
     */
    public function createBlokInstance(array $blok, ?StoryInterface $story = null): Block
    {
        return $this->fieldRenderer->createBlockInstance($blok, $story ?? $this->getStory());
    }

    public function renderRichTextField(
        string $fieldName,
    ): string {
        return $this->fieldRenderer->renderRichTextField($this->getBlock()->getData($fieldName));
    }

    /**
     * Magic method for accessing and rendering block data and assets.
     *
     * @param string $method
     * @param array $args
     *
     * @return mixed|null
     * @throws NoSuchEntityException|LocalizedException
     */
    public function __call($method, $args)

    {
        if (str_starts_with($method, 'get') && str_ends_with($method, 'Html')) {
            $fieldName = $this->_underscore(substr($method, 0, -4));

            if (!in_array($fieldName, BlockInterface::UNRENDERABLE_FIELDS) && $this->getBlock()->hasData($fieldName)) {
                return $this->renderField($fieldName);
            }

            return $this->getData($fieldName);
        }

        return parent::__call($method, $args);
    }

    public function getComponent(): ?string
    {
        return $this->getBlock()->getComponent() ?? $this->getData('component');
    }

    /**
     * Add cache tags for entities this blok renders (products, categories, referenced stories...),
     * typically from a hydrator. They are collected into the page's X-Magento-Tags so the full page
     * cache is purged when those entities change.
     *
     * @param string[] $identities
     *
     * @return $this
     */
    public function addIdentities(array $identities): static
    {
        return $this->setData(
            self::KEY_CACHE_IDENTITIES,
            array_values(array_unique([...$this->getIdentities(), ...$identities]))
        );
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return parent::getData(self::KEY_CACHE_IDENTITIES) ?? [];
    }

    protected function _toHtml(): string
    {
        $html = parent::_toHtml();
        $editableAttributes = $this->getBlokEditableAttributes();

        if (empty($editableAttributes)) {
            return $html;
        }

        return $this->addAttributesToFirstTag($html, $editableAttributes);
    }

    /**
     * Append attributes to the first element tag in the HTML (skipping script/style/meta and document tags).
     *
     * Attribute values are matched as quoted strings, so a ">" inside one (e.g. a utility-class variant like
     * class="[&>*]:mt-4") doesn't end the tag early.
     *
     * @param string $html
     * @param string $attributes
     *
     * @return string
     */
    private function addAttributesToFirstTag(
        string $html,
        string $attributes,
    ): string {
        $excludedTags = ['script', 'style', 'link', 'meta', '!doctype', 'html', 'head', 'body'];
        $pattern = '/<(?!(?:' . implode('|', $excludedTags) . ')[\s>\/])([a-zA-Z0-9]+)((?:"[^"]*"|\'[^\']*\'|[^\'">])*?)(\s*\/?)>/is';

        $modifiedHtml = preg_replace_callback(
            $pattern,
            static fn (array $matches) => '<' . $matches[1] . $matches[2] . ' ' . $attributes . $matches[3] . '>',
            $html,
            1
        );

        return $modifiedHtml ?? $html;
    }
}
