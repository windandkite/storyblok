<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Model\Hydrator;

use Magento\Framework\App\Response\Http as HttpResponse;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\Model\Block as StoryblokBlock;
use WindAndKite\Storyblok\Model\Hydrator\MagentoVariables;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;
use WindAndKite\Storyblok\ViewModel\Variables;

class MagentoVariablesTest extends TestCase
{
    public function testWithoutAnEditorSessionNothingIsExposed(): void
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects($this->never())->method('setNoCacheHeaders');

        $block = $this->hydrate(false, $response);

        $this->assertNull($block->getData('variables_view_model'));
    }

    public function testEditorsGetTheListingUncached(): void
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects($this->once())->method('setNoCacheHeaders');

        $block = $this->hydrate(true, $response);

        $this->assertInstanceOf(Variables::class, $block->getData('variables_view_model'));
    }

    private function hydrate(bool $isEditor, HttpResponse $response): Block
    {
        $session = $this->createStub(StoryblokSessionManager::class);
        $session->method('isValidEditorSession')->willReturn($isEditor);

        // Only setData/getData are used; Block::getData falls back to the Storyblok block, so give it a real one.
        $block = (new \ReflectionClass(Block::class))->newInstanceWithoutConstructor();
        $block->setData('block', (new \ReflectionClass(StoryblokBlock::class))->newInstanceWithoutConstructor());

        return (new MagentoVariables($this->createStub(Variables::class), $session, $response))->populate($block);
    }
}
