<?php

namespace WindAndKite\Storyblok\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;
use WindAndKite\Storyblok\Controller\Webhook\Index as Webhook;

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
        $html .= '<p class="note"><span>'
            . $this->escapeHtml(__('Webhook URL for Storyblok (Settings > Webhooks). Plans that can sign webhooks can also set this secret there.'))
            . '</span><br><code id="storyblok_webhook_url" style="word-break:break-all">'
            . $this->escapeHtml($element->getValue() ? $this->getWebhookUrl((string)$element->getValue()) : (string)__('Generate a secret first.'))
            . '</code></p>';

        return $html;
    }

    /**
     * The frontend webhook endpoint with the secret as its key, for Storyblok plans that can't sign webhooks.
     *
     * @param string $secret
     *
     * @return string
     */
    private function getWebhookUrl(string $secret): string
    {
        $baseUrl = $this->_storeManager->getDefaultStoreView()?->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) ?? '';

        return $baseUrl . 'storyblok/webhook?' . http_build_query([Webhook::KEY_PARAM => $secret]);
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
        $webhookUrl = $this->escapeJs($this->getWebhookUrl(''));
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
                            $('#storyblok_webhook_url').text('{$webhookUrl}' + encodeURIComponent(response.secret));
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
