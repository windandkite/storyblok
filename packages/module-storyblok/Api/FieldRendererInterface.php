<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Api;

use WindAndKite\Storyblok\Api\Data\StoryInterface;

/**
 * Interface StoryblokFieldRendererInterface
 *
 * This interface defines the contract for rendering Storyblok fields.
 * It focuses primarily on handling complex field types like blocks and rich text,
 * leaving the rendering of simple field types to the template developers.
 */
interface FieldRendererInterface
{
    public const FIELD_TYPE_BLOCK = 'block';
    public const FIELD_TYPE_RICH_TEXT = 'doc';

    /**
     * Renders a Storyblok field based on its type.
     *
     * @param mixed $fieldValue The value of the Storyblok field. Can be a single value or an array.
     * @return string The rendered output.
     */
    public function renderField(
        mixed $fieldValue,
        ?StoryInterface $story = null,
    ): string;

    /**
     * Creates and hydrates the block instance for a single blok without rendering it.
     *
     * @param array $fieldValue A single blok (must have _uid and component).
     * @param StoryInterface|null $story
     *
     * @return \WindAndKite\Storyblok\Block\Block
     */
    public function createBlockInstance(
        array $fieldValue,
        ?StoryInterface $story = null,
    ): \WindAndKite\Storyblok\Block\Block;
}
