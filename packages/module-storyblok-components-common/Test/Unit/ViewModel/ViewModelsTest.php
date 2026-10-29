<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use WindAndKite\StoryblokComponentsCommon\ViewModel\Embed;
use WindAndKite\StoryblokComponentsCommon\ViewModel\Table;
use WindAndKite\StoryblokComponentsCommon\ViewModel\Video;

class ViewModelsTest extends TestCase
{
    public function testVideoUrlParsing(): void
    {
        $video = new Video();

        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame('dQw4w9WgXcQ', $video->getYoutubeId($url), $url);
        }

        $this->assertSame(['id' => '76979871', 'hash' => null], $video->getVimeoId('https://vimeo.com/76979871'));
        $this->assertSame(['id' => '123', 'hash' => 'abc123'], $video->getVimeoId('https://vimeo.com/123/abc123'));
        $this->assertNull($video->getYoutubeId('https://example.com'));

        $this->assertStringStartsWith(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&enablejsapi=1',
            $video->getEmbedUrl('youtube', 'https://youtu.be/dQw4w9WgXcQ')
        );
        $this->assertSame(
            'https://player.vimeo.com/video/123?h=abc123&autoplay=1&dnt=1&playsinline=1&muted=1&controls=0',
            $video->getEmbedUrl('vimeo', 'https://vimeo.com/123/abc123', true, false)
        );
        $this->assertNull($video->getEmbedUrl('youtube', 'https://vimeo.com/123'));
    }

    public function testEmbedSanitisation(): void
    {
        $embed = new Embed();
        $iframe = $embed->getIframe(
            '<script>alert(1)</script><iframe src="https://maps.example.com/x" title="Map" onload="alert(1)" allow="fullscreen" allowfullscreen></iframe>'
        );

        $this->assertSame(
            ['src' => 'https://maps.example.com/x', 'title' => 'Map', 'allow' => 'fullscreen', 'allowfullscreen' => true],
            $iframe
        );
        $this->assertNull($embed->getIframe('<iframe src="javascript:alert(1)"></iframe>'));
        $this->assertNull($embed->getIframe('<iframe src="http://insecure.example.com"></iframe>'));
        $this->assertNull($embed->getIframe('<script>alert(1)</script>'));
        $this->assertSame('https://example.com/embed', $embed->getIframe('https://example.com/embed')['src']);
    }

    public function testTableNormalisation(): void
    {
        $table = (new Table())->normalize([
            'thead' => [['value' => 'Size'], ['value' => 'Price']],
            'tbody' => [['body' => [['value' => 'S'], ['value' => '£10']]]],
        ]);

        $this->assertSame(['head' => ['Size', 'Price'], 'rows' => [['S', '£10']]], $table);
        $this->assertSame([], (new Table())->normalize(['thead' => [['value' => '']], 'tbody' => []])['head']);
    }
}
