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

        $extensions[] = ['src' => self::relativePath(BP, $path)];

        $config->setData('extensions', $extensions);
    }

    /**
     * The path from the Magento root, with ".." when the module is outside it (a Composer path repository's
     * symlinked checkout resolves to its real location).
     *
     * @param string $root
     * @param string $path
     *
     * @return string
     */
    public static function relativePath(string $root, string $path): string
    {
        $from = array_values(array_filter(explode('/', (realpath($root) ?: $root)), 'strlen'));
        $to = array_values(array_filter(explode('/', (realpath($path) ?: $path)), 'strlen'));

        while ($from && $to && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return implode('/', [...array_fill(0, count($from), '..'), ...$to]);
    }
}
