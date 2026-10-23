<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\TipTap;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Filter\Template;
use PHPUnit\Framework\TestCase;
use Storyblok\Tiptap\Extension\Storyblok;
use Tiptap\Editor;
use WindAndKite\Storyblok\TipTap\DirectiveFilter;
use WindAndKite\Storyblok\TipTap\Link;
use WindAndKite\Storyblok\TipTap\Text;

class DirectivesTest extends TestCase
{
    private const PHONE = '{{config path="general/store_information/phone"}}';

    /**
     * @var DirectiveFilter
     */
    private DirectiveFilter $directiveFilter;

    protected function setUp(): void
    {
        $filter = $this->createStub(Template::class);
        // Mirrors Magento: known directives resolve, unknown ones come back with "{{" in them, malformed ones throw.
        $filter->method('filter')->willReturnCallback(fn (string $value) => match (true) {
            $value === self::PHONE => '01234 <b>5</b>',
            str_starts_with($value, '{{<') => throw new \TypeError('directiveName is null'),
            default => 'signature' . $value . 'signature',
        });
        $filterProvider = $this->createStub(FilterProvider::class);
        $filterProvider->method('getPageFilter')->willReturn($filter);
        $this->directiveFilter = new DirectiveFilter($filterProvider);
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

    public function testUnresolvedBraceFragmentsAreEscapedNotRendered(): void
    {
        $html = $this->render([
            'type' => 'text',
            'text' => '{{<img src=x onerror=alert(1)>}} {{foo <b>x</b>}}',
        ]);

        $this->assertSame(
            '<p>{{&lt;img src=x onerror=alert(1)&gt;}} {{foo &lt;b&gt;x&lt;/b&gt;}}</p>',
            $html
        );
    }

    public function testOnlyTheDirectiveInALinkUrlIsDecoded(): void
    {
        $html = $this->render([
            'type' => 'text',
            'text' => 'Email',
            'marks' => [[
                'type' => 'link',
                'attrs' => [
                    'href' => 'mailto:a@b.test?subject=Sales%26Support&body=' . rawurlencode(self::PHONE) . '%23x',
                    'linktype' => 'url',
                ],
            ]],
        ]);

        $this->assertSame(
            '<p><a href="mailto:a@b.test?subject=Sales%26Support&amp;body=01234 &lt;b&gt;5&lt;/b&gt;%23x">'
            . 'Email</a></p>',
            $html
        );
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
                'text' => new Text($this->directiveFilter),
                'link' => new Link($this->directiveFilter),
            ],
        ])]]);
        $editor->setContent(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [$textNode]]]]);

        return $editor->getHtml();
    }
}
