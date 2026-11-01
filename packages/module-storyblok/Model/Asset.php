<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\DataObject;
use WindAndKite\Storyblok\Api\Data\AssetInterface;

class Asset extends DataObject implements AssetInterface
{

    public function getFilename(): ?string
    {
        return $this->getData(self::KEY_FILENAME);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::KEY_CREATED_AT);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::KEY_UPDATED_AT);
    }

    public function getExpireAt(): ?string
    {
        return $this->getData(self::KEY_EXPIRE_AT);
    }

    public function getContentLength(): ?int
    {
        return $this->getData(self::KEY_CONTENT_LENGTH);
    }

    public function getSignedUrl(): ?string
    {
        return $this->getData(self::KEY_SIGNED_URL);
    }

    public function getContentType(): ?string
    {
        return $this->getData(self::KEY_CONTENT_TYPE);
    }
}
