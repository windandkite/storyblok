<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\TipTap;

use Tiptap\Nodes\Text as TextNode;

/**
 * Renders Magento CMS directives ({{config}}, {{customVar}}, {{icon}} on Hyvä, ...) found in rich text.
 *
 * Plain text, and anything Magento doesn't resolve as a directive, is escaped as before. Directive output is
 * trusted the same as on a CMS page. Inline code and code blocks are left literal, so guides can show directives.
 */
class Text extends TextNode
{
    /**
     * Node property set by CodeBlock on its text.
     */
    public const LITERAL = 'wkLiteral';

    /**
     * @param DirectiveFilter $directiveFilter
     * @param array $options
     */
    public function __construct(
        private readonly DirectiveFilter $directiveFilter,
        array $options = [],
    ) {
        parent::__construct($options);
    }

    /**
     * Render a text node, resolving any directives in it.
     *
     * @param object $node
     *
     * @return string
     */
    public function renderText($node): string
    {
        $text = (string)$node->text;

        if (!str_contains($text, '{{') || $this->isCode($node)) {
            return $this->escape($text);
        }

        $html = '';

        foreach (preg_split('/({{.*?}})/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $index => $part) {
            // Odd indexes are the captured directives.
            $html .= ($index % 2 ? $this->directiveFilter->resolve($part) : null) ?? $this->escape($part);
        }

        return $html;
    }

    /**
     * Whether the text is inline code or inside a code block.
     *
     * @param object $node
     *
     * @return bool
     */
    private function isCode(object $node): bool
    {
        if (!empty($node->{self::LITERAL})) {
            return true;
        }

        foreach ($node->marks ?? [] as $mark) {
            if (($mark->type ?? null) === 'code') {
                return true;
            }
        }

        return false;
    }

    /**
     * Escape text the same way TipTap does by default.
     *
     * @param string $text
     *
     * @return string
     */
    private function escape(string $text): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- matches TipTap's own text escaping
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
