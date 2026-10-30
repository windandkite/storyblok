<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Block;

use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Block\Story;

class StoryTest extends TestCase
{
    public function testAMissingStoryIsTaggedWithItsSlugForThePublishWebhook(): void
    {
        $block = $this->getMockBuilder(Story::class)->disableOriginalConstructor()->onlyMethods(['getStory'])->getMock();
        $block->expects($this->exactly(2))->method('getStory')->willReturn(null);

        $block->setData('slug', '/global/announcement-bar');
        $this->assertSame(['storyblok_story_slug_global/announcement-bar'], $block->getIdentities(), 'the full slug the webhook cleans');

        $block->unsetData('slug');
        $this->assertSame([], $block->getIdentities());
    }
}
