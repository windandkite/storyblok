<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\ViewModel;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\ViewModel\Link;

class LinkTest extends TestCase
{
    private Link $link;

    protected function setUp(): void
    {
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getDirectUrl')->willReturnCallback(fn ($slug) => 'https://shop.test/' . $slug);
        $urlBuilder->method('getBaseUrl')->willReturn('https://shop.test/');

        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeUrl')->willReturnArgument(0);
        $escaper->method('escapeHtmlAttr')->willReturnArgument(0);

        $config = $this->createStub(Config::class);
        $config->method('getHomeSlug')->willReturn('home');
        $config->method('isHomeEnabled')->willReturn(true);

        $this->link = new Link($urlBuilder, $escaper, $config);
    }

    public function testLinkTypes(): void
    {
        $this->assertSame('https://example.com', $this->link->getUrl(['linktype' => 'url', 'url' => 'https://example.com']));
        $this->assertSame('https://shop.test/about/us', $this->link->getUrl(['linktype' => 'story', 'cached_url' => 'about/us/']));
        $this->assertSame('https://shop.test/blog', $this->link->getUrl(['linktype' => 'story', 'cached_url' => 'x', 'story' => ['full_slug' => 'blog']]));
        $this->assertSame('mailto:hi@example.com', $this->link->getUrl(['linktype' => 'email', 'email' => 'hi@example.com']));
        $this->assertSame('https://a.storyblok.com/f/1/doc.pdf', $this->link->getUrl(['linktype' => 'asset', 'url' => 'https://a.storyblok.com/f/1/doc.pdf']));
        $this->assertSame('https://shop.test/faq#returns', $this->link->getUrl(['linktype' => 'story', 'cached_url' => 'faq', 'anchor' => 'returns']));
    }

    public function testEmptyAndUnsafeLinksResolveToEmptyString(): void
    {
        $this->assertSame('', $this->link->getUrl(null));
        $this->assertSame('', $this->link->getUrl(['linktype' => 'story', 'cached_url' => '']));
        $this->assertSame('', $this->link->getUrl(['linktype' => 'url', 'url' => 'javascript:alert(1)']));
    }

    public function testAttributesAndExternal(): void
    {
        $link = ['linktype' => 'url', 'url' => 'https://example.com', 'target' => '_blank'];

        $this->assertSame('href="https://example.com" target="_blank" rel="noopener noreferrer"', $this->link->getAttributes($link));
        $this->assertTrue($this->link->isExternal($link));
        $this->assertFalse($this->link->isExternal(['linktype' => 'story', 'cached_url' => 'home']));
    }

    public function testHomepageStoryLinksToTheBaseUrl(): void
    {
        $this->assertSame('https://shop.test/', $this->link->getUrl(['linktype' => 'story', 'cached_url' => 'home']));
        $this->assertSame('https://shop.test/', $this->link->getUrl(['linktype' => 'story', 'cached_url' => 'x', 'story' => ['full_slug' => 'home/']]));
        $this->assertSame('https://shop.test/#faq', $this->link->getUrl(['linktype' => 'story', 'cached_url' => 'home', 'anchor' => 'faq']));
        $this->assertSame('https://shop.test/homeware', $this->link->getUrl(['linktype' => 'story', 'cached_url' => 'homeware']));
    }
}
