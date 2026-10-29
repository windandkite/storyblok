<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Parses YouTube / Vimeo URLs into privacy-friendly embed URLs.
 */
class Video implements ArgumentInterface
{
    public const TYPE_NATIVE = 'native';
    public const TYPE_YOUTUBE = 'youtube';
    public const TYPE_VIMEO = 'vimeo';

    private const YOUTUBE_PATTERN = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([\w-]{11})~i';
    private const VIMEO_PATTERN = '~vimeo\.com/(?:.*/)?(?:video/)?(\d+)(?:/([\da-f]+))?~i';

    /**
     * @param string|null $url
     *
     * @return string|null
     */
    public function getYoutubeId(?string $url): ?string
    {
        return $url && preg_match(self::YOUTUBE_PATTERN, $url, $matches) ? $matches[1] : null;
    }

    /**
     * @param string|null $url
     *
     * @return array{id: string, hash: string|null}|null
     */
    public function getVimeoId(?string $url): ?array
    {
        if (!$url || !preg_match(self::VIMEO_PATTERN, $url, $matches)) {
            return null;
        }

        return ['id' => $matches[1], 'hash' => $matches[2] ?? null];
    }

    /**
     * Embed URL for the iframe. The iframe is only injected after user intent (or autoplay),
     * so it always requests autoplay; the JS API flags allow other players to pause it.
     *
     * @param string $type
     * @param string|null $url
     * @param bool $muted
     * @param bool $controls
     *
     * @return string|null
     */
    public function getEmbedUrl(
        string $type,
        ?string $url,
        bool $muted = false,
        bool $controls = true,
    ): ?string {
        if ($type === self::TYPE_YOUTUBE && $id = $this->getYoutubeId($url)) {
            return 'https://www.youtube-nocookie.com/embed/' . $id . '?' . http_build_query([
                'autoplay' => 1,
                'enablejsapi' => 1,
                'playsinline' => 1,
                'rel' => 0,
                'mute' => (int)$muted,
                'controls' => (int)$controls,
            ]);
        }

        if ($type === self::TYPE_VIMEO && $vimeo = $this->getVimeoId($url)) {
            return 'https://player.vimeo.com/video/' . $vimeo['id'] . '?' . http_build_query(array_filter([
                'h' => $vimeo['hash'],
                'autoplay' => 1,
                'dnt' => 1,
                'playsinline' => 1,
                'muted' => (int)$muted,
                'controls' => (int)$controls,
            ], static fn ($value) => $value !== null));
        }

        return null;
    }

    /**
     * Fallback poster when no poster image was uploaded. Vimeo has no keyless thumbnail URL.
     *
     * @param string $type
     * @param string|null $url
     *
     * @return string|null
     */
    public function getFallbackPosterUrl(string $type, ?string $url): ?string
    {
        $id = $type === self::TYPE_YOUTUBE ? $this->getYoutubeId($url) : null;

        return $id ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' : null;
    }
}
