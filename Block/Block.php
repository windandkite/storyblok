<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use WindAndKite\Storyblok\Api\Data\BlockInterface;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Controller\Router;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\BlockFactory;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
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
        array $data = []
    ) {
        parent::__construct($storyRepository, $assetViewModel, $scopeConfig, $context, $data);
    }

    public function getData($key = '', $index = null) {
        if ($key === 'block') {
            return parent::getData($key, $index);
        }

        return parent::getData($key, $index) ?? $this->getBlock()->getData($key, $index);
    }

    public function getBlock(): StoryblokBlock
    {
        return $this->getData('block') ?? $this->blockFactory->create();
    }

    public function renderField(string $fieldName): string
    {
        return $this->fieldRenderer->renderField($this->getBlock()->getData($fieldName));
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
     * @return mixed|null
     * @throws NoSuchEntityException
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

        if (str_starts_with($method, 'get') && str_ends_with($method, 'Asset')) {
            $fieldName = $this->_underscore(substr($method, 0, -4));

            if (
                !in_array($fieldName, BlockInterface::UNRENDERABLE_FIELDS)
                && $this->getBlock()->hasData($fieldName)
                && ($this->getBlock()->getData($fieldName)['fieldtype'] ?? null) === 'asset'
            ) {
                $asset = $this->assetFactory->create();
                $asset->setData($this->getBlock()->getData($fieldName));

                return $asset;
            }

            if (
                !in_array($fieldName, BlockInterface::UNRENDERABLE_FIELDS)
                && $this->getBlock()->hasData($fieldName)
                && ($this->getBlock()->getData($fieldName)['fieldtype'] ?? null) !== 'asset'
            ) {
                throw new InvalidArgumentException(
                    __('Field "%1" is not an asset component.', $fieldName)
                );
            }

            throw new NoSuchEntityException(
                __('Field "%1" does not exist on component "%2".', $fieldName, $this->getBlock()->getComponent())
            );
        }

        return parent::__call($method, $args);
    }

    public function getComponent(): string
    {
        return $this->getBlock()->getComponent();
    }

    protected function _toHtml(): string
    {
        $editable = $this->getData('_editable') ?? '';

        return $editable . parent::_toHtml();
    }
}
