<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\SerializerInterface;
use WindAndKite\Storyblok\Api\Data\BlockInterface;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\BlockFactory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryRequestService;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;
use WindAndKite\Storyblok\ViewModel\Asset;

class Block extends AbstractStoryblok
{
    protected const TEMPLATE_DIR = 'block';

    public function __construct(
        StoryRepository $storyRepository,
        Asset $assetViewModel,
        Config $scopeConfig,
        Template\Context $context,
        private BlockFactory $blockFactory,
        private FieldRendererInterface $fieldRenderer,
        StoryRequestService $storyRequestService,
        private SerializerInterface $serializer,
        private StoryblokSessionManager $storyblokSessionManager,
        array $data = []
    ) {
        parent::__construct(
            $storyRepository,
            $assetViewModel,
            $scopeConfig,
            $context,
            $storyRequestService,
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
        if (!$this->scopeConfig->isDevModeEnabled() && !$this->storyblokSessionManager->isValidEditorSession() && !$this->getStory()->getForceBridge()) {
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

    protected function _toHtml(): string
    {
        $html = parent::_toHtml();
        $editableAttributes = $this->getBlokEditableAttributes();

        if (!empty($editableAttributes)) {
            $excludedTags = ['script', 'style', 'link', 'meta', '!doctype', 'html', 'head', 'body'];

            $pattern = '/<(?!(?:' . implode('|', $excludedTags) . ')[\s>\/])([a-zA-Z0-9]+)([^>]*)>/is';

            $modifiedHtml = preg_replace_callback($pattern, function ($matches) use ($editableAttributes) {
                $tagName = $matches[1];
                $existingAttributes = $matches[2];

                return "<{$tagName}{$existingAttributes} {$editableAttributes}>";
            }, $html, 1);

            if ($modifiedHtml !== null && $modifiedHtml !== $html) {
                return $modifiedHtml;
            }
        }

        return $html;
    }
}
