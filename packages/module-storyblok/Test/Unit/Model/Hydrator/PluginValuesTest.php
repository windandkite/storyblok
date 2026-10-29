<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Model\Hydrator;

use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Api\PluginValueHydratorInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Block\Story;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\Hydrator\PluginValues;
use WindAndKite\Storyblok\Model\Story as StoryblokStory;
use WindAndKite\Storyblok\Service\BlockHydrationManager;

class PluginValuesTest extends TestCase
{
    public function testRegisteredPluginValuesAreHydrated(): void
    {
        $clip = ['plugin' => 'storyblok-vimeo', 'id' => '42'];
        $block = $this->block([
            'component' => 'video',
            'clip' => $clip,
            'other' => ['plugin' => 'unregistered', 'x' => 1],
            'title' => 'Not a plugin',
            'gallery' => [['plugin' => 'storyblok-vimeo']],
            'story' => ['plugin' => 'storyblok-vimeo'],
        ]);

        $vimeo = $this->createMock(PluginValueHydratorInterface::class);
        $vimeo->expects($this->once())->method('hydrate')->with($clip, $block)->willReturn('hydrated');

        (new PluginValues(['storyblok-vimeo' => $vimeo]))->populate($block);

        $this->assertSame('hydrated', $block->getData('clip'));
        $this->assertSame($clip, $block->getBlock()->getData('clip'), 'the raw value stays on the Storyblok block');
        $this->assertSame(['plugin' => 'unregistered', 'x' => 1], $block->getData('other'));
        $this->assertSame('Not a plugin', $block->getData('title'));
        $this->assertSame('page', $block->getData('story'), 'data the block instance holds is never replaced');
    }

    public function testAnEmptyResultIsNotReplacedByTheRawValue(): void
    {
        $block = $this->block(['component' => 'video', 'clip' => ['plugin' => 'storyblok-vimeo', 'id' => '']]);
        $vimeo = $this->createStub(PluginValueHydratorInterface::class);
        $vimeo->method('hydrate')->willReturn(null);

        (new PluginValues(['storyblok-vimeo' => $vimeo]))->populate($block);

        $this->assertNull($block->getData('clip'));
        $this->assertNull($block->getClip());
        $this->assertSame('video', $block->getData('component'), 'keys the instance doesn\'t have still come from Storyblok');
    }

    public function testStoryContentIsHydratedToo(): void
    {
        $content = (new \ReflectionClass(StoryblokBlock::class))->newInstanceWithoutConstructor();
        $content->setData(['component' => 'page', '_uid' => '1']);
        $story = $this->createStub(StoryblokStory::class);
        $story->method('getData')->willReturn([]);

        $rendered = $this->getMockBuilder(Block::class)->disableOriginalConstructor()->onlyMethods(['toHtml'])->getMock();
        $rendered->expects($this->once())->method('toHtml')->willReturnCallback(static fn () => (string)$rendered->getData('hydrated'));
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($rendered);

        $hydrator = $this->createStub(BlockHydratorInterface::class);
        $hydrator->method('populate')->willReturnCallback(static fn (Block $block) => $block->setData('hydrated', 'yes'));

        $storyBlock = $this->getMockBuilder(Story::class)->disableOriginalConstructor()
            ->onlyMethods(['getStory', 'getContent', 'getLayout'])->getMock();
        $storyBlock->method('getStory')->willReturn($story);
        $storyBlock->method('getContent')->willReturn($content);
        $storyBlock->expects($this->once())->method('getLayout')->willReturn($layout);
        (new \ReflectionProperty(Story::class, 'blockHydrationManager'))->setValue($storyBlock, new BlockHydrationManager(['page' => [$hydrator]]));

        $this->assertSame('yes', $storyBlock->getContentHtml());
    }

    public function testStarHydratorsRunForEveryComponentFirst(): void
    {
        $all = $this->createStub(BlockHydratorInterface::class);
        $video = $this->createStub(BlockHydratorInterface::class);
        $manager = new BlockHydrationManager(['*' => [$all], 'video' => [$video]]);

        $this->assertSame([$all, $video], $manager->getForComponent('video'));
        $this->assertSame([$all], $manager->getForComponent('text'));
        $this->assertSame([], (new BlockHydrationManager())->getForComponent('text'));
    }

    private function block(array $data): Block
    {
        // Only setData/getData are used; Block::getData falls back to the Storyblok block, so give it a real one.
        $storyblokBlock = (new \ReflectionClass(StoryblokBlock::class))->newInstanceWithoutConstructor();
        $storyblokBlock->setData($data);
        $block = (new \ReflectionClass(Block::class))->newInstanceWithoutConstructor();
        $block->setData('block', $storyblokBlock);
        $block->setData('story', 'page');

        return $block;
    }
}
