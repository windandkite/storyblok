<?php

namespace WindAndKite\Storyblok\Api\Data;

/**
 * Interface AssetInterface
 *
 * @api
 */
interface AssetInterface
{
    public const KEY_FILENAME = 'filename';
    public const KEY_CREATED_AT = 'created_at';
    public const KEY_UPDATED_AT = 'updated_at';
    public const KEY_EXPIRE_AT = 'expire_at';
    public const KEY_CONTENT_LENGTH = 'content_length';
    public const KEY_SIGNED_URL = 'signed_url';
    public const KEY_CONTENT_TYPE = 'content_type';

    public function getFilename(): ?string;

    public function getCreatedAt(): ?string;

    public function getUpdatedAt(): ?string;

    public function getExpireAt(): ?string;

    public function getContentLength(): ?int;

    public function getSignedUrl(): ?string;

    public function getContentType(): ?string;
}
