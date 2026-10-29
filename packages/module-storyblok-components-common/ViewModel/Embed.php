<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\ViewModel;

use DOMDocument;
use DOMElement;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Reduces editor-supplied embed code to a single, attribute-whitelisted https iframe.
 * Scripts, event handlers and every other element are dropped.
 */
class Embed implements ArgumentInterface
{
    /**
     * @param string|null $embedCode Iframe HTML or a bare https URL.
     *
     * @return array{src: string, title: string, allow: string, allowfullscreen: bool}|null
     */
    public function getIframe(?string $embedCode): ?array
    {
        $embedCode = trim((string)$embedCode);

        if ($embedCode === '') {
            return null;
        }

        if (!str_contains($embedCode, '<')) {
            return $this->isHttpsUrl($embedCode)
                ? ['src' => $embedCode, 'title' => '', 'allow' => '', 'allowfullscreen' => true]
                : null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>' . $embedCode, LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $iframe = $document->getElementsByTagName('iframe')->item(0);

        if (!$iframe instanceof DOMElement) {
            return null;
        }

        $src = trim($iframe->getAttribute('src'));

        if (!$this->isHttpsUrl($src)) {
            return null;
        }

        return [
            'src' => $src,
            'title' => $iframe->getAttribute('title'),
            'allow' => $iframe->getAttribute('allow'),
            'allowfullscreen' => $iframe->hasAttribute('allowfullscreen'),
        ];
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    private function isHttpsUrl(string $url): bool
    {
        return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https'
            && (bool)parse_url($url, PHP_URL_HOST);
    }
}
