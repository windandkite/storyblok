<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Storyblok\Api\Domain\Value\Resolver\RelationCollection;
use Storyblok\Api\Request\StoryRequest;

class StoryRequestService
{
    public function __construct(
        private StoryblokSessionManager $storyblokSessionManager,
    ) {}

    public function getStoryRequest(
        array $data
    ): StoryRequest {
        $version = $data['version'] ?? $this->storyblokSessionManager->getStoryblokApiVersion();

        $language = $data['language'] ?? $this->storyblokSessionManager->getRequestedLanguage();
        $language = $language ?? 'default';

        $relations = $data['resolve_relations'] ?? null;

        return new StoryRequest(
            $language,
            $version,
            $relations ? new RelationCollection(array_values($relations)) : new RelationCollection([])
        );
    }
}
