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
     * Hydrators registered under "*" run for every component, before the component's own.
     *
     * @param string $componentType
     *
     * @return BlockHydratorInterface[]
     */
    public function getForComponent(
        string $componentType,
    ): array {
        $matchedHydrators = [];

        foreach (['*', $componentType] as $key) {
            if (!isset($this->hydrators[$key]) || !is_array($this->hydrators[$key])) {
                continue;
            }

            foreach ($this->hydrators[$key] as $hydrator) {
                if ($hydrator instanceof BlockHydratorInterface) {
                    $matchedHydrators[] = $hydrator;
                }
            }
        }

        return $matchedHydrators;
    }
}
