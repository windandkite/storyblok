<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Service;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Storyblok\Api\Domain\Value\Dto\Version;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryblokSessionManager;

class StoryblokSessionManagerTest extends TestCase
{
    private const PREVIEW_TOKEN = 'preview-token';

    #[DataProvider('tokenProvider')]
    public function testEditorTokenValidation(int $age, bool $signed, bool $expected): void
    {
        $timestamp = (string)(time() - $age);
        $params = [
            'space_id' => '1',
            'timestamp' => $timestamp,
            'token' => $signed ? sha1('1:' . self::PREVIEW_TOKEN . ':' . $timestamp) : 'forged',
        ];

        $session = $this->session([
            StoryblokSessionManager::STORYBLOK_VISUAL_EDITOR_PARAM => '1',
            StoryblokSessionManager::STORYBLOK_TOKEN_PARAM => $params,
        ]);

        $this->assertSame($expected, $session->isValidEditorSession());
        $this->assertSame($expected ? $params : [], $session->getEditorTokenParams());
        $this->assertSame($expected ? Version::Draft : Version::Published, $session->getStoryblokApiVersion());
    }

    public static function tokenProvider(): array
    {
        return [
            'fresh' => [0, true, true],
            'still editing after 30 minutes' => [1800, true, true],
            'older than an hour' => [3700, true, false],
            'forged' => [0, false, false],
        ];
    }

    public function testMalformedTokensAreRejectedNotFatal(): void
    {
        $session = $this->session([
            StoryblokSessionManager::STORYBLOK_VISUAL_EDITOR_PARAM => '1',
            StoryblokSessionManager::STORYBLOK_TOKEN_PARAM => ['space_id' => ['1'], 'timestamp' => '1', 'token' => '1'],
        ]);

        $this->assertFalse($session->isValidEditorSession());
    }

    public function testVisitorsCantRequestDrafts(): void
    {
        $session = $this->session([StoryblokSessionManager::STORYBLOK_VERSION_PARAM => 'draft']);

        $this->assertSame(Version::Published, $session->getStoryblokApiVersion());
        $this->assertFalse($session->isDraftRequest());
    }

    #[DataProvider('shareLinkProvider')]
    public function testShareLinks(string $signedPath, int $expires, string $tamper, bool $expected, int $signedStore = 1): void
    {
        $param = $this->session([], storeId: $signedStore)->createShareParam($signedPath, $expires) . $tamper;
        $session = $this->session([StoryblokSessionManager::SHARE_PARAM => $param], '/about/team/');

        $this->assertSame($expected, $session->isValidShareLink());
        $this->assertSame($expected ? Version::Draft : Version::Published, $session->getStoryblokApiVersion());
    }

    public static function shareLinkProvider(): array
    {
        return [
            'valid' => ['about/team', time() + 60, '', true],
            'expired' => ['about/team', time() - 1, '', false],
            'signed for another page' => ['about', time() + 60, '', false],
            'tampered signature' => ['about/team', time() + 60, 'x', false],
            'created in another store' => ['about/team', time() + 60, '', false, 2],
        ];
    }

    public function testShareParamMustBeAString(): void
    {
        $this->assertFalse($this->session([StoryblokSessionManager::SHARE_PARAM => ['1.x']])->isValidShareLink());
    }

    private function session(array $params, string $pathInfo = '/', int $storeId = 1): StoryblokSessionManager
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(static fn ($key) => $params[$key] ?? null);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $config = $this->createStub(Config::class);
        $config->method('getApiToken')->willReturn(self::PREVIEW_TOKEN);
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('hash')->willReturnCallback(static fn ($data) => hash_hmac('sha256', $data, 'crypt-key'));

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new StoryblokSessionManager($request, $config, $encryptor, $storeManager);
    }
}
