<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\ViewModel;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Config\Model\Config\Structure\Reader as StructureReader;
use Magento\Framework\App\Area;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Variable\Model\Config\Structure\AvailableVariables;
use Magento\Variable\Model\VariableFactory;

/**
 * Lists the Magento CMS variables Storyblok editors can use in rich text,
 * using the same sources as the admin "Insert Variable" dialog.
 */
class Variables implements ArgumentInterface
{
    /**
     * @param AvailableVariables $availableVariables
     * @param StructureReader $structureReader
     * @param VariableFactory $variableFactory
     * @param FilterProvider $filterProvider
     */
    public function __construct(
        private readonly AvailableVariables $availableVariables,
        private readonly StructureReader $structureReader,
        private readonly VariableFactory $variableFactory,
        private readonly FilterProvider $filterProvider,
    ) {}

    /**
     * Allowed config variables grouped by section, e.g. [['label' => 'General / Store Information', 'value' => [...]]].
     *
     * @return array<int, array{label: string, value: array<int, array{label: string, value: string}>}>
     */
    public function getConfigVariables(): array
    {
        // Labels live in adminhtml system.xml, which the frontend config scope doesn't load.
        // ponytail: uncached read (~0.5s), fine for an unpublished editor reference story only
        $sections = $this->structureReader->read(Area::AREA_ADMINHTML)['config']['system']['sections'] ?? [];
        $groups = [];
        /** @var array<string, array<string, string>> $configPaths Magento's own @return is wrong */
        $configPaths = $this->availableVariables->getConfigPaths();

        foreach ($configPaths as $groupPath => $paths) {
            $groupParts = explode('/', $groupPath);
            $groupLabels = [];

            foreach (array_keys($groupParts) as $depth) {
                $groupLabels[] = $this->getLabel($sections, implode('/', array_slice($groupParts, 0, $depth + 1)));
            }

            $variables = [];

            foreach (array_keys($paths) as $path) {
                $variables[] = [
                    'label' => $this->getLabel($sections, $path),
                    'value' => '{{config path="' . $path . '"}}',
                ];
            }

            $groups[] = ['label' => implode(' / ', $groupLabels), 'value' => $variables];
        }

        return $groups;
    }

    /**
     * Custom variables defined in the admin (Content > Custom Variables).
     *
     * @return array<int, array{label: mixed, value: string}>
     */
    public function getCustomVariables(): array
    {
        return $this->variableFactory->create()->getVariablesOptionArray();
    }

    /**
     * Run a directive through the CMS page filter, as rich text does.
     *
     * @param string $directive
     *
     * @return string
     */
    public function resolve(string $directive): string
    {
        return $this->filterProvider->getPageFilter()->filter($directive);
    }

    /**
     * Admin label for a config path, falling back to the path itself.
     *
     * @param array $sections
     * @param string $path
     *
     * @return string
     */
    private function getLabel(
        array $sections,
        string $path,
    ): string {
        $node = ['children' => $sections];

        foreach (explode('/', $path) as $id) {
            $node = $node['children'][$id] ?? null;

            if ($node === null) {
                return $path;
            }
        }

        return (string)__($node['label'] ?? $path);
    }
}
