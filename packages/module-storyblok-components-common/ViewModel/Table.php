<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokComponentsCommon\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Normalises the Storyblok "table" field into plain header / row arrays.
 */
class Table implements ArgumentInterface
{
    /**
     * @param array|null $table
     *
     * @return array{head: string[], rows: string[][]}
     */
    public function normalize(?array $table): array
    {
        $head = array_map(static fn ($cell) => (string)($cell['value'] ?? ''), $table['thead'] ?? []);
        $rows = array_map(
            static fn ($row) => array_map(static fn ($cell) => (string)($cell['value'] ?? ''), $row['body'] ?? []),
            $table['tbody'] ?? []
        );

        return ['head' => array_filter($head, 'strlen') ? $head : [], 'rows' => $rows];
    }
}
