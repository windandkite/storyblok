<?php

namespace WindAndKite\Storyblok\Api\Data;

/**
 * Interface BlockInterface
 *
 * @api
 */
interface BlockInterface
{
    public const FIELD_COMPONENT = 'component';
    public const FIELD_UID = '_uid';
    public const FIELD_EDITABLE = '_editable';

    public const UNRENDERABLE_FIELDS = [
        self::FIELD_COMPONENT,
        self::FIELD_UID,
        self::FIELD_EDITABLE,
    ];

    /**
     * Get component name.
     *
     * @return string|null
     */
    public function getComponent(): ?string;

    /**
     * Get UID.
     *
     * @return string|null
     */
    public function getUid(): ?string;

    /**
     * Get the names of the data fields (excluding internal Storyblok fields).
     *
     * @return array
     */
    public function getFieldNames(): array;
}
