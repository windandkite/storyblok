<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Model\Hydrator;

use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Block\Block;
use WindAndKite\Storyblok\ViewModel\Variables;

/**
 * Gives the magento_variables reference blok its view model, so any theme's template can use it.
 */
class MagentoVariables implements BlockHydratorInterface
{
    /**
     * @param Variables $variables
     */
    public function __construct(
        private readonly Variables $variables,
    ) {}

    /**
     * Add the variables view model to the block.
     *
     * @param Block $blockInstance
     *
     * @return Block
     */
    public function populate(Block $blockInstance): Block
    {
        return $blockInstance->setData('variables_view_model', $this->variables);
    }
}
