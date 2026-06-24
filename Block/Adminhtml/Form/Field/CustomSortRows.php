<?php
declare(strict_types=1);

namespace WindAndKite\Storyblok\Block\Adminhtml\Form\Field;

use Exception;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use WindAndKite\Storyblok\Config\OptionSource\DefaultSort;

class CustomSortRows extends AbstractFieldArray
{
    /**
     * @var PresetColumn|null
     */
    private ?PresetColumn $presetRenderer = null;

    /**
     * @var DirectionColumn|null
     */
    private ?DirectionColumn $directionRenderer = null;

    /**
     * Prepare grid columns matching Adobe pattern
     */
    protected function _prepareToRender()
    {
        $this->addColumn('preset_field', [
            'label' => __('Field Preset'),
            'class' => 'required-entry sort-preset-select',
            'renderer' => $this->getPresetRenderer()
        ]);
        $this->addColumn('field_name', [
            'label' => __('Field Code'),
            'class' => 'required-entry admin__control-text sort-field-input'
        ]);
        $this->addColumn('direction', [
            'label' => __('Direction'),
            'class' => 'required-entry sort-direction-select',
            'renderer' => $this->getDirectionRenderer()
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Sort Rule');
    }

    /**
     * Map loaded database config state to set rows to "selected" state
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $preset = $row->getPresetField();
        if ($preset !== null) {
            $options['option_' . $this->getPresetRenderer()->calcOptionHash($preset)] = 'selected="selected"';
        }

        $direction = $row->getDirection();
        if ($direction !== null) {
            $options['option_' . $this->getDirectionRenderer()->calcOptionHash($direction)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Get lazy loaded block rendering layout instance for Field Presets
     */
    private function getPresetRenderer(): PresetColumn
    {
        if (!$this->presetRenderer) {
            $this->presetRenderer = $this->getLayout()->createBlock(
                PresetColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->presetRenderer;
    }

    /**
     * Get lazy loaded block rendering layout instance for Sort Direction
     */
    private function getDirectionRenderer(): DirectionColumn
    {
        if (!$this->directionRenderer) {
            $this->directionRenderer = $this->getLayout()->createBlock(
                DirectionColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->directionRenderer;
    }

    /**
     * Append the customized native JS interaction overlay
     *
     * @return string
     * @throws Exception
     */
    protected function _toHtml(): string
    {
        $html = parent::_toHtml();

        $customId = DefaultSort::FIELD_CUSTOM;
        $positionId = DefaultSort::FIELD_POSITION;
        $ascVal = strtolower(SortOrder::SORT_ASC);

        $js = <<<JS
        <script>
        (function () {
            'use strict';

            /**
             * Handles self-contained lifecycle and logic for an individual table row
             */
            class SortRowController {
                constructor(rowElement) {
                    this.row = rowElement;
                    this.rowId = rowElement.id;
                    
                    if (!this.rowId) return;

                    this.presetSel = document.getElementById(this.rowId + '_preset_field');
                    this.fieldInput = document.getElementById(this.rowId + '_field_name');
                    this.dirSel = document.getElementById(this.rowId + '_direction');

                    if (!this.presetSel || !this.fieldInput || !this.dirSel) return;

                    this.init();
                }

                init() {
                    this.presetSel.addEventListener('change', () => this.applyLogic());
                    this.applyLogic();
                }

                applyLogic() {
                    if (this.presetSel.value !== '{$customId}') {
                        this.fieldInput.value = this.presetSel.value;
                        this.fieldInput.readOnly = true;
                    } else {
                        this.fieldInput.readOnly = false;
                    }

                    if (this.presetSel.value === '{$positionId}') {
                        this.dirSel.value = '{$ascVal}';
                        this.dirSel.disabled = true;
                    } else {
                        this.dirSel.disabled = false;
                    }
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const container = document.getElementById('storyblok_story_lists_default_sort_custom');
                if (!container) return;

                const tbody = container.querySelector('tbody');
                if (!tbody) return;

                const registerRow = (row) => {
                    requestAnimationFrame(() => {
                        new SortRowController(row);
                    });
                };

                tbody.querySelectorAll('tr').forEach(registerRow);

                const observer = new MutationObserver((mutations) => {
                    for (const mutation of mutations) {
                        for (const node of mutation.addedNodes) {
                            if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'TR') {
                                registerRow(node);
                            }
                        }
                    }
                });

                observer.observe(tbody, { childList: true });

                const form = container.closest('form');
                if (form) {
                    form.addEventListener('submit', () => {
                        container.querySelectorAll('select[disabled]').forEach((select) => {
                            select.disabled = false;
                        });
                    });
                }
            });
        })();
        </script>
JS;
        return $html . $js;
    }
}
