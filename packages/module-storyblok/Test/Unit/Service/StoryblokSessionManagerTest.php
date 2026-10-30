<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Service;

use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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

        $session = $this->session($params);

        $this->assertSame($expected, $session->isValidEditorSession());
        $this->assertSame($expected ? $params : [], $session->getEditorTokenParams());
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

    private function session(array $tokenParams): StoryblokSessionManager
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            [StoryblokSessionManager::STORYBLOK_VISUAL_EDITOR_PARAM, null, '1'],
            [StoryblokSessionManager::STORYBLOK_TOKEN_PARAM, null, $tokenParams],
        ]);
        $config = $this->createStub(Config::class);
        $config->method('getApiToken')->willReturn(self::PREVIEW_TOKEN);

        return new StoryblokSessionManager($request, $config);
    }
}
