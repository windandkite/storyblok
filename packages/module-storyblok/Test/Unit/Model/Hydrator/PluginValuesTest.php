<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Model\Hydrator;

use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Api\PluginValueHydratorInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\Hydrator\PluginValues;
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
