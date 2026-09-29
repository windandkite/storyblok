<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Block;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template\Context;
use WindAndKite\Storyblok\Api\FieldRendererInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Model\BlockFactory;
use WindAndKite\Storyblok\Model\Story;
use WindAndKite\Storyblok\Model\StoryRepository;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;
use WindAndKite\Storyblok\Service\StoryRequestService;
use WindAndKite\Storyblok\ViewModel\Asset;
use WindAndKite\Storyblok\ViewModel\Link;

class BlockTest extends TestCase
{
    private const ATTRIBUTES = "data-blok-c='{\"id\":1}' data-blok-uid='1-abc'";

    #[DataProvider('htmlProvider')]
    public function testEditableAttributesGoOnTheFirstElementTag(string $html, string $expected): void
    {
        $method = new \ReflectionMethod(Block::class, 'addAttributesToFirstTag');
        $block = (new \ReflectionClass(Block::class))->newInstanceWithoutConstructor();

        $this->assertSame($expected, $method->invoke($block, $html, self::ATTRIBUTES));
    }

    public static function htmlProvider(): array
    {
        return [
            'plain' => [
                '<div class="a"><p>x</p></div>',
                '<div class="a" ' . self::ATTRIBUTES . '><p>x</p></div>',
            ],
            '">" inside a class value' => [
                '<div class="grid [&>:not(.sb-column)]:col-span-full [.x>&:first-child]:-ml-4"><p>x</p></div>',
                '<div class="grid [&>:not(.sb-column)]:col-span-full [.x>&:first-child]:-ml-4" ' . self::ATTRIBUTES . '><p>x</p></div>',
            ],
            'single-quoted value with ">"' => [
                "<section data-x='a>b'>x</section>",
                "<section data-x='a>b' " . self::ATTRIBUTES . '>x</section>',
            ],
            'leading script and comment are skipped' => [
                "<!-- c --><script>if (a > b) {}</script>\n<ul class=\"flex\"><li>x</li></ul>",
                "<!-- c --><script>if (a > b) {}</script>\n<ul class=\"flex\" " . self::ATTRIBUTES . '><li>x</li></ul>',
            ],
            'self-closing tag keeps its slash' => [
                '<hr class="my-8" />',
                '<hr class="my-8" ' . self::ATTRIBUTES . ' />',
            ],
            'no attributes' => [
                '<article><h3>x</h3></article>',
                '<article ' . self::ATTRIBUTES . '><h3>x</h3></article>',
            ],
        ];
    }

    public function testRendersChildBloksWithTheBlocksStory(): void
    {
        $pageStory = (new \ReflectionClass(Story::class))->newInstanceWithoutConstructor();
        $otherStory = (new \ReflectionClass(Story::class))->newInstanceWithoutConstructor();
        $blok = ['_uid' => '1', 'component' => 'link'];
        $child = $this->createStub(Block::class);

        $fieldRenderer = $this->createMock(FieldRendererInterface::class);
        $fieldRenderer->expects($this->exactly(2))
            ->method('renderField')
            ->willReturnCallback(fn ($value, $story) => $story === $pageStory ? 'page' : ($story === $otherStory ? 'other' : 'none'));
        $fieldRenderer->expects($this->once())->method('createBlockInstance')->with($blok, $pageStory)->willReturn($child);

        $block = $this->block($fieldRenderer);
        $block->setData('story', $pageStory);

        $this->assertSame('page', $block->renderBlok($blok), 'defaults to the block\'s story');
        $this->assertSame('other', $block->renderBlok($blok, $otherStory), 'another story can be passed');
        $this->assertSame($child, $block->createBlokInstance($blok));
    }

    public function testLinkViewModelCanBeSuppliedAsABlockArgument(): void
    {
        $link = $this->createStub(Link::class);
        $block = $this->block($this->createStub(FieldRendererInterface::class));
        $block->setData('link_view_model', $link);

        $this->assertSame($link, $block->getLinkViewModel());
    }

    private function block(FieldRendererInterface $fieldRenderer): Block
    {
        return new Block(
            $this->createStub(BlockFactory::class),
            $fieldRenderer,
            $this->createStub(SerializerInterface::class),
            $this->createStub(StoryblokSessionManager::class),
            $this->createStub(StoryRepository::class),
            $this->createStub(Asset::class),
            $this->createStub(Config::class),
            $this->createStub(Context::class),
            $this->createStub(StoryRequestService::class),
        );
    }
}
