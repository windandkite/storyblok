<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\Api\SortOrder;

class DirectionColumn extends Select
{
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function setInputId($value)
    {
        return $this->setId($value);
    }

    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->addOption(strtolower(SortOrder::SORT_ASC), __('ASC'));
            $this->addOption(strtolower(SortOrder::SORT_DESC), __('DESC'));
        }

        return parent::_toHtml();
    }
}
