<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Block;

use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Block\Story;
use WindAndKite\Storyblok\Service\StoryblokCacheService;

class StoryTest extends TestCase
{
    public function testAMissingStoryIsTaggedForThePublishWebhook(): void
    {
        $block = $this->getMockBuilder(Story::class)->disableOriginalConstructor()->onlyMethods(['getStory'])->getMock();
        $block->expects($this->once())->method('getStory')->willReturn(null);

        $this->assertSame([StoryblokCacheService::NOT_FOUND_TAG], $block->getIdentities());
    }
}
