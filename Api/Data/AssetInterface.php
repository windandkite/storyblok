<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api\Data;

/**
 * Interface AssetInterface
 *
 * Represents a Storyblok asset.
 */
interface AssetInterface
{
    // Asset Data
    public const KEY_ID = 'id';
    public const KEY_ALT = 'alt';
    public const KEY_NAME = 'name';
    public const KEY_FOCUS = 'focus';
    public const KEY_TITLE = 'title';
    public const KEY_SOURCE = 'source';
    public const KEY_FILENAME = 'filename';
    public const KEY_COPYRIGHT = 'copyright';
    public const KEY_FIELD_TYPE = 'fieldtype';
    public const KEY_META_DATA = 'meta_data';
    public const KEY_IS_EXTERNAL_URL = 'is_external_url';

    // Modifiable Data

    public const KEY_HEIGHT = 'modifiable_height';
    public const KEY_WIDTH = 'modifiable_width';
    public const KEY_FORMAT = 'modifiable_format';
    public const KEY_FILL = 'modifiable_fill';
    public const KEY_FOCAL_POINT = 'modifiable_focal_point';
    public const KEY_MANUAL_CROP = 'modifiable_manual_crop';
    public const KEY_IS_SMART_CROP = 'modifiable_is_smart_crop';
    public const KEY_BRIGHTNESS = 'modifiable_brightness';
    public const KEY_BLUR = 'modifiable_blur';
    public const KEY_IS_GRAYSCALE = 'modifiable_is_grayscale';
    public const KEY_ROTATION = 'modifiable_rotation';
    public const KEY_IS_HORIZONTAL_FLIP = 'modifiable_is_horizontal_flip';
    public const KEY_IS_VERTICAL_FLIP = 'modifiable_is_vertical_flip';
    public const KEY_ROUNDED_CORNERS_RADIUS = 'modifiable_rounded_corners_radius';
    public const KEY_ROUNDED_CORNERS_ELLIPSIS = 'modifiable_rounded_corners_ellipsis';
    public const KEY_ROUNDED_CORNERS_BACKGROUND_COLOR = 'modifiable_rounded_corners_background_color';

    public const ALLOWED_FORMATS = [
        'webp',
        'jpeg',
        'png',
        'avif',
    ];

    public function getId(): ?int;

    public function getAlt(): ?string;

    public function getName(): ?string;

    public function getFocus(): ?string;

    public function getTitle(): ?string;

    public function getSource(): ?string;

    public function getFilename(): ?string;

    public function getCopyright(): ?string;

    public function getMetaData(): ?array;

    public function getIsExternalUrl(): bool;

    // Modifiable Data

    public function getHeight(): ?int;

    public function setHeight(
        int $height,
    ): self;

    public function getWidth(): ?int;

    public function setWidth(
        int $width,
    ): self;

    public function getFormat(): ?string;

    public function setFormat(
        string $format,
    ): self;

    public function getFill(): ?string;

    public function setFill(
        string $colour
    ): self;

    public function setFocalPoint(
        string $focalPoint,
    ): self;

    public function getManualCrop(): ?string;

    public function setManualCrop(
        string $manualCrop,
    ): self;

    public function getIsSmartCrop(): ?bool;

    public function setIsSmartCrop(
        bool $smartCrop,
    ): self;

    public function getBrightness(): ?int;

    public function setBrightness(
        int $brightness,
    ): self;

    public function getBlur(): ?int;

    public function setBlur(
        int $blur,
    ): self;

    public function isGrayscale(): bool;

    public function setIsGrayscale(
        bool $grayscale,
    ): self;

    public function getRotation(): ?int;

    public function setRotation(
        int $rotation,
    ): self;

    public function getIsHorizontalFlip(): bool;

    public function setIsHorizontalFlip(
        bool $isHorizontalFlip,
    ): self;

    public function getIsVerticalFlip(): bool;

    public function setIsVerticalFlip(
        bool $isVerticalFlip,
    ): self;

    public function setRoundedCornersRadius(
        int $radius,
    ): self;

    public function setRoundedCornersEllipsis(
        int $ellipsis,
    ): self;

    public function setRoundedCornersBackgroundColor(
        string $background,
    ): self;

    // Computed Data

    public function getRoundedCorners(): ?string;

    public function getUrl(): ?string;
}
