<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\App\RequestInterface;
use Storyblok\Api\Domain\Value\Dto\Version;
use WindAndKite\Storyblok\Scope\Config;

class StoryblokSessionManager
{
    public const STORYBLOK_VISUAL_EDITOR_PARAM = '_storyblok';
    public const STORYBLOK_VERSION_PARAM = '_storyblok_version';
    public const STORYBLOK_LANGUAGE_PARAM = '_storyblok_lang';
    public const STORYBLOK_TOKEN_PARAM = '_storyblok_tk';

    private ?bool $isValidEditorSessionCache = null;

    public function __construct(
        private RequestInterface $request,
        private Config $config,
    ) {}

    public function isValidEditorSession(): bool
    {
        if ($this->isValidEditorSessionCache !== null) {
            return $this->isValidEditorSessionCache;
        }

        if ($this->request->getParam(self::STORYBLOK_VISUAL_EDITOR_PARAM) === null) {
            $this->isValidEditorSessionCache = false;
            return false;
        }

        $storyblokTokenParams = $this->request->getParam(self::STORYBLOK_TOKEN_PARAM);

        if (
            !is_array($storyblokTokenParams) ||
            !isset($storyblokTokenParams['space_id'], $storyblokTokenParams['timestamp'], $storyblokTokenParams['token'])
        ) {
            $this->isValidEditorSessionCache = false;
            return false;
        }

        $isValid = $this->validateStoryblokToken(
            $storyblokTokenParams['space_id'],
            (int)$storyblokTokenParams['timestamp'],
            $storyblokTokenParams['token']
        );

        $this->isValidEditorSessionCache = $isValid;

        return $isValid;
    }

    private function validateStoryblokToken(string $spaceId, int $timestamp, string $tokenFromUrl): bool
    {
        $previewApiToken = $this->config->getApiToken();

        if (empty($previewApiToken)) {
            return false;
        }

        $expectedToken = sha1("{$spaceId}{$previewApiToken}{$timestamp}");

        $tolerance = 300;

        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        if ($expectedToken !== $tokenFromUrl) {
            return false;
        }

        return true;
    }

    public function getStoryblokApiVersion(): Version
    {
        if ($this->config->isDevModeEnabled() || $this->isValidEditorSession()) {
            return Version::Draft;
        }

        $requestedVersion = $this->request->getParam(self::STORYBLOK_VERSION_PARAM);

        if ($requestedVersion && Version::tryFrom($requestedVersion)) {
            return Version::from($requestedVersion);
        }

        return Version::Published;
    }

    public function getRequestedLanguage(): ?string
    {
        $language = $this->request->getParam(self::STORYBLOK_LANGUAGE_PARAM);

        return is_string($language) && !empty($language) ? $language : null;
    }
}
