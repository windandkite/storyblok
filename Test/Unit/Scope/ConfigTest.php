<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\Scope;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\Scope\Config;

class ConfigTest extends TestCase
{
    #[DataProvider('urlPathProvider')]
    public function testStoryUrlPathDropsTheRoutingFolder(bool $restrict, ?string $folder, string $fullSlug, string $expected): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn($restrict);
        $scopeConfig->method('getValue')->willReturn($folder);

        $config = new Config($scopeConfig, $this->createStub(SerializerInterface::class));

        $this->assertSame($expected, $config->getStoryUrlPath($fullSlug));
    }

    public static function urlPathProvider(): array
    {
        return [
            'no folder restriction' => [false, 'pages', 'pages/about', 'pages/about'],
            'inside the folder' => [true, 'pages', 'pages/about/team', 'about/team'],
            'folder with slashes' => [true, '/pages/', 'pages/about/', 'about'],
            'the folder itself' => [true, 'pages', 'pages/', ''],
            'only a name prefix' => [true, 'pages', 'pages-old/about', 'pages-old/about'],
            'outside the folder' => [true, 'pages', 'blog/post', 'blog/post'],
        ];
    }
}
