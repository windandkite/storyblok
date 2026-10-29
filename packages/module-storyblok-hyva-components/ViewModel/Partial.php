<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokHyvaComponents\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;

/**
 * Renders shared partial templates (picture, Alpine component scripts) from blok templates.
 */
class Partial implements ArgumentInterface
{
    private const MODULE = 'WindAndKite_StoryblokHyvaComponents::';

    /**
     * @var array<string, true>
     */
    private array $rendered = [];

    /**
     * @param LayoutInterface $layout
     */
    public function __construct(
        private readonly LayoutInterface $layout,
    ) {}

    /**
     * Render partials/{name}.phtml with the given data.
     *
     * @param string $name
     * @param array $data
     *
     * @return string
     */
    public function render(string $name, array $data = []): string
    {
        return $this->renderTemplate('partials/' . $name . '.phtml', $data);
    }

    /**
     * Render js/{name}.phtml only the first time it is requested in this request, so N instances
     * of a blok print its Alpine component definition once. Storyblok bloks are not block-cached
     * individually (FPC caches the whole page), so per-request tracking is safe.
     *
     * @param string $name
     *
     * @return string
     */
    public function renderScriptOnce(string $name): string
    {
        if (isset($this->rendered[$name])) {
            return '';
        }

        $this->rendered[$name] = true;

        return $this->renderTemplate('js/' . $name . '.phtml');
    }

    /**
     * @param string $template
     * @param array $data
     *
     * @return string
     */
    private function renderTemplate(string $template, array $data = []): string
    {
        return $this->layout->createBlock(Template::class, '', ['data' => $data])
            ->setTemplate(self::MODULE . $template)
            ->toHtml();
    }
}
