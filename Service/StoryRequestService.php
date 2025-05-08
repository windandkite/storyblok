<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\App\RequestInterface;
use Storyblok\Api\Domain\Value\Dto\Version;
use Storyblok\Api\Domain\Value\Resolver\RelationCollection;
use Storyblok\Api\Request\StoryRequest;
use WindAndKite\Storyblok\Controller\Router;
use WindAndKite\Storyblok\Scope\Config;

class StoryRequestService
{
    public function __construct(
        private Config $scopeConfig,
        private RequestInterface $request,
    ) {}

    public function getStoryRequest(
        array $data
    ): StoryRequest {
        $language = $data['language'] ?? 'default';
        $relations = $data['resolve_relations'] ?? null;
        $version = $data['version'] ?? null;

        if (!$version) {
            $version = Version::Published;

            if ($this->request->getParam(Router::STORYBLOK_EDITOR_KEY) || $this->scopeConfig->isDevModeEnabled()) {
                $version = Version::Draft;
            }
        }

        return new StoryRequest(
            $language,
            $version,
            $relations ? new RelationCollection(array_values($relations)) : new RelationCollection(),
        );
    }
}
