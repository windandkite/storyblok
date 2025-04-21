<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use WindAndKite\Storyblok\Api\Data\BlockInterface;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\BlockFactory;

class Block extends Template
{
    protected $_template = 'WindAndKite_Storyblok::block/fallback.phtml';

    public function __construct(
        Template\Context $context,
        private readonly BlockFactory $blockFactory,
        private readonly FieldRendererInterface $fieldRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
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

    public function getBlockTemplate(
        $useFallback = true
    ): string {
        $block = $this->getBlock();

        if ($componentName = $block->getComponent()) {
            $templateName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $componentName));
            $templateName = str_replace('-', '_', $templateName);

            return 'WindAndKite_Storyblok::block/' . $templateName . '.phtml';
        }

        return $this->_template;
    }

    public function getTemplateFile($template = null)
    {
        $template = $template ?? $this->getBlockTemplate();
        $templateFile = parent::getTemplateFile($template);

        if (!$templateFile) {
            $templateFile = parent::getTemplateFile($this->_template);
        }

        return $templateFile;
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

            throw new NoSuchEntityException(
                __('Field "%1" does not exist on component "%2".', $fieldName, $this->getBlock()->getComponent())
            );
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
                throw new \Magento\Framework\Exception\InvalidArgumentException(
                    __('Field "%1" is not an asset component.', $fieldName)
                );
            }

            throw new NoSuchEntityException(
                __('Field "%1" does not exist on component "%2".', $fieldName, $this->getBlock()->getComponent())
            );
        }

        return parent::__call($method, $args);
    }

}
