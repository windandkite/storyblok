<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

interface BlockHydratorInterface
{
    /**
     * Enrich or modify the block instance data/dependencies before rendering.
     *
     * @param \WindAndKite\Storyblok\Block\Block $blockInstance
     *
     * @return \WindAndKite\Storyblok\Block\Block
     */
    public function populate(
        \WindAndKite\Storyblok\Block\Block $blockInstance
    ): \WindAndKite\Storyblok\Block\Block;
}
