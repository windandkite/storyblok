<?php

namespace WindAndKite\Storyblok\Api;

interface MediaServiceInterface
{
    /**
     * Get details about a Storyblok asset by its filename.
     *
     * @param string $filename
     *
     * @return \Storyblok\Api\Response\AssetResponse
     */
    public function getAssetByFilename(
        string $filename
    ): \Storyblok\Api\Response\AssetResponse;
}
