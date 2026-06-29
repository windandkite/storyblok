<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use WindAndKite\Storyblok\Api\BlockHydratorInterface;

class BlockHydrationManager
{
    /**
     * @param array<string, array<BlockHydratorInterface>> $hydrators
     */
    public function __construct(
        private readonly array $hydrators = []
    ) {}

    /**
     * @param string $componentType
     *
     * @return BlockHydratorInterface[]
     */
    public function getForComponent(
        string $componentType,
    ): array {
        if (!isset($this->hydrators[$componentType]) || !is_array($this->hydrators[$componentType])) {
            return [];
        }

        $matchedHydrators = [];

        foreach ($this->hydrators[$componentType] as $hydrator) {
            if ($hydrator instanceof BlockHydratorInterface) {
                $matchedHydrators[] = $hydrator;
            }
        }

        return $matchedHydrators;
    }
}
