<?php

namespace WindAndKite\Storyblok\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class WebhookSecret extends Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setDisabled('disabled');
        $html = $element->getElementHtml();
        $html .= $this->getGenerateButtonHtml();
        return $html;
    }

    /**
     * Generate button HTML
     *
     * @return string
     */
    protected function getGenerateButtonHtml()
    {
        $button = $this->getLayout()
            ->createBlock(Button::class)->setData(
                [
                    'id' => 'generate_webhook_secret',
                    'label' => __('Generate New Secret'),
                    'onclick' => 'javascript:generateWebhookSecret(); return false;',
                ]
            );

        return $button->toHtml() . $this->getGenerateSecretJs();
    }

    /**
     * Generate secret js
     *
     * @return string
     */
    protected function getGenerateSecretJs()
    {
        $generateUrl = $this->getUrl('storyblok/webhook/regenerateSecret');
        return <<<JS
        <script>
        require(['jquery', 'Magento_Ui/js/modal/alert'], function ($, alert) {
            window.generateWebhookSecret = function () {
                $.ajax({
                    url: '{$generateUrl}',
                    type: 'POST',
                    dataType: 'json',
                    data: {form_key: window.FORM_KEY},
                    showLoader: true,
                    success: function (response) {
                        if (response.success) {
                            alert({
                                title: 'Success',
                                content: 'New webhook secret generated and saved.',
                                actions: {
                                    always: function(){}
                                }
                            });
                            // Update the secret field
                            $('input#storyblok_general_webhook_secret').val(response.secret);
                        } else {
                            alert({
                                title: 'Error',
                                content: response.message || 'Failed to generate new secret.',
                                actions: {
                                    always: function(){}
                                }
                            });
                        }
                    },
                    error: function () {
                        alert({
                            title: 'Error',
                            content: 'Failed to generate new secret.',
                            actions: {
                                always: function(){}
                            }
                        });
                    }
                });
            }
        });
        </script>
        JS;
    }
}
