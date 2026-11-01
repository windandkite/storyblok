<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

/**
 * Turns a custom field plugin's stored value into what blok templates get, e.g. a product picker's IDs into
 * products. Registered by plugin name in the "hydrators" argument of
 * \WindAndKite\Storyblok\Model\Hydrator\PluginValues.
 *
 * @api
 */
interface PluginValueHydratorInterface
{
    /**
     * @param array $value The stored value. Its "plugin" key holds the plugin's name.
     * @param \WindAndKite\Storyblok\Block\Block $blockInstance The blok being rendered.
     *
     * @return mixed The value templates get from the field's getter.
     */
    public function hydrate(
        array $value,
        \WindAndKite\Storyblok\Block\Block $blockInstance
    ): mixed;
}
