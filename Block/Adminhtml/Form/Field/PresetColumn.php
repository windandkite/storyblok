<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use WindAndKite\Storyblok\Config\OptionSource\DynamicSortFields;

class PresetColumn extends Select
{
    /**
     * @param Context $context
     * @param DynamicSortFields $dynamicSortFields
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly DynamicSortFields $dynamicSortFields,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Set input name attribute
     *
     * @param string $value
     *
     * @return $this
     */
    public function setInputName(
        string $value,
    ): self {
        return $this->setName($value);
    }

    /**
     * Set input ID attribute
     *
     * @param string $value
     *
     * @return $this
     */
    public function setInputId(
        string $value,
    ): self {
        return $this->setId($value);
    }

    /**
     * Render HTML options dropdown
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->addOption('', __('-- Select Sort Field --'));

            foreach ($this->dynamicSortFields->toOptionArray() as $option) {
                $this->addOption($option['value'], $option['label']);
            }
        }

        return parent::_toHtml();
    }
}
