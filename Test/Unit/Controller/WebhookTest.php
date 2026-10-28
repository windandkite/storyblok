<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Controller;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Webapi\Rest\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WindAndKite\Storyblok\Controller\Webhook\Index;
use WindAndKite\Storyblok\Scope\Config;
use WindAndKite\Storyblok\Service\StoryblokCacheService;

class WebhookTest extends TestCase
{
    private const BODY = '{"action":"published","story_id":5}';

    #[DataProvider('requestProvider')]
    public function testAuthentication(?string $secret, ?string $signature, ?string $key, string $body, int $status): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getContent')->willReturn($body);
        $request->method('getHeader')->willReturnCallback(static fn ($name) => $name === 'webhook-signature' ? ($signature ?? false) : false);
        $request->method('getParam')->willReturnCallback(static fn ($name) => $name === Index::KEY_PARAM ? $key : null);

        $config = $this->createStub(Config::class);
        $config->method('getWebhookSecret')->willReturn($secret);

        $cacheService = $this->createMock(StoryblokCacheService::class);
        $cacheService->expects($status === 200 ? $this->once() : $this->never())->method('cleanCacheByTags');

        $code = 200;
        $result = $this->createStub(Json::class);
        $result->method('setData')->willReturnSelf();
        $result->method('setHttpResponseCode')->willReturnCallback(function ($value) use (&$code, $result) {
            $code = $value;

            return $result;
        });
        $resultFactory = $this->createStub(JsonFactory::class);
        $resultFactory->method('create')->willReturn($result);

        (new Index($resultFactory, $this->createStub(LoggerInterface::class), $cacheService, new JsonSerializer(), $config, $request))
            ->execute();

        $this->assertSame($status, $code);
    }

    public static function requestProvider(): array
    {
        return [
            'signed' => ['secret', hash_hmac('sha1', self::BODY, 'secret'), null, self::BODY, 200],
            'key in the URL' => ['secret', null, 'secret', self::BODY, 200],
            'wrong key' => ['secret', null, 'guess', self::BODY, 401],
            'wrong signature' => ['secret', 'forged', 'secret', self::BODY, 401],
            'no secret generated, empty-key signature' => [null, hash_hmac('sha1', self::BODY, ''), '', self::BODY, 401],
            'not a JSON object' => ['secret', null, 'secret', '"x"', 400],
        ];
    }
}
