<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model\Hydrator;

use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Api\PluginValueHydratorInterface;
use WindAndKite\Storyblok\Block\Block;

/**
 * Runs for every blok: replaces each custom field plugin value that has a registered hydrator with the hydrated
 * value. Storyblok stores the plugin's name with every value, so no schema is needed. The raw value stays
 * available through $block->getBlock()->getData('<field>').
 */
class PluginValues implements BlockHydratorInterface
{
    /**
     * @param array<string, PluginValueHydratorInterface> $hydrators Plugin name => hydrator.
     */
    public function __construct(
        private readonly array $hydrators = []
    ) {}

    /**
     * @param Block $blockInstance
     *
     * @return Block
     */
    public function populate(Block $blockInstance): Block
    {
        foreach ($blockInstance->getBlock()->getData() as $field => $value) {
            $plugin = is_array($value) ? ($value['plugin'] ?? null) : null;
            $hydrator = is_string($plugin) ? ($this->hydrators[$plugin] ?? null) : null;

            // A field named like data the block instance already holds ("block", "story", "template"...) keeps
            // its raw value: replacing that data would break rendering.
            if (!$hydrator instanceof PluginValueHydratorInterface || $blockInstance->hasData((string)$field)) {
                continue;
            }

            $blockInstance->setData((string)$field, $hydrator->hydrate($value, $blockInstance));
        }

        return $blockInstance;
    }
}
