<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\ViewModel;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use WindAndKite\Storyblok\Scope\Config;

/**
 * Resolves Storyblok "multilink" field values into storefront URLs and anchor attributes.
 */
class Link implements ArgumentInterface
{
    public const TYPE_URL = 'url';
    public const TYPE_STORY = 'story';
    public const TYPE_EMAIL = 'email';
    public const TYPE_ASSET = 'asset';

    /**
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param Config $config
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        private readonly Config $config,
    ) {}

    /**
     * Resolve a multilink field to a URL. Returns an empty string when no link is set.
     *
     * @param array|null $link
     *
     * @return string
     */
    public function getUrl(?array $link): string
    {
        if (!$link) {
            return '';
        }

        $url = match ($link['linktype'] ?? self::TYPE_URL) {
            self::TYPE_STORY => $this->getStoryUrl($link),
            self::TYPE_EMAIL => $this->getEmailUrl($link),
            default => trim((string)($link['url'] ?? $link['cached_url'] ?? '')),
        };

        if ($url === '' || !$this->isSafeScheme($url)) {
            return '';
        }

        if (!empty($link['anchor'])) {
            $url .= '#' . ltrim((string)$link['anchor'], '#');
        }

        return $url;
    }

    /**
     * @param array|null $link
     *
     * @return string|null
     */
    public function getTarget(?array $link): ?string
    {
        $target = $link['target'] ?? null;

        return $target === '_blank' ? '_blank' : null;
    }

    /**
     * @param array|null $link
     *
     * @return string|null
     */
    public function getRel(?array $link): ?string
    {
        return $this->getTarget($link) === '_blank' ? 'noopener noreferrer' : null;
    }

    /**
     * Whether the resolved URL points away from the current storefront.
     *
     * @param array|null $link
     *
     * @return bool
     */
    public function isExternal(?array $link): bool
    {
        $host = parse_url($this->getUrl($link), PHP_URL_HOST);

        return $host && $host !== parse_url($this->urlBuilder->getBaseUrl(), PHP_URL_HOST);
    }

    /**
     * Escaped href/target/rel attribute string, ready to print inside an <a> tag.
     *
     * @param array|null $link
     *
     * @return string
     */
    public function getAttributes(?array $link): string
    {
        $attributes = ['href' => $this->getUrl($link), 'target' => $this->getTarget($link), 'rel' => $this->getRel($link)];
        $html = [];

        foreach (array_filter($attributes) as $name => $value) {
            $html[] = $name . '="' . ($name === 'href' ? $this->escaper->escapeUrl($value) : $this->escaper->escapeHtmlAttr($value)) . '"';
        }

        return implode(' ', $html);
    }

    /**
     * @param array $link
     *
     * @return string
     */
    private function getStoryUrl(array $link): string
    {
        $slug = $link['story']['full_slug'] ?? $link['cached_url'] ?? '';
        $slug = trim((string)$slug, '/');

        if ($slug === '') {
            return '';
        }

        // The story served as the storefront homepage lives at "/", not at its slug.
        $homeSlug = trim((string)$this->config->getHomeSlug(), '/');

        if ($homeSlug !== '' && $slug === $homeSlug && $this->config->isHomeEnabled()) {
            return $this->urlBuilder->getBaseUrl();
        }

        return $this->urlBuilder->getDirectUrl($slug);
    }

    /**
     * @param array $link
     *
     * @return string
     */
    private function getEmailUrl(array $link): string
    {
        $email = trim((string)($link['email'] ?? $link['url'] ?? ''));

        if ($email === '') {
            return '';
        }

        return str_starts_with($email, 'mailto:') ? $email : 'mailto:' . $email;
    }

    /**
     * Reject javascript:, data: and other dangerous schemes entered by editors.
     *
     * @param string $url
     *
     * @return bool
     */
    private function isSafeScheme(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return !$scheme || in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true);
    }
}
