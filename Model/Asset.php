<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\ValidatorException;
use Mixable\Color\Convert;
use WindAndKite\Storyblok\Api\Data\AssetInterface;

class Asset extends DataObject implements AssetInterface
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

    public function getId(): ?int
    {
        return $this->getData(self::KEY_ID);
    }

    public function getAlt(): ?string
    {
        return $this->getData(self::KEY_ALT);
    }

    public function getName(): ?string
    {
        return $this->getData(self::KEY_NAME);
    }

    public function getFocus(): ?string
    {
        return $this->getData(self::KEY_FOCUS;
    }

    public function getTitle(): ?string
    {
        return $this->getData(self::KEY_TITLE);
    }

    public function getSource(): ?string
    {
        return $this->getData(self::KEY_SOURCE);
    }

    public function getFilename(): ?string
    {
        return $this->getData(self::KEY_FILENAME);
    }

    public function getCopyright(): ?string
    {
        return $this->getData(self::KEY_COPYRIGHT);
    }

    public function getMetaData(): ?array
    {
        return $this->getData(self::KEY_META_DATA);
    }

    public function getIsExternalUrl(): bool
    {
        return $this->getData(self::KEY_IS_EXTERNAL_URL);
    }

    public function getHeight(): ?int
    {
        return $this->getData(self::KEY_HEIGHT);
    }

    public function setHeight(
        int $height,
    ): AssetInterface {
        return $this->setData(self::KEY_HEIGHT, $height);
    }

    public function getWidth(): ?int
    {
        return $this->getData(self::KEY_WIDTH);
    }

    public function setWidth(
        int $width,
    ): AssetInterface {
        return $this->setData(self::KEY_WIDTH, $width);
    }

    public function getFormat(): ?string
    {
        return $this->getData(self::KEY_FORMAT);
    }

    /**
     * @param string $format
     *
     * @return AssetInterface
     * @throws ValidatorException
     */
    public function setFormat(
        string $format,
    ): AssetInterface {
        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            throw new ValidatorException(__('Invalid format'));
        }

        return $this->setData(self::KEY_FORMAT, $format);
    }

    public function getFill(): ?string
    {
        return $this->getData(self::KEY_FILL);
    }

    public function setFill(
        string $color
    ): AssetInterface {
        $color = $colour === self::COLOR_TRANSPARENT ? $color : $this->parseColor($color, 'hex');

        return $this->setData(self::KEY_FILL, $color);
    }

    public function getFocalPoint(): ?string
    {
        return $this->getData(self::KEY_FOCAL_POINT) ?? $this->getFocus();
    }

    public function setFocalPoint(string $focalPoint): AssetInterface
    {
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

        $dimensions = $this->extractImageDimensionsFromFilename();

        if ($dimensions) {
            $imageWidth = $dimensions['width'];
            $imageHeight = $dimensions['height'];

            if ($x1 >= $imageWidth || $y1 >= $imageHeight || $x2 > $imageWidth || $y2 > $imageHeight) {
                throw new ValidatorException(__('Focal point is outside the image bounds.'));
            }
        }

        return $this->setData(self::KEY_FOCAL_POINT, $focalPoint);
    }

    public function getManualCrop(): ?string
    {
        return $this->getData(self::KEY_MANUAL_CROP);
    }

    public function setManualCrop(
        string $manualCrop,
    ): AssetInterface {
        $coordinates = explode(':', $manualCrop);

        if (count($coordinates) !== 2) {
            throw new ValidatorException(__('Invalid manual crop format. Expected X1xY1:X2xY2'));
        }

        $start = explode('x', $coordinates[0]);
        $end = explode('x', $coordinates[1]);

        if (count($start) !== 2 || count($end) !== 2) {
            throw new ValidatorException(__('Invalid manual crop format. Expected X1xY1:X2xY2'));
        }

        $x1 = (int)$start[0];
        $y1 = (int)$start[1];
        $x2 = (int)$end[0];
        $y2 = (int)$end[1];

        if (!is_int($x1) || $x1 < 0 || !is_int($y1) || $y1 < 0 || !is_int($x2) || $x2 < 0 || !is_int($y2) || $y2 < 0) {
            throw new ValidatorException(__('Manual crop coordinates must be non-negative integers.'));
        }

        $dimensions = $this->extractImageDimensionsFromFilename();

        if ($dimensions) {
            $imageWidth = $dimensions['width'];
            $imageHeight = $dimensions['height'];

            if ($x1 >= $imageWidth || $y1 >= $imageHeight || $x2 > $imageWidth || $y2 > $imageHeight || $x2 <= $x1 || $y2 <= $y1) {
                throw new ValidatorException(__('Manual crop coordinates are outside the image bounds or invalid.'));
            }
        }

        return $this->setData(self::KEY_MANUAL_CROP, $manualCrop);
    }

    public function getIsSmartCrop(): ?bool
    {
        return $this->getData(self::KEY_IS_SMART_CROP);
    }

    public function setIsSmartCrop(
        bool $smartCrop,
    ): AssetInterface {
        return $this->setData(self::KEY_IS_SMART_CROP, $smartCrop);
    }

    public function getBrightness(): ?int
    {
        return $this->getData(self::KEY_BRIGHTNESS);
    }

    public function setBrightness(
        int $brightness,
    ): AssetInterface {
        if ($brightness < -100 || $brightness > 100) {
            throw new ValidatorException(__('Brightness value must be between -100 and 100.'));
        }

        return $this->setData(self::KEY_BRIGHTNESS, $brightness);
    }

    public function getBlur(): ?int
    {
        return $this->getData(self::KEY_BLUR);
    }

    public function setBlur(
        int $blur,
    ): AssetInterface {
        if ($blur < 0 || $blur > 150) {
            throw new ValidatorException(__('Blur value must be between 0 and 150.'));
        }

        return $this->setData(self::KEY_BLUR, $blur);
    }

    public function isGrayscale(): bool
    {
        return (bool)$this->getData(self::KEY_IS_GRAYSCALE);
    }

    public function setIsGrayscale(
        bool $grayscale,
    ): AssetInterface {
        return $this->setData(self::KEY_IS_GRAYSCALE, $grayscale);
    }

    public function getRotation(): ?int
    {
        return $this->getData(self::KEY_ROTATION);
    }

    public function setRotation(int $rotation,): AssetInterface
    {
        if (!in_array($rotation, [0, 90, 180, 270], true)) {
            throw new ValidatorException(__('Rotation must be one of the following values: 0, 90, 180, 270.'));
        }

        return $this->setData(self::KEY_ROTATION, $rotation);
    }

    public function getIsHorizontalFlip(): bool
    {
        return (bool)$this->getData(self::KEY_IS_HORIZONTAL_FLIP);
    }

    public function setIsHorizontalFlip(
        bool $isHorizontalFlip,
    ): AssetInterface {
        return $this->setData(self::KEY_IS_HORIZONTAL_FLIP, $isHorizontalFlip);
    }

    public function getIsVerticalFlip(): bool
    {
        return (bool)$this->getData(self::KEY_IS_VERTICAL_FLIP);
    }

    public function setIsVerticalFlip(bool $isVerticalFlip,): AssetInterface
    {
        return $this->setData(self::KEY_IS_VERTICAL_FLIP, $isVerticalFlip);
    }

    public function setRoundedCornersRadius(
        int $radius,
    ): AssetInterface {
        $this->validateDimensionAgainstWidthPercentage($radius, 0.5, 'Rounded corners radius');

        return $this->setData(self::KEY_ROUNDED_CORNERS_RADIUS, $radius);
    }

    public function setRoundedCornersEllipsis(
        int $ellipsis,
    ): AssetInterface {
        $this->validateDimensionAgainstWidthPercentage($ellipsis, 0.25, 'Rounded corners ellipsis');

        return $this->setData(self::KEY_ROUNDED_CORNERS_ELLIPSIS, $ellipsis);
    }

    public function setRoundedCornersBackgroundColor(
        string $background,
    ): AssetInterface {
        $background = $background === self::COLOR_TRANSPARENT? '0,0,0,1' : $this->parseColor($background, 'rgb') . ',0';

        return $this->setData(self::KEY_ROUNDED_CORNERS_BACKGROUND_COLOR, $background);
    }

    public function getRoundedCorners(): ?string
    {
        $radius = $this->getData(self::KEY_ROUNDED_CORNERS_RADIUS);

        if (!$radius) {
            return null;
        }

        $this->validateDimensionAgainstWidthPercentage($radius, 0.5, 'Rounded corners radius');

        $ellipsis = $this->getData(self::KEY_ROUNDED_CORNERS_ELLIPSIS);
        $backgroundColor = $this->getData(self::KEY_ROUNDED_CORNERS_BACKGROUND_COLOR) ?? '0,0,0,0';

        $returnValue = $radius;public function getRoundedCorners(): ?string
    {
        $radius = $this->getData(self::KEY_ROUNDED_CORNERS_RADIUS);

        if (!$radius) {
            return null;
        }

        $this->validateDimensionAgainstWidthPercentage($radius, 0.5, 'Rounded corners radius');

        $ellipsis = $this->getData(self::KEY_ROUNDED_CORNERS_ELLIPSIS);
        $backgroundColor = $this->getData(self::KEY_ROUNDED_CORNERS_BACKGROUND_COLOR) ?? '0,0,0,0';

        $returnValue = $radius;

        if ($ellipsis) {
            $this->validateDimensionAgainstWidthPercentage($ellipsis, 0.25, 'Rounded corners ellipsis');

            $returnValue .= '|' . $ellipsis;
        }

        $returnValue .= $backgroundColor;

        return $returnValue;
    }

        if ($ellipsis) {
            $this->validateDimensionAgainstWidthPercentage($ellipsis, 0.25, 'Rounded corners ellipsis');

            $returnValue .= '|' . $ellipsis;
        }

        $returnValue .= $backgroundColor;

        return $returnValue;
    }

    public function getUrl(): ?string
    {
        if ($cropping = $this->getManualCrop()) {
            $modifier = $cropping;
        } else {
            $width = $this->getWidth() ?? 0;
            $height = $this->getHeight() ?? 0;

            if ($this->getIsHorizontalFlip()) {
                $width = '-' . $width;
            }

            if ($this->getIsVerticalFlip()) {
                $height = '-' . $height;
            }

            $modifier = $width . 'x' . $height;
        }

        $filters = [];

        foreach (self::URL_FILTERS as $filter => $getter) {
            if ($value = $this->$getter()) {
                $filters[] = $filter . '(' . $value . ')';
            }
        }

        // Special Filter Handling
        if ($this->isGrayscale()) {
            $filters[] = 'graysscale()';
        }

        $url = $this->getFilename() . '/m/' . $modifier;

        if ($filters) {
            $url .= '/filters:' . implode(':', $filters);
        }

        return $url;
    }

    /**
     * Extracts image dimensions from a Storyblok filename.
     *
     * Assumes the filename format:
     * https://a.storyblok.com/f/{folder_id}/{width}x{height}/{hash}/{filename}.{extension}
     *
     * @param string|null $filename
     *
     * @return array|null
     */
    private function extractImageDimensionsFromFilename(
        ?string $filename
    ): array {
        $filename = $filename ?? $this->getFilename();
        $urlParts = parse_url($filename);

        if ($urlParts && isset($urlParts['path'])) {
            $pathParts = explode('/', trim($urlParts['path'], '/'));

            if (count($pathParts) >= 3) {
                // The dimensions should be in the third segment
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

    /**
     * Validates a dimension against a percentage of the image width.
     *
     * @param int   $value      The value to validate (radius or ellipsis).
     * @param float $percentage The percentage of the width (e.g., 0.5 for 50%, 0.25 for 25%).
     * @param string $valueName  A descriptive name for the value being validated (e.g., "radius", "ellipsis").
     * @return void
     * @throws ValidatorException
     */
    private function validateDimensionAgainstWidthPercentage(
        int $value,
        float $percentage,
        string $valueName
    ): void {
        if ($value < 0) {
            throw new ValidatorException(__("%1 must be 0 or greater.", $valueName));
        }

        $width = $this->getCroppedDimensions()['width']
            ?? $this->getWidth()
            ?? $this->extractImageDimensionsFromFilename($this->getFilename())['width']
            ?? null;

        if ($width === null) {
            throw new ValidatorException(__("%1 validation: Asset width is not set.", $valueName));
        }

        if ($value > $width * $percentage) {
            throw new ValidatorException(
                __("%1 cannot be greater than %2% of the image width.", $valueName, $percentage * 100)
            );
        }
    }

    /**
     * Parses and validates a color string, converting it to the desired type.
     *
     * @param string $color       The color string (hex or RGB).
     * @param string $desiredType The desired output type ('hex' or 'rgb').
     * @return string The color string in the desired format (e.g., "FF0000" or "255,0,0").
     * @throws ValidatorException If the color format is invalid or RGB values are out of range,
     * or if the desired type is not supported.
     */
    private function parseColor(
        string $color,
        string $desiredType = 'rgb'
    ): string {
        if (!in_array($desiredType, ['rgb', 'hex'])) {
            throw new ValidatorException(__('Unhandled Desired Color Type "%1".', $desiredType));
        }

        $currentType = match(true) {
            str_starts_with($color, '#') || strlen($color) === 6 || strlen($color) === 3 => 'hex',
            preg_match('/^\d{1,3},\d{1,3},\d{1,3}$/', $color) => 'rgb',
            default => null,
        };

        if (!$currentType) {
            throw new ValidatorException(__('Invalid color format: %s.', $color));
        }

        $convertedValue =  match(true) {
            $currentType === $desiredType => $color,
            $currentType === 'rgb' && $desiredType === 'hex' => \Mixable\Color\Convert::rgb2hex(explode(',', $color)),
            $currentType === 'hex' && $desiredType === 'rgb' => implode(',', \Mixable\Color\Convert::hex2rgb($color) ?? []),
            default => null,
        }

        if (!$convertedValue) {
            throw new ValidatorException(__('Unabled to convert: %1 to %2', $color, $desiredType));
        }

        return $convertedValue;
    }

    /**
     * Calculates and returns the dimensions of the image after cropping.
     *
     * @return array An array containing 'width' and 'height' keys, or an empty array if no crop is applied
     * or if crop coordinates are invalid.
     */
    private function getCroppedDimensions(): array
    {
        $dimensions = [];
        $manualCrop = $this->getManualCrop();

        if ($manualCrop) {
            $cropCoordinates = explode(':', $manualCrop);

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

    public function reset(): AssetInterface
    {
        foreach ($this->getData() as $key => $value) {
            if (str_starts_with($key, 'modifiable_')) {
                $this->unsetData($key);
            }
        }

        return $this;
    }
}
