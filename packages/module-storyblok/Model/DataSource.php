<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model;

use Magento\Framework\DataObject;
use WindAndKite\Storyblok\Api\Data\DataSourceInterface;

class DataSource extends DataObject implements DataSourceInterface
{
    public function getId(): ?int
    {
        return $this->getData(self::KEY_ID);
    }

    public function getName(): ?string
    {
        return $this->getData(self::KEY_NAME);
    }

    public function getSlug(): ?string
    {
        return $this->getData(self::KEY_SLUG);
    }

    public function getDimensions(): ?array
    {
        return $this->getData(self::KEY_DIMENSIONS);
    }
}
