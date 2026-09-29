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

        // External URLs, SVGs and GIFs can't go through Storyblok's image service: use them as they are.
        if (!$this->isTransformable($assetData)) {
            return (string)$assetData['filename'];
        }

        $filename = $assetData['filename'];
        $dimensions = $this->getDimensionsFromFilename($filename);

        if (($options['width'] ?? null) || ($options['height'] ?? null)) {
            $dimensions = [
                'width' => $options['width'] ?? 0,
                'height' => $options['height'] ?? 0,
            ];
        }

        if ($options['flip_vertical'] ?? null) {
            $dimensions['height'] = '-' . ($dimensions['height'] ?? 0);
        }

        if ($options['flip_horizontal'] ?? null) {
            $dimensions['width'] = '-' . ($dimensions['width'] ?? 0);
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
            $filters[] = 'grayscale()';
        }

        $url = $filename . '/m/' . ($dimensions['width'] ?? 0) . 'x' . ($dimensions['height'] ?? 0);

        if ($filters) {
            $url .= '/filters:' . implode(':', $filters);
        }

        return $url;
    }

    /**
     * Whether the value is a Storyblok asset field with a file selected.
     *
     * @param mixed $assetData
     *
     * @return bool
     */
    public function isValidAsset(mixed $assetData): bool
    {
        return is_array($assetData)
            && ($assetData['fieldtype'] ?? null) === 'asset'
            && !empty($assetData['filename']);
    }

    /**
     * Original pixel dimensions encoded in the Storyblok asset URL, or [] when unknown.
     *
     * @param array $assetData
     *
     * @return array{width?: int, height?: int}
     */
    public function getDimensions(array $assetData): array
    {
        return $this->isValidAsset($assetData) ? $this->getDimensionsFromFilename($assetData['filename']) : [];
    }

    /**
     * Build a srcset string, one transformed URL per width.
     *
     * Pass $options['ratio'] (height / width, e.g. 9/16) to crop every candidate to the same
     * aspect ratio; otherwise height is 0 so the image service keeps the original ratio.
     * Candidates wider (or, with a ratio, taller) than the original image are skipped to avoid upscaling.
     *
     * @param array $assetData
     * @param int[] $widths
     * @param array $options Any transformImage() option, plus "ratio".
     *
     * @return string
     */
    public function getSrcset(
        array $assetData,
        array $widths,
        array $options = [],
    ): string {
        if (!$this->isTransformable($assetData)) {
            return '';
        }

        $ratio = (float)($options['ratio'] ?? 0);
        unset($options['ratio']);

        $widths = array_filter($widths, fn ($width) => $width <= $this->getMaxWidth($assetData, $ratio)) ?: [min($widths)];
        $candidates = [];

        foreach ($widths as $width) {
            $candidates[] = $this->transformImage($assetData, [
                'width' => $width,
                'height' => $ratio ? (int)round($width * $ratio) : 0,
            ] + $options) . ' ' . $width . 'w';
        }

        return implode(', ', $candidates);
    }

    /**
     * CSS object-position ("42% 30%") for the asset's focal point, or null when none is set.
     *
     * @param array $assetData
     *
     * @return string|null
     */
    public function getFocalPointPosition(array $assetData): ?string
    {
        $dimensions = $this->getDimensions($assetData);

        if (
            empty($assetData['focus'])
            || empty($dimensions['width'])
            || empty($dimensions['height'])
            || !preg_match('/^(\d+)x(\d+):/', (string)$assetData['focus'], $matches)
        ) {
            return null;
        }

        return round((int)$matches[1] / $dimensions['width'] * 100, 2) . '% '
            . round((int)$matches[2] / $dimensions['height'] * 100, 2) . '%';
    }

    /**
     * Whether the image service can transform the asset. SVGs, GIFs and assets that aren't hosted on
     * Storyblok (external URLs) can't be processed and are used as-is.
     *
     * @param array $assetData
     *
     * @return bool
     */
    public function isTransformable(array $assetData): bool
    {
        if (!$this->isValidAsset($assetData) || !empty($assetData['is_external_url'])) {
            return false;
        }

        $host = (string)parse_url($assetData['filename'], PHP_URL_HOST);
        $path = (string)parse_url($assetData['filename'], PHP_URL_PATH);

        // Storyblok asset hosts, including regional ones (a-us.storyblok.com, a.storyblokchina.cn).
        return (bool)preg_match('/(^|\.)(storyblok\.com|storyblokchina\.cn)$/i', $host)
            && !preg_match('/\.(svg|gif)$/i', $path);
    }

    /**
     * Widest rendition possible without upscaling, optionally for a height / width crop ratio.
     *
     * @param array $assetData
     * @param float $ratio
     *
     * @return int
     */
    public function getMaxWidth(
        array $assetData,
        float $ratio = 0,
    ): int {
        $dimensions = $this->getDimensions($assetData);

        if (empty($dimensions['width'])) {
            return PHP_INT_MAX;
        }

        return $ratio && !empty($dimensions['height'])
            ? (int)min($dimensions['width'], floor($dimensions['height'] / $ratio))
            : $dimensions['width'];
    }

    private function getFormat(
        array $options,
    ): ?string {
        if (!($options['format'] ?? null)) {
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
        if (!($options['round_corner'] ?? null) || !is_array($options['round_corner'])) {
            return null;
        }

        $roundedCorners = $options['round_corner'];
        $radius = (int)($roundedCorners['radius'] ?? 0);

        if (!$radius) {
            return null;
        }

        $this->validateDimensionAgainstWidthPercentage($radius, 0.5, (int)($dimensions['width'] ?? 0), 'Radius');

        $ellipsis = (int)($roundedCorners['ellipsis'] ?? 0);

        if ($ellipsis) {
            $this->validateDimensionAgainstWidthPercentage($ellipsis, 0.5, (int)($dimensions['width'] ?? 0), 'Ellipsis');
        }

        $backgroundColor = $roundedCorners['background'] ?? null;

        if ($backgroundColor && $backgroundColor !== self::COLOR_TRANSPARENT) {
            $backgroundColor = $this->parseColor($backgroundColor) . ',0';
        }

        if ($backgroundColor === self::COLOR_TRANSPARENT) {
            $backgroundColor = '0,0,0,1';
        }

        if (!$backgroundColor) {
            $backgroundColor = '0,0,0,0';
        }


        return $radius . ($ellipsis ? '|' . $ellipsis : '') . ',' . $backgroundColor;
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
