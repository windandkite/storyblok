<?php

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\DataObject;
use WindAndKite\Storyblok\Api\Data\BlockInterface;

class Block extends DataObject implements BlockInterface
{
    /**
     * Get component name.
     *
     * @return string|null
     */
    public function getComponent(): ?string
    {
        return $this->getData(self::FIELD_COMPONENT);
    }

    /**
     * Get UID.
     *
     * @return string|null
     */
    public function getUid(): ?string
    {
        return $this->getData(self::FIELD_UID);
    }

    /**
     * Get the names of the data fields (excluding internal Storyblok fields).
     *
     * @return array
     */
    public function getFieldNames(): array
    {
        $data = $this->getData();

        return array_diff(array_keys($data), [
            self::FIELD_COMPONENT,
            self::FIELD_UID,
            self::FIELD_EDITABLE,
        ]);
    }
}
