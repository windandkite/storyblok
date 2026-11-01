<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Api\Data\StoryInterface;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\ViewModel\FieldRenderer;

class FieldRendererTest extends TestCase
{
    public function testDelegatesToTheFieldRenderer(): void
    {
        $story = $this->createStub(StoryInterface::class);
        $blok = ['_uid' => '1', 'component' => 'link'];
        $block = $this->createStub(Block::class);

        $renderer = $this->createMock(FieldRendererInterface::class);
        $renderer->expects($this->once())->method('renderField')->with($blok, $story)->willReturn('<a>x</a>');
        $renderer->expects($this->once())->method('createBlockInstance')->with($blok, $story)->willReturn($block);

        $viewModel = new FieldRenderer($renderer);

        $this->assertSame('<a>x</a>', $viewModel->renderField($blok, $story));
        $this->assertSame($block, $viewModel->createBlock($blok, $story));
    }
}
