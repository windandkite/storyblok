<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokHyvaComponents\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Adds this module to app/etc/hyva-themes.json so Tailwind scans its templates.
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    /**
     * @param ComponentRegistrar $componentRegistrar
     */
    public function __construct(
        private readonly ComponentRegistrar $componentRegistrar,
    ) {}

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $config = $observer->getData('config');
        $extensions = $config->hasData('extensions') ? $config->getData('extensions') : [];
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'WindAndKite_StoryblokHyvaComponents');

        $extensions[] = ['src' => substr($path, strlen(BP) + 1)];

        $config->setData('extensions', $extensions);
    }
}
