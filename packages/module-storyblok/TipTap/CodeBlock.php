<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\TipTap;

use Tiptap\Nodes\CodeBlock as CodeBlockNode;

/**
 * Storyblok code block that shows Magento CMS directives literally instead of resolving them.
 *
 * Replaces Storyblok\Tiptap\Node\CodeBlock, which is final, so this extends TipTap's own node under Storyblok's name.
 */
class CodeBlock extends CodeBlockNode
{
    /**
     * @var string
     */
    public static $name = 'code_block';

    /**
     * Render the code block, flagging its text so Text leaves directives in it alone.
     *
     * Runs before the children are rendered.
     *
     * @param object $node
     * @param array $HTMLAttributes
     *
     * @return array
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        foreach ($node->content ?? [] as $child) {
            $child->{Text::LITERAL} = true;
        }

        return parent::renderHTML($node, $HTMLAttributes);
    }
}
