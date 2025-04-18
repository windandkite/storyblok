<?php

namespace WindAndKite\Storyblok\Service;

use Exception;
use Storyblok\Api\Response\AssetResponse;
use WindAndKite\Storyblok\Api\MediaServiceInterface;

class MediaService extends AbstractService implements MediaServiceInterface
{
    /**
     * @inheritDoc
     * @throws Exception
     */
    public
    function getAssetByFilename(
        string $filename,
    ): AssetResponse {
        return $this->storyblokClientWrapper->getAssetByFilename($filename);
    }
}
