<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\Test\Unit\Hydrator;

use Magento\Framework\Exception\NoSuchEntityException;
use Storyblok\Api\Request\StoryRequest;
use WindAndKite\Storyblok\Api\StoryRepositoryInterface;
use WindAndKite\Storyblok\Model\Story;
use WindAndKite\Storyblok\Service\StoryRequestService;
use WindAndKite\StoryblokComponentsCommon\Hydrator\Reference;

class ReferenceTest extends HydratorTestCase
{
    private const UUID = '11111111-2222-3333-4444-555555555555';

    public function testReferencedStoryIsLoadedAndTagged(): void
    {
        $story = (new \ReflectionClass(Story::class))->newInstanceWithoutConstructor();
        $story->setData(['id' => 42, 'uuid' => self::UUID]);

        $repository = $this->createMock(StoryRepositoryInterface::class);
        $repository->expects($this->once())->method('getByUuid')->with(self::UUID)->willReturn($story);

        $block = $this->hydrator($repository)->populate($this->blockFor(['story' => self::UUID]));

        $this->assertSame($story, $block->getData('referenced_story'));
        $this->assertSame(['storyblok_story_id_42'], $block->getIdentities(), 'publishing the referenced story purges the page');
    }

    public function testInvalidOrMissingStoriesHydrateToNull(): void
    {
        $repository = $this->createStub(StoryRepositoryInterface::class);
        $repository->method('getByUuid')->willThrowException(new NoSuchEntityException());

        foreach (['', 'not-a-uuid', 'global/usp', self::UUID] as $value) {
            $block = $this->hydrator($repository)->populate($this->blockFor(['story' => $value]));

            $this->assertNull($block->getData('referenced_story'), var_export($value, true));
            $this->assertSame([], $block->getIdentities());
        }
    }

    public function testInvalidUuidsSkipTheApi(): void
    {
        $repository = $this->createMock(StoryRepositoryInterface::class);
        $repository->expects($this->never())->method('getByUuid');

        $this->hydrator($repository)->populate($this->blockFor(['story' => 'global/usp']));
    }

    private function hydrator(StoryRepositoryInterface $repository): Reference
    {
        $requests = $this->createStub(StoryRequestService::class);
        $requests->method('getStoryRequest')->willReturn(new StoryRequest());

        return new Reference($repository, $requests);
    }
}
