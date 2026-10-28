<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\TipTap;

use PHPUnit\Framework\TestCase;
use Storyblok\Tiptap\Extension\Storyblok;
use Tiptap\Editor;
use WindAndKite\Storyblok\TipTap\Image;
use WindAndKite\Storyblok\ViewModel\Asset;

class ImageTest extends TestCase
{
    private const SRC = 'https://a.storyblok.com/f/123/2400x1200/abc/photo.jpg';

    public function testStoryblokImagesAreResizedWithDimensionsAndSrcset(): void
    {
        $html = $this->render(self::SRC);

        $this->assertStringContainsString('src="' . self::SRC . '/m/1200x0"', $html);
        $this->assertStringContainsString(
            'srcset="' . self::SRC . '/m/400x0 400w, ' . self::SRC . '/m/800x0 800w, ' . self::SRC . '/m/1200x0 1200w"',
            $html
        );
        $this->assertStringContainsString('width="1200" height="600"', $html);
        $this->assertStringContainsString('loading="lazy" decoding="async"', $html);
    }

    public function testSmallImagesAreNotEnlarged(): void
    {
        $src = 'https://a.storyblok.com/f/123/600x300/abc/icon.png';

        $this->assertStringContainsString(
            'srcset="' . $src . '/m/400x0 400w, ' . $src . '/m/600x0 600w"',
            $this->render($src)
        );
    }

    public function testOtherImagesOnlyLoadLazily(): void
    {
        $html = $this->render('https://example.com/a.jpg');

        $this->assertStringContainsString('src="https://example.com/a.jpg"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('srcset', $html);
    }

    public function testQueryStringsGoAfterTheTransformation(): void
    {
        $html = $this->render(self::SRC . '?cv=123');

        $this->assertStringContainsString('src="' . self::SRC . '/m/1200x0?cv=123"', $html);
        $this->assertStringContainsString(self::SRC . '/m/400x0?cv=123 400w', $html);
    }

    public function testAlreadyTransformedImagesAreLeftAsTheyAre(): void
    {
        $src = self::SRC . '/m/600x300/filters:focal(10x10:20x20)';
        $html = $this->render($src);

        $this->assertStringContainsString('src="' . $src . '"', $html);
        $this->assertStringNotContainsString('srcset', $html);
    }

    private function render(string $src): string
    {
        $editor = new Editor(['extensions' => [new Storyblok([
            'override_extensions' => ['image' => new Image(new Asset())],
        ])]]);
        $editor->setContent(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['src' => $src, 'alt' => 'A']]]],
        ]]);

        return $editor->getHtml();
    }
}
