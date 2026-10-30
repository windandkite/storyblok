<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Tracks which stories are being rendered through "reference" bloks during this request,
 * so a story can't include itself (A -> A, or A -> B -> A) and nesting stays bounded.
 */
class ReferenceStack implements ArgumentInterface
{
    private const MAX_DEPTH = 5;

    /**
     * @var string[]
     */
    private array $stack = [];

    /**
     * The story the first reference was rendered in, so a reference back to it is stopped too.
     */
    private ?string $root = null;

    /**
     * @param string $uuid Story being referenced.
     * @param string|null $parentUuid Story that contains the reference blok (the page on first entry).
     *
     * @return bool False when rendering would recurse or nest too deep.
     */
    public function enter(string $uuid, ?string $parentUuid = null): bool
    {
        if ($this->stack === []) {
            $this->root = $parentUuid;
        }

        if ($uuid === $parentUuid
            || $uuid === $this->root
            || in_array($uuid, $this->stack, true)
            || count($this->stack) >= self::MAX_DEPTH
        ) {
            return false;
        }

        $this->stack[] = $uuid;

        return true;
    }

    /**
     * @param string $uuid
     *
     * @return void
     */
    public function leave(string $uuid): void
    {
        $index = array_search($uuid, $this->stack, true);

        if ($index !== false) {
            array_splice($this->stack, $index, 1);
        }

        if ($this->stack === []) {
            $this->root = null;
        }
    }
}
