<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Storyblok\Api\Domain\Value\Dto\Version;
use WindAndKite\Storyblok\Scope\Config;

class StoryblokSessionManager
{
    public const STORYBLOK_VISUAL_EDITOR_PARAM = '_storyblok';
    public const STORYBLOK_VERSION_PARAM = '_storyblok_version';
    public const STORYBLOK_LANGUAGE_PARAM = '_storyblok_lang';
    public const STORYBLOK_TOKEN_PARAM = '_storyblok_tk';
    public const SHARE_PARAM = '_storyblok_share';

    private ?bool $isValidEditorSessionCache = null;
    private ?bool $isValidShareLinkCache = null;

    public function __construct(
        private RequestInterface $request,
        private Config $config,
        private EncryptorInterface $encryptor,
        private StoreManagerInterface $storeManager,
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
            !is_string($storyblokTokenParams['space_id'] ?? null) ||
            !is_string($storyblokTokenParams['timestamp'] ?? null) ||
            !is_string($storyblokTokenParams['token'] ?? null)
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

        $expectedToken = sha1("{$spaceId}:{$previewApiToken}:{$timestamp}");

        // Storyblok's documented validity window. The editor keeps using the same token for live updates and reloads.
        $tolerance = 3600;

        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals($expectedToken, $tokenFromUrl);
    }

    /**
     * Whether this request shows draft content: the Visual Editor, a valid share link, or dev mode.
     * Draft responses are never cached (see DraftNoCachePlugin).
     *
     * @return bool
     */
    public function isDraftRequest(): bool
    {
        return $this->config->isDevModeEnabled() || $this->isValidEditorSession() || $this->isValidShareLink();
    }

    /**
     * Share link parameter for a page path in the current store, so people without a Storyblok account can view its
     * draft. Each website can have its own space, so a link only works in the store it was created in.
     *
     * @param string $path Request path, e.g. "about/team".
     * @param int $expires Unix timestamp.
     *
     * @return string
     */
    public function createShareParam(string $path, int $expires): string
    {
        return $expires . '.' . $this->signSharePath($path, $expires);
    }

    /**
     * A share link signed for this request's path that hasn't expired.
     *
     * @return bool
     */
    public function isValidShareLink(): bool
    {
        if ($this->isValidShareLinkCache !== null) {
            return $this->isValidShareLinkCache;
        }

        $param = $this->request->getParam(self::SHARE_PARAM);
        [$expires, $signature] = is_string($param) ? explode('.', $param, 2) + [1 => ''] : ['', ''];

        $this->isValidShareLinkCache = ctype_digit($expires)
            && (int)$expires >= time()
            && hash_equals($this->signSharePath($this->getSharePath(), (int)$expires), $signature);

        return $this->isValidShareLinkCache;
    }

    /**
     * The path a share link is signed for. Read before routing rewrites the request.
     *
     * @return string
     */
    public function getSharePath(): string
    {
        return trim((string)$this->request->getPathInfo(), '/');
    }

    private function signSharePath(string $path, int $expires): string
    {
        return $this->encryptor->hash(
            'storyblok-share|' . $this->storeManager->getStore()->getId() . '|' . $path . '|' . $expires
        );
    }

    /**
     * The validated Visual Editor token parameters, to forward on follow-up requests (e.g. live preview AJAX).
     *
     * @return array{space_id?: string, timestamp?: string, token?: string}
     */
    public function getEditorTokenParams(): array
    {
        return $this->isValidEditorSession() ? (array)$this->request->getParam(self::STORYBLOK_TOKEN_PARAM) : [];
    }

    public function getStoryblokApiVersion(): Version
    {
        return $this->isDraftRequest() ? Version::Draft : Version::Published;
    }

    public function getRequestedLanguage(): ?string
    {
        if ($this->isValidEditorSession() || $this->config->isDevModeEnabled()) {
            $language = $this->request->getParam(self::STORYBLOK_LANGUAGE_PARAM);

            if (is_string($language) && !empty($language)) {
                return $language;
            }
        }

        return $this->config->getLanguage();
    }
}
