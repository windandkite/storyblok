<?php

declare(strict_types=1);

namespace WindAndKite\StoryblokSchema\Service;

use Magento\Framework\Filesystem\Driver\File;

/**
 * Counts how much stored content each schema issue affects, from the story files written by
 * `storyblok stories pull` (full story JSON, drafts included).
 */
class ComponentUsageCounter
{
    /**
     * @param File $file
     */
    public function __construct(
        private readonly File $file,
    ) {}

    /**
     * Adds "usage" => {stories, bloks} to each issue that has a field. Returns null (usage unknown) unless
     * the folder holds at least one story and every file in it is valid JSON: a zero count must mean zero.
     *
     * @param string $storiesDirectory
     * @param array $issues
     *
     * @return array|null
     */
    public function annotate(string $storiesDirectory, array $issues): ?array
    {
        if (!$this->file->isDirectory($storiesDirectory)) {
            return null;
        }

        $bloks = [];
        $storyCount = 0;

        foreach ($this->file->readDirectory($storiesDirectory) as $path) {
            if (!str_ends_with($path, '.json')) {
                continue;
            }

            $story = json_decode($this->file->fileGetContents($path), true);

            if (!is_array($story)) {
                return null;
            }

            // Folders and other non-story files have no content.
            if (is_array($story['content'] ?? null)) {
                $this->collect($story['content'], (string)($story['uuid'] ?? $path), $bloks);
                $storyCount++;
            }
        }

        if (!$storyCount) {
            return null;
        }

        foreach ($issues as &$issue) {
            if ($issue['field'] === null) {
                continue;
            }

            $stories = [];
            $count = 0;

            foreach ($bloks[$issue['component']] ?? [] as [$storyId, $blok]) {
                foreach ($this->getFieldValues($blok, (string)$issue['field']) as $value) {
                    if ($this->isEmpty($value)) {
                        continue;
                    }

                    // Option values / bloks: only count content that actually uses the affected values, or for a new
                    // whitelist, bloks outside it. Settings issues list setting names, so any content counts.
                    if ($issue['values']
                        && $issue['kind'] !== ComponentSchemaComparer::KIND_FIELD_SETTINGS
                        && !$this->usesValues($value, $issue['values'], $issue['kind'] === ComponentSchemaComparer::KIND_WHITELIST_UNRESTRICTED)
                    ) {
                        continue;
                    }

                    $count++;
                    $stories[$storyId] = true;
                    break;
                }
            }

            $issue['usage'] = ['stories' => count($stories), 'bloks' => $count];
        }

        unset($issue);

        return $issues;
    }

    /**
     * @param array $node
     * @param string $storyId
     * @param array $bloks
     *
     * @return void
     */
    private function collect(array $node, string $storyId, array &$bloks): void
    {
        if (isset($node['component'], $node['_uid']) && is_string($node['component'])) {
            $bloks[$node['component']][] = [$storyId, $node];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collect($value, $storyId, $bloks);
            }
        }
    }

    /**
     * The field's value and its translations, stored as e.g. "title__i18n__de".
     *
     * @param array $blok
     * @param string $field
     *
     * @return array
     */
    private function getFieldValues(array $blok, string $field): array
    {
        $values = [$blok[$field] ?? null];

        foreach ($blok as $key => $value) {
            if (str_starts_with((string)$key, $field . '__i18n__')) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param mixed $value
     * @param string[] $values
     * @param bool $outside Look for bloks not in $values instead.
     *
     * @return bool
     */
    private function usesValues(mixed $value, array $values, bool $outside = false): bool
    {
        if (is_scalar($value)) {
            return in_array((string)$value, $values, true);
        }

        foreach ((array)$value as $child) {
            // Multi-option selections are lists of values; bloks are lists of arrays.
            if (is_scalar($child) ? in_array((string)$child, $values, true) : is_array($child) && in_array($child['component'] ?? null, $values, true) !== $outside) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function isEmpty(mixed $value): bool
    {
        // A stored false is content: when a boolean defaults to true, an explicit false is a choice. Missing
        // fields are null.
        return $value === null
            || $value === ''
            || $value === []
            || (is_array($value) && ($value['type'] ?? null) === 'doc' && empty($value['content']))
            || (is_array($value) && ($value['fieldtype'] ?? null) === 'asset' && empty($value['filename']))
            || (is_array($value) && ($value['fieldtype'] ?? null) === 'multilink' && empty($value['url']) && empty($value['cached_url']) && empty($value['email']));
    }
}
