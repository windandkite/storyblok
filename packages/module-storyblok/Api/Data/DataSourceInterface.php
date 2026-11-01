<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api\Data;

interface DataSourceInterface
{
    public const KEY_ID = 'id';
    public const KEY_NAME = 'name';
    public const KEY_SLUG = 'slug';
    public const KEY_DIMENSIONS = 'dimensions';

    public function getId(): ?int;

    public function getName(): ?string;

    public function getSlug(): ?string;

    public function getDimensions(): ?array;
}
