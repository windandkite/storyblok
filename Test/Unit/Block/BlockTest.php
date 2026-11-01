<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Block;

use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;

class BlockTest extends TestCase
{
    public function testBlokFieldsNamedStoryOrSlugDontReplaceTheStory(): void
    {
        // A reference blok: its "story" field is the UUID of another story.
        $blok = (new \ReflectionClass(StoryblokBlock::class))->newInstanceWithoutConstructor();
        $blok->setData(['story' => 'uuid-of-another-story', 'slug' => 'other', 'title' => 'Hello']);

        $block = (new \ReflectionClass(Block::class))->newInstanceWithoutConstructor();
        $block->setData('block', $blok);

        $this->assertNull($block->getData('story'));
        $this->assertNull($block->getData('slug'));
        $this->assertSame('Hello', $block->getData('title'));
    }

    public function testAboveTheFoldIsInheritedFromParentBloks(): void
    {
        $section = $this->block(['above_the_fold' => true]);
        $card = $this->block([], $section);
        $image = $this->block([], $card);

        $this->assertTrue($image->isAboveTheFold());
        $this->assertFalse($this->block([], $this->block([]))->isAboveTheFold());
    }

    private function block(array $fields, ?Block $parent = null): Block
    {
        $blok = (new \ReflectionClass(StoryblokBlock::class))->newInstanceWithoutConstructor();
        $blok->setData($fields);

        $block = (new \ReflectionClass(Block::class))->newInstanceWithoutConstructor();
        $block->setData(['block' => $blok, 'parent_blok' => $parent]);

        return $block;
    }
}
