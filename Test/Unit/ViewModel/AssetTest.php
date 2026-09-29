<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use WindAndKite\Storyblok\ViewModel\Asset;

class AssetTest extends TestCase
{
    private const ASSET = [
        'fieldtype' => 'asset',
        'filename' => 'https://a.storyblok.com/f/123/1600x900/abc/photo.jpg',
        'focus' => '',
    ];

    public function testFormatAndGrayscaleFiltersAreApplied(): void
    {
        $url = (new Asset())->transformImage(self::ASSET, ['width' => 800, 'format' => 'webp', 'grayscale' => true]);

        $this->assertSame(self::ASSET['filename'] . '/m/800x0/filters:format(webp):grayscale()', $url);
    }

    public function testHeightOnlyIsRespected(): void
    {
        $this->assertStringContainsString('/m/0x300', (new Asset())->transformImage(self::ASSET, ['height' => 300]));
    }

    public function testSrcsetAppliesRatioAndSkipsUpscaling(): void
    {
        $srcset = (new Asset())->getSrcset(self::ASSET, [400, 800, 2400], ['ratio' => 0.5]);

        $this->assertSame(
            self::ASSET['filename'] . '/m/400x200 400w, ' . self::ASSET['filename'] . '/m/800x400 800w',
            $srcset
        );
    }

    public function testValidAssetAndDimensions(): void
    {
        $asset = new Asset();

        $this->assertTrue($asset->isValidAsset(self::ASSET));
        $this->assertFalse($asset->isValidAsset(['fieldtype' => 'asset', 'filename' => null]));
        $this->assertSame(['width' => 1600, 'height' => 900], $asset->getDimensions(self::ASSET));
    }

    public function testFocalPointPositionAndTransformable(): void
    {
        $asset = new Asset();

        $this->assertSame('25% 50%', $asset->getFocalPointPosition(['focus' => '400x450:401x451'] + self::ASSET));
        $this->assertNull($asset->getFocalPointPosition(self::ASSET));
        $this->assertTrue($asset->isTransformable(self::ASSET));
        $this->assertFalse($asset->isTransformable(['filename' => 'https://a.storyblok.com/f/1/24x24/x/icon.svg'] + self::ASSET));
    }

    public function testSrcsetDoesNotUpscaleCroppedHeight(): void
    {
        // 1600x900 original, square crop: at most 900x900.
        $srcset = (new Asset())->getSrcset(self::ASSET, [480, 768, 1024], ['ratio' => 1]);

        $this->assertSame(
            self::ASSET['filename'] . '/m/480x480 480w, ' . self::ASSET['filename'] . '/m/768x768 768w',
            $srcset
        );
        $this->assertSame(900, (new Asset())->getMaxWidth(self::ASSET, 1));
    }

    public function testExternalAndSvgAssetsAreUsedAsIs(): void
    {
        $asset = new Asset();
        $external = ['fieldtype' => 'asset', 'filename' => 'https://images.example.com/photo.jpg', 'is_external_url' => true];
        $otherHost = ['fieldtype' => 'asset', 'filename' => 'https://cdn.example.com/1600x900/photo.jpg'];
        $svg = ['fieldtype' => 'asset', 'filename' => 'https://a.storyblok.com/f/1/24x24/x/icon.svg'];

        foreach ([$external, $otherHost, $svg] as $data) {
            $this->assertFalse($asset->isTransformable($data), $data['filename']);
            $this->assertSame($data['filename'], $asset->transformImage($data, ['width' => 800]), 'no image-service path appended');
            $this->assertSame('', $asset->getSrcset($data, [400, 800]));
        }

        $this->assertTrue($asset->isTransformable(['filename' => 'https://a-us.storyblok.com/f/1/1600x900/x/p.jpg'] + self::ASSET), 'regional host');
    }
}
