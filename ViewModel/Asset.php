<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\ViewModel;

use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Mixable\Color\Convert;

class Asset implements ArgumentInterface
{
    public const COLOR_TRANSPARENT = 'transparent';

    private const URL_FILTERS = [
        'format' => 'getFormat',
        'quality' => 'getQuality',
        'fill' => 'getFill',
        'focal' => 'getFocalPoint',
        'brightness' => 'getBrightness',
        'blur' => 'getBlur',
        'rotate' => 'getRotation',
        'round_corner' => 'getRoundedCorners',
    ];

    private const ALLOWED_FORMATS = [
        'webp',
        'jpeg',
        'png',
        'avif',
    ];

    public function transformImage(
        array $assetData,
        array $options,
    ): string {
        if (!$assetData || ($assetData['fieldtype'] ?? null) !== 'asset' || !($assetData['filename'] ?? null )) {
            throw new InvalidArgumentException(
                __('Invalid asset data provided.')
            );
        }

        $filename = $assetData['filename'];
        $dimensions = $this->getDimensionsFromFilename($filename);

        if ($options['width'] ?? null || $options['height'] ?? null) {
            $dimensions = [
                'width' => $options['width'] ?? 0,
                'height' => $options['height'] ?? 0,
            ];
        }

        if ($options['flip_vertical'] ?? null) {
            $dimensions['height'] = '-' . $dimensions['height'];
        }

        if ($options['flip_horizontal'] ?? null) {
            $dimensions['width'] = '-' . $dimensions['width'];
        }

        if ($options['crop'] ?? null) {
            $dimensions = $this->getCroppedDimensions($options['crop']);
        }

        $filters = [];

        foreach (self::URL_FILTERS as $filter => $getter) {
            if ($value = $this->$getter($options, $assetData, $dimensions)) {
                $filters[] = $filter . '(' . $value . ')';
            }
        }

        // Special Filter Handling
        if ($options['grayscale'] ?? null) {
            $filters[] = 'graysscale()';
        }

        $url = $filename . '/m/' . $dimensions['width'] . 'x' . $dimensions['height'];

        if ($filters) {
            $url .= '/filters:' . implode(':', $filters);
        }

        return $url;
    }

    private function getFormat(
        array $options,
    ): ?string {
        if (!($optins['format'] ?? null)) {
            return null;
        }

        $format = strtolower($options['format']);

        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            throw new ValidatorException(__('Invalid Image format: %1', $format));
        }

        return $format;
    }

    private function getQuality(
        array $options,
    ): ?int {
        if (!($options['quality'] ?? null)) {
            return null;
        }

        $quality = (int)$options['quality'];

        if ($quality < 0 || $quality > 100) {
            throw new ValidatorException(__('Invalid Image quality: %1', $quality));
        }

        return $quality;
    }

    private function getFill(
        array $options,
    ): ?string {
        if (!($options['fill'] ?? null)) {
            return null;
        }

        $fill = strtolower($options['fill']);

        if ($fill === self::COLOR_TRANSPARENT) {
            return 'transparent';
        }

        return $this->parseColor($fill, 'hex');
    }

    private function getFocalPoint(
        array $options,
        array $assetData,
    ): ?string {
        $focalPoint = $options['focus'] ?? $assetData['focus'] ?? null;

        if (!$focalPoint) {
            return null;
        }

        $this->validateFocalPoint($focalPoint, $assetData, $options);

        return $focalPoint;
    }

    private function getBrightness(
        array $options,
    ): ?int {
        if (!($options['brightness'] ?? null)) {
            return null;
        }

        $brightness = (int)$options['brightness'];

        if ($brightness < -100 || $brightness > 100) {
            throw new ValidatorException(__('Invalid Image brightness: %1', $brightness));
        }

        return $brightness;
    }

    private function getBlur(
        array $options,
    ): ?int {
        if (!($options['blur'] ?? null)) {
            return null;
        }

        $blur = (int)$options['blur'];

        if ($blur < 0 || $blur > 100) {
            throw new ValidatorException(__('Invalid Image blur: %1', $blur));
        }

        return $blur;
    }

    private function getRotation(
        array $options,
    ): ?int {
        if (!($options['rotate'] ?? null)) {
            return null;
        }

        $rotation = (int)$options['rotate'];

        if (!in_array($rotation, [0, 90, 180, 270], true)) {
            throw new ValidatorException(__('Invalid Image rotation: %1', $rotation));
        }

        return $rotation;
    }

    private function getRoundedCorners(
        array $options,
        array $assetData,
        array $dimensions,
    ): ?string {
        if (!($options['round_corner'] ?? null) || !is_array($assetData['round_corner'])) {
            return null;
        }

        $roundedCorners = $options['round_corner'];
        $radius = (int)$roundedCorners['radius'] ?? 0;

        if (!$radius) {
            return null;
        }

        $this->validateDimensionAgainstWidthPercentage($radius, 0.5, $dimensions['width'], 'Radius');

        $ellipsis = (int)$roundedCorners['ellipsis'] ?? 0;

        if ($ellipsis) {
            $this->validateDimensionAgainstWidthPercentage($ellipsis, 0.5, $dimensions['width'], 'Ellipsis');
        }

        $backgroundColor = $roundedCorners['background'] ?? 'null';

        if ($backgroundColor && $backgroundColor !== self::COLOR_TRANSPARENT) {
            $backgroundColor = $this->parseColor($backgroundColor) . ',0';
        }

        if ($backgroundColor === self::COLOR_TRANSPARENT) {
            $backgroundColor = '0,0,0,1';
        }

        if (!$backgroundColor) {
            $backgroundColor = '0,0,0,0';
        }


        return $radius . ($ellipsis ? '|' . $ellipsis : '') . $backgroundColor;
    }

    // Validation Function
    private function validateFocalPoint(
        string $focalPoint,
        array $assetData,
        array $options,
    ): void {
        $dimensions = $this->getDimensionsFromFilename($assetData['filename']);

        if ($options['crop'] ?? null) {
            $dimensions = $this->getCroppedDimensions($options['crop']);
        }

        if (!($dimensions['width'] ?? null) && !($dimensions['height'] ?? null)) {
            throw new ValidatorException(__('Invalid dimensions for focal point.'));
        }

        $coordinates = explode(':', $focalPoint);

        if (count($coordinates) !== 2) {
            throw new ValidatorException(__('Invalid focal point format. Expected X1xY1:X2xY2'));
        }

        $start = explode('x', $coordinates[0]);
        $end = explode('x', $coordinates[1]);

        if (count($start) !== 2 || count($end) !== 2) {
            throw new ValidatorException(__('Invalid focal point format. Expected X1xY1:X2xY2'));
        }

        $x1 = (int)$start[0];
        $y1 = (int)$start[1];
        $x2 = (int)$end[0];
        $y2 = (int)$end[1];

        if (!is_int($x1) || $x1 < 0 || !is_int($y1) || $y1 < 0 || !is_int($x2) || $x2 < 0 || !is_int($y2) || $y2 < 0) {
            throw new ValidatorException(__('Focal point coordinates must be non-negative integers.'));
        }

        if ($x2 - $x1 !== 1 || $y2 - $y1 !== 1) {
            throw new ValidatorException(__('Focal point must represent a 1x1 pixel square.'));
        }

        if ($dimensions) {
            $imageWidth = $dimensions['width'];
            $imageHeight = $dimensions['height'];

            if ($x1 >= $imageWidth || $y1 >= $imageHeight || $x2 > $imageWidth || $y2 > $imageHeight) {
                throw new ValidatorException(__('Focal point is outside the image bounds.'));
            }
        }
    }

    private function validateDimensionAgainstWidthPercentage(
        int $value,
        float $percentage,
        int $width,
        string $valueName
    ): void {
        if ($value < 0) {
            throw new ValidatorException(__("%1 must be 0 or greater.", $valueName));
        }

        if ($value > $width * $percentage) {
            throw new ValidatorException(
                __("%1 cannot be greater than %2% of the image width.", $valueName, $percentage * 100)
            );
        }
    }

    // Utility Functions
    private function parseColor(
        string $color,
        string $desiredType = 'rgb'
    ): string {
        if (!in_array($desiredType, ['rgb', 'hex'])) {
            throw new ValidatorException(__('Unhandled Desired Color Type "%1".', $desiredType));
        }

        $currentType = match (true) {
            str_starts_with($color, '#') || strlen($color) === 6 || strlen($color) === 3 => 'hex',
            preg_match('/^\d{1,3},\d{1,3},\d{1,3}$/', $color) => 'rgb',
            default => null,
        };

        if (!$currentType) {
            throw new ValidatorException(__('Invalid color format: %s.', $color));
        }

        $convertedValue = match (true) {
            $currentType === $desiredType => $color,
            $currentType === 'rgb' && $desiredType === 'hex' => Convert::rgb2hex(explode(',', $color)),
            $currentType === 'hex' && $desiredType === 'rgb' => implode(
                ',',
                Convert::hex2rgb($color) ?? []
            ),
            default => null,
        };

        if (!$convertedValue) {
            throw new ValidatorException(__('Unabled to convert: %1 to %2', $color, $desiredType));
        }

        return $convertedValue;
    }

    private function getDimensionsFromFilename(
        string $filename
    ): array {
        $urlParts = parse_url($filename);

        if ($urlParts && isset($urlParts['path'])) {
            $pathParts = explode('/', trim($urlParts['path'], '/'));

            if (count($pathParts) >= 3) {
                preg_match('/(\d+)x(\d+)/', $pathParts[2], $matches);

                if (count($matches) === 3) {
                    return [
                        'width' => (int)$matches[1],
                        'height' => (int)$matches[2],
                    ];
                }
            }
        }

        return [];
    }

    private function getCroppedDimensions(
        string $crop
    ): array {
        $dimensions = [];

        if ($crop) {
            $cropCoordinates = explode(':', $crop);

            if (count($cropCoordinates) === 2) {
                $start = explode('x', $cropCoordinates[0]);
                $end = explode('x', $cropCoordinates[1]);

                if (count($start) === 2 && count($end) === 2) {
                    $x1 = (int)$start[0];
                    $y1 = (int)$start[1];
                    $x2 = (int)$end[0];
                    $y2 = (int)$end[1];
                    $dimensions['width'] = abs($x2 - $x1);
                    $dimensions['height'] = abs($y2 - $y1);
                }
            }
        }

        return $dimensions;
    }
}
