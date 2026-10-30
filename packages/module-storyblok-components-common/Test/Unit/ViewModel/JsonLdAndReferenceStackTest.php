<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use WindAndKite\StoryblokComponentsCommon\ViewModel\JsonLd;
use WindAndKite\StoryblokComponentsCommon\ViewModel\ReferenceStack;

class JsonLdAndReferenceStackTest extends TestCase
{
    public function testJsonLdCannotBreakOutOfScript(): void
    {
        $html = (new JsonLd())->render(['@type' => 'Thing', 'name' => '</script><script>alert(1)</script>']);

        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringStartsWith('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Thing"', $html);
        $this->assertSame('', (new JsonLd())->render([]));
    }

    public function testPlainText(): void
    {
        $this->assertSame(
            "Free delivery over £50.\nReturns & exchanges",
            (new JsonLd())->toPlainText('<div><p>Free <strong>delivery</strong> over £50.</p><p>Returns &amp; exchanges</p></div>')
        );
    }

    public function testPlainTextLeavesOutScriptsAndStyles(): void
    {
        $this->assertSame(
            "Answer\nMore",
            (new JsonLd())->toPlainText('<p>Answer</p><script type="application/ld+json">{"@type":"Product"}</script><style>p{}</style><p>More</p>')
        );
    }

    public function testReferenceStackBlocksCyclesAndDepth(): void
    {
        $stack = new ReferenceStack();

        $this->assertFalse($stack->enter('a', 'a'), 'self reference');
        $this->assertTrue($stack->enter('b', 'a'));
        $this->assertFalse($stack->enter('b', 'b'), 'cycle through the stack');
        $this->assertTrue($stack->enter('c', 'b'));
        $stack->leave('c');
        $stack->leave('b');
        $this->assertTrue($stack->enter('b', 'a'), 'siblings can reuse a story');
        $this->assertFalse($stack->enter('a', 'b'), 'back to the page the references started on');
        $stack->leave('b');
        $this->assertTrue($stack->enter('x', 'y'), 'a new page starts a new chain');
        $stack->leave('x');
        $stack->enter('b', 'a');

        foreach (['c', 'd', 'e', 'f'] as $uuid) {
            $stack->enter($uuid);
        }

        $this->assertFalse($stack->enter('g'), 'max depth');
    }
}
