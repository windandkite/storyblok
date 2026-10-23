<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\TipTap;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Filter\Template;
use PHPUnit\Framework\TestCase;
use Storyblok\Tiptap\Extension\Storyblok;
use Tiptap\Editor;
use WindAndKite\Storyblok\TipTap\Link;
use WindAndKite\Storyblok\TipTap\Text;

class DirectivesTest extends TestCase
{
    private const PHONE = '{{config path="general/store_information/phone"}}';

    /**
     * @var FilterProvider
     */
    private FilterProvider $filterProvider;

    protected function setUp(): void
    {
        $filter = $this->createStub(Template::class);
        $filter->method('filter')->willReturnCallback(
            fn (string $value) => str_replace(self::PHONE, '01234 <b>5</b>', $value)
        );
        $this->filterProvider = $this->createStub(FilterProvider::class);
        $this->filterProvider->method('getPageFilter')->willReturn($filter);
    }

    public function testDirectivesInTextAreResolvedAndTheRestIsEscaped(): void
    {
        $html = $this->render([
            'type' => 'text',
            'text' => 'Call <us> on ' . self::PHONE . ' today',
        ]);

        $this->assertSame('<p>Call &lt;us&gt; on 01234 <b>5</b> today</p>', $html);
    }

    public function testTextWithoutDirectivesIsOnlyEscaped(): void
    {
        $this->assertSame('<p>a &amp; b</p>', $this->render(['type' => 'text', 'text' => 'a & b']));
    }

    public function testDirectivesInLinkUrlsAreResolved(): void
    {
        $html = $this->render([
            'type' => 'text',
            'text' => 'Phone',
            'marks' => [[
                'type' => 'link',
                'attrs' => ['href' => 'tel:' . rawurlencode(self::PHONE), 'linktype' => 'url'],
            ]],
        ]);

        $this->assertSame('<p><a href="tel:01234 &lt;b&gt;5&lt;/b&gt;">Phone</a></p>', $html);
    }

    private function render(array $textNode): string
    {
        $editor = new Editor(['extensions' => [new Storyblok([
            'override_extensions' => [
                'text' => new Text($this->filterProvider),
                'link' => new Link($this->filterProvider),
            ],
        ])]]);
        $editor->setContent(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [$textNode]]]]);

        return $editor->getHtml();
    }
}
