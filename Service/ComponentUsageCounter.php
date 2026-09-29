<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

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
     * Adds "usage" => {stories, bloks} to each issue that has a field. Returns null when there are no
     * story files to count from.
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

        foreach ($this->file->readDirectory($storiesDirectory) as $path) {
            if (!str_ends_with($path, '.json')) {
                continue;
            }

            $story = json_decode($this->file->fileGetContents($path), true);

            if (is_array($story) && is_array($story['content'] ?? null)) {
                $this->collect($story['content'], (string)($story['uuid'] ?? $path), $bloks);
            }
        }

        foreach ($issues as &$issue) {
            if ($issue['field'] === null) {
                continue;
            }

            $stories = [];
            $count = 0;

            foreach ($bloks[$issue['component']] ?? [] as [$storyId, $blok]) {
                $value = $blok[$issue['field']] ?? null;

                if ($this->isEmpty($value)) {
                    continue;
                }

                // Option values / bloks: only count content that actually uses the affected values.
                if ($issue['values'] && !$this->usesValues($value, $issue['values'])) {
                    continue;
                }

                $count++;
                $stories[$storyId] = true;
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
     * @param mixed $value
     * @param string[] $values
     *
     * @return bool
     */
    private function usesValues(mixed $value, array $values): bool
    {
        if (is_scalar($value)) {
            return in_array((string)$value, $values, true);
        }

        foreach ((array)$value as $child) {
            if (is_array($child) && in_array($child['component'] ?? null, $values, true)) {
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
        // ponytail: an unticked checkbox (false) counts as "not set"; compare against the field default if
        // a space ever defaults a boolean to true and false becomes meaningful.
        return $value === null
            || $value === false
            || $value === ''
            || $value === []
            || (is_array($value) && ($value['type'] ?? null) === 'doc' && empty($value['content']))
            || (is_array($value) && ($value['fieldtype'] ?? null) === 'asset' && empty($value['filename']))
            || (is_array($value) && ($value['fieldtype'] ?? null) === 'multilink' && empty($value['url']) && empty($value['cached_url']) && empty($value['email']));
    }
}
