<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

interface AssetRepositoryInterface
{
    public function getByFilename(
        string $filename,
    ): \WindAndKite\Storyblok\Api\Data\AssetInterface;
}
