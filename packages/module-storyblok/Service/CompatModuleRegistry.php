<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

class CompatModuleRegistry
{
    /**
     * @param string[] $compatModules
     */
    public function __construct(
        private readonly array $compatModules = []
    ) {
    }

    /**
     * Get all registered compatibility modules.
     *
     * @return string[]
     */
    public function getCompatModules(): array
    {
        return $this->compatModules;
    }
}
