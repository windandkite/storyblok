<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Plugin;

use InvalidArgumentException;
use Magento\Framework\Module\Dir as ModuleDir;
use Magento\Framework\View\Design\Fallback\Rule\ModularSwitch;
use WindAndKite\Storyblok\Service\CompatModuleRegistry;

class ViewFileOverride
{
    private const TARGET_MODULE = 'WindAndKite_Storyblok';

    /**
     * @param ModuleDir $moduleDir
     * @param CompatModuleRegistry $compatModuleRegistry
     */
    public function __construct(
        private readonly ModuleDir $moduleDir,
        private readonly CompatModuleRegistry $compatModuleRegistry
    ) {}

    /**
     * Intercept template path determination to inject compatibility module paths
     *
     * @param ModularSwitch $subject
     * @param array $fallbackDirsResult
     * @param array $params
     *
     * @return array
     */
    public function afterGetPatternDirs(
        ModularSwitch $subject,
        array $fallbackDirsResult,
        array $params,
    ): array {
        if (isset($params['module_name']) && $params['module_name'] === self::TARGET_MODULE) {
            $compatModules = $this->compatModuleRegistry->getCompatModules();

            if (!empty($compatModules)) {
                return $this->injectCompatModulesDirs($fallbackDirsResult);
            }
        }

        return $fallbackDirsResult;
    }

    /**
     * Inject compatibility module paths into the active fallback list
     *
     * @param string[] $fallbackDirsResult
     *
     * @return string[]
     */
    private function injectCompatModulesDirs(
        array $fallbackDirsResult,
    ): array {
        $origModuleDir = $this->moduleDir->getDir(self::TARGET_MODULE);
        $compatModuleDirs = [];

        foreach ($this->compatModuleRegistry->getCompatModules() as $compatModule) {
            try {
                $compatModuleDirs[] = $this->moduleDir->getDir($compatModule);
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        $finalDirs = [];
        foreach ($fallbackDirsResult as $origFallbackDir) {
            if (str_starts_with($origFallbackDir, $origModuleDir)) {
                $pathInModule = substr($origFallbackDir, strlen($origModuleDir));

                foreach ($compatModuleDirs as $compatModuleDir) {
                    $finalDirs[] = $compatModuleDir . $pathInModule;
                }
            }

            $finalDirs[] = $origFallbackDir;
        }

        return $finalDirs;
    }
}
