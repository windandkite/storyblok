<?php

namespace WindAndKite\Storyblok\Service;

use Psr\Log\LoggerInterface;
use WindAndKite\Storyblok\Model\StoryblokClientWrapper;

abstract class AbstractService
{
    /**
     * @param StoryblokClientWrapper $storyblokClientWrapper
     * @param LoggerInterface $logger ,
     */
    public function __construct(
        protected StoryblokClientWrapper $storyblokClientWrapper,
        protected LoggerInterface $logger,
    ) {}
}
