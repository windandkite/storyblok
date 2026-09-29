<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

/**
 * Compares the schema generated from a store's templates (ComponentSchemaGenerator) with the schema
 * pulled from a Storyblok space (`storyblok components pull`), and reports where they disagree.
 *
 * Errors are mismatches that break rendering or silently change output (a component with no template,
 * a field type the template doesn't expect, option values the template doesn't handle). Warnings are
 * drift that a push would resolve or that needs a decision (unpushed components, fields only on one side).
 *
 * Each issue has a "kind". isDestructive() tells which kinds would orphan content already stored in the
 * space if the template schema were pushed: Storyblok keeps that data (shown as "out of schema"), but the
 * editor and the templates stop using it.
 */
class ComponentSchemaComparer
{
    public const LEVEL_ERROR = 'error';
    public const LEVEL_WARNING = 'warning';

    public const KIND_MISSING_TEMPLATE = 'missing_template';
    public const KIND_UNTAGGED = 'untagged';
    public const KIND_COMPONENT_NOT_PUSHED = 'component_not_pushed';
    public const KIND_FIELD_REMOVED = 'field_removed';
    public const KIND_FIELD_RENAMED = 'field_renamed';
    public const KIND_FIELD_NOT_PUSHED = 'field_not_pushed';
    public const KIND_TYPE_CHANGED = 'type_changed';
    public const KIND_OPTION_SOURCE_CHANGED = 'option_source_changed';
    public const KIND_OPTION_VALUES_REMOVED = 'option_values_removed';
    public const KIND_OPTION_VALUES_NOT_PUSHED = 'option_values_not_pushed';
    public const KIND_WHITELIST_WITHOUT_TEMPLATE = 'whitelist_without_template';
    public const KIND_WHITELIST_REMOVED = 'whitelist_removed';
    public const KIND_WHITELIST_NOT_PUSHED = 'whitelist_not_pushed';
    public const KIND_WHITELIST_UNRESTRICTED = 'whitelist_unrestricted';
    public const KIND_LAYOUT = 'layout';
    public const KIND_FIELD_SETTINGS = 'field_settings';
    public const KIND_FIELD_ORDER = 'field_order';
    public const KIND_COMPONENT_SETTINGS = 'component_settings';

    // Field settings compared besides type, options and whitelists, with editor-facing names.
    private const FIELD_SETTINGS = [
        'display_name' => 'label',
        'description' => 'description',
        'default_value' => 'default',
        'required' => 'required',
        'filetypes' => 'file types',
        'folder_slug' => 'story folder',
    ];

    // Kinds that describe the space itself, not a difference the templates would push.
    private const NOT_A_CHANGE = [self::KIND_MISSING_TEMPLATE, self::KIND_UNTAGGED];

    private const DESTRUCTIVE_KINDS = [
        self::KIND_FIELD_REMOVED,
        self::KIND_TYPE_CHANGED,
        self::KIND_OPTION_SOURCE_CHANGED,
        self::KIND_OPTION_VALUES_REMOVED,
        self::KIND_WHITELIST_REMOVED,
    ];

    // Storyblok layout-only schema entries, not content fields.
    private const LAYOUT_TYPES = ['tab', 'section'];

    // Field types that render the same way as far as a template is concerned.
    private const COMPATIBLE_TYPES = [['text', 'textarea', 'markdown']];

    /**
     * @param array{components: array, renames?: array} $expected Generated from templates.
     * @param array $pulled Pulled from the space: a CLI v4 item list, or CLI v3 {"components": [...]}.
     * @param array<string, string> $untagged Component name => template path, for templates without a schema.
     *
     * @return array<int, array{level: string, kind: string, component: string, field: string|null, message: string, values: string[]}>
     */
    public function compare(array $expected, array $pulled, array $untagged = []): array
    {
        $templates = array_column($expected['components'] ?? [], null, 'name');
        $space = $this->spaceComponents($pulled);
        $folders = $this->folderNames($pulled);
        $renames = [];

        foreach ($expected['renames'] ?? [] as $rename) {
            $renames[$rename['component']][$rename['from']] = $rename['to'];
        }

        $issues = [];

        foreach ($space as $name => $component) {
            if (isset($templates[$name])) {
                continue;
            }

            $untaggedTemplate = $untagged[$name] ?? $untagged[ComponentSchemaGenerator::templateName($name)] ?? null;

            $issues[] = $untaggedTemplate !== null
                ? $this->issue(self::LEVEL_WARNING, self::KIND_UNTAGGED, $name, null, 'Template has no @storyblok tag, so its fields can\'t be checked: ' . $untaggedTemplate)
                : $this->issue(
                    self::LEVEL_ERROR,
                    self::KIND_MISSING_TEMPLATE,
                    $name,
                    null,
                    !empty($component['is_root'])
                        ? 'Content type in the space has no block template (fine if it is rendered by a story template).'
                        : 'Blok exists in the space but has no template: the storefront renders the "Missing Template" fallback.'
                );
        }

        foreach ($templates as $name => $component) {
            if (!isset($space[$name])) {
                $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_COMPONENT_NOT_PUSHED, $name, null, 'Defined by a template but not in the space yet: push to add it.');
                continue;
            }

            $issues = [
                ...$issues,
                ...$this->compareComponent($name, $component, $space[$name], $folders),
                ...$this->compareFields($name, $component['schema'] ?? [], $space[$name]['schema'] ?? [], $templates, $renames[$name] ?? []),
                ...$this->compareLayout($name, $component['schema'] ?? [], $space[$name]['schema'] ?? []),
            ];
        }

        return $issues;
    }

    /**
     * Components whose pushed schema would differ from the space, with a short reason per difference.
     * New components count; components that exist only in the space don't (a push leaves them alone).
     *
     * @param array $issues From compare().
     *
     * @return array<string, string[]> Component name => reasons.
     */
    public function changedComponents(array $issues): array
    {
        $changed = [];

        foreach ($issues as $issue) {
            if (in_array($issue['kind'], self::NOT_A_CHANGE, true)) {
                continue;
            }

            $changed[$issue['component']][] = $this->reason($issue);
        }

        foreach ($changed as &$reasons) {
            $reasons = array_values(array_unique($reasons));
        }

        unset($reasons);
        ksort($changed);

        return $changed;
    }

    /**
     * Whether pushing the template schema would orphan content this issue refers to.
     *
     * @param array $issue
     *
     * @return bool
     */
    public function isDestructive(array $issue): bool
    {
        return in_array($issue['kind'], self::DESTRUCTIVE_KINDS, true);
    }

    /**
     * Components from a pull: a CLI v4 pull mixes them with groups, presets and tags, so only items with a
     * schema count.
     *
     * @param array $pulled
     *
     * @return array<string, array>
     */
    public function spaceComponents(array $pulled): array
    {
        $items = $pulled['components'] ?? (array_is_list($pulled) ? $pulled : [$pulled]);

        return array_column(
            array_filter($items, static fn ($item) => is_array($item) && isset($item['name'], $item['schema'])),
            null,
            'name'
        );
    }

    /**
     * @param string $component
     * @param array $expectedFields
     * @param array $spaceFields
     * @param array $templates
     * @param array<string, string> $renames Old field name => new field name.
     *
     * @return array
     */
    private function compareFields(
        string $component,
        array $expectedFields,
        array $spaceFields,
        array $templates,
        array $renames,
    ): array {
        $issues = [];
        $expectedFields = $this->contentFields($expectedFields);
        $spaceFields = $this->contentFields($spaceFields);

        foreach (array_diff_key($spaceFields, $expectedFields) as $field => $_) {
            $issues[] = isset($renames[$field])
                ? $this->issue(self::LEVEL_WARNING, self::KIND_FIELD_RENAMED, $component, $field, sprintf('Renamed to "%s": the generated migration copies its content across.', $renames[$field]))
                : $this->issue(self::LEVEL_WARNING, self::KIND_FIELD_REMOVED, $component, $field, 'Only in the space (added in the Storyblok UI, or removed from the template). The next push removes it from the schema; stored content stays in Storyblok as "out of schema".');
        }

        $renamedTargets = array_flip($renames);
        $byPos = static function (array $fields): array {
            uasort($fields, static fn ($a, $b) => ($a['pos'] ?? 0) <=> ($b['pos'] ?? 0));

            return array_keys($fields);
        };
        $shared = array_intersect($byPos($expectedFields), array_keys($spaceFields));

        if (array_values($shared) !== array_values(array_intersect($byPos($spaceFields), $shared))) {
            $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_FIELD_ORDER, $component, null, 'Fields are in a different order in the space. The next push reorders them.');
        }

        foreach ($expectedFields as $field => $expected) {
            if (isset($spaceFields[$field]) && $differences = $this->settingDifferences($field, $expected, $spaceFields[$field])) {
                $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_FIELD_SETTINGS, $component, $field, 'Settings differ from the space (' . implode(', ', $differences) . '). The next push updates them.', $differences);
            }

            if (!isset($spaceFields[$field])) {
                if (!isset($renamedTargets[$field])) {
                    $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_FIELD_NOT_PUSHED, $component, $field, 'Defined by the template but not in the space: push to add it.');
                }

                continue;
            }

            $actual = $spaceFields[$field];

            if (!$this->typesMatch($expected['type'] ?? '', $actual['type'] ?? '')) {
                $issues[] = $this->issue(self::LEVEL_ERROR, self::KIND_TYPE_CHANGED, $component, $field, sprintf('Type is "%s" in the space but "%s" in the template.', $actual['type'] ?? '?', $expected['type'] ?? '?'));
                continue;
            }

            if (($expected['type'] ?? '') === 'option') {
                $issues = [...$issues, ...$this->compareOptions($component, $field, $expected, $actual)];
            }

            if (($expected['type'] ?? '') === 'bloks') {
                $issues = [...$issues, ...$this->compareWhitelist($component, $field, $expected, $actual, $templates)];
            }
        }

        return $issues;
    }

    /**
     * @param string $component
     * @param string $field
     * @param array $expected
     * @param array $actual
     *
     * @return array
     */
    private function compareOptions(string $component, string $field, array $expected, array $actual): array
    {
        $expectedSource = $expected['source'] ?? 'self';
        $actualSource = ($actual['source'] ?? '') ?: 'self';

        if ($expectedSource !== $actualSource) {
            return [$this->issue(self::LEVEL_ERROR, self::KIND_OPTION_SOURCE_CHANGED, $component, $field, sprintf('Option source is "%s" in the space but "%s" in the template.', $actualSource, $expectedSource))];
        }

        if ($expectedSource !== 'self') {
            return [];
        }

        $expectedValues = array_map('strval', array_column($expected['options'] ?? [], 'value'));
        $actualValues = array_map('strval', array_column($actual['options'] ?? [], 'value'));
        $issues = [];

        if ($unhandled = array_values(array_diff($actualValues, $expectedValues))) {
            $issues[] = $this->issue(self::LEVEL_ERROR, self::KIND_OPTION_VALUES_REMOVED, $component, $field, 'Option values in the space the template doesn\'t handle (they fall back to the default): ' . implode(', ', $unhandled), $unhandled);
        }

        if ($unpushed = array_values(array_diff($expectedValues, $actualValues))) {
            $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_OPTION_VALUES_NOT_PUSHED, $component, $field, 'Option values the template supports but editors can\'t choose yet: ' . implode(', ', $unpushed), $unpushed);
        }

        return $issues;
    }

    /**
     * @param string $component
     * @param string $field
     * @param array $expected
     * @param array $actual
     * @param array $templates
     *
     * @return array
     */
    private function compareWhitelist(string $component, string $field, array $expected, array $actual, array $templates): array
    {
        $expectedAllowed = !empty($expected['restrict_components']) ? ($expected['component_whitelist'] ?? []) : null;
        $actualAllowed = !empty($actual['restrict_components']) ? ($actual['component_whitelist'] ?? []) : null;
        $issues = [];

        if ($actualAllowed === null) {
            if ($expectedAllowed !== null) {
                $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_WHITELIST_UNRESTRICTED, $component, $field, 'Any blok can be added in the space, but the template restricts it: push to apply the whitelist.');
            }

            return $issues;
        }

        if ($withoutTemplate = array_values(array_diff($actualAllowed, array_keys($templates)))) {
            $issues[] = $this->issue(self::LEVEL_ERROR, self::KIND_WHITELIST_WITHOUT_TEMPLATE, $component, $field, 'The space allows bloks with no template: ' . implode(', ', $withoutTemplate), $withoutTemplate);
        }

        if ($expectedAllowed !== null) {
            if ($removed = array_values(array_intersect(array_diff($actualAllowed, $expectedAllowed), array_keys($templates)))) {
                $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_WHITELIST_REMOVED, $component, $field, 'Bloks the space allows but the template no longer does (existing ones stay, editors can\'t add more): ' . implode(', ', $removed), $removed);
            }

            if ($unpushed = array_values(array_diff($expectedAllowed, $actualAllowed))) {
                $issues[] = $this->issue(self::LEVEL_WARNING, self::KIND_WHITELIST_NOT_PUSHED, $component, $field, 'Bloks the template allows but the space doesn\'t yet: ' . implode(', ', $unpushed), $unpushed);
            }
        }

        return $issues;
    }

    /**
     * Label, content-type flag and Block Library folder.
     *
     * @param string $component
     * @param array $expected
     * @param array $actual
     * @param array<string, string> $folders Space folder UUID => name.
     *
     * @return array
     */
    private function compareComponent(string $component, array $expected, array $actual, array $folders): array
    {
        $differences = [];

        $label = static fn ($value) => $value === null || $value === '' || $value === ucfirst(str_replace('_', ' ', $component)) ? null : $value;

        if ($label($expected['display_name'] ?? null) !== $label($actual['display_name'] ?? null)) {
            $differences[] = 'label';
        }

        // Component descriptions aren't compared: Storyblok doesn't store them from a CLI push, so they would
        // always differ. Field descriptions are stored and are compared in settingDifferences().

        if ((bool)($expected['is_root'] ?? false) !== (bool)($actual['is_root'] ?? false)) {
            $differences[] = 'content type';
        }

        $expectedFolder = $expected['component_group_name'] ?? null;
        $actualFolder = $folders[$actual['component_group_uuid'] ?? ''] ?? null;

        // Only comparable when the pull includes folders (CLI v4 pulls do).
        if ($folders && $expectedFolder !== $actualFolder) {
            $differences[] = 'folder';
        }

        if (!$differences) {
            return [];
        }

        return [$this->issue(self::LEVEL_WARNING, self::KIND_COMPONENT_SETTINGS, $component, null, 'Component settings differ from the space (' . implode(', ', $differences) . '). The next push updates them.', $differences)];
    }

    /**
     * @param string $field
     * @param array $expected
     * @param array $actual
     *
     * @return string[] Editor-facing names of the settings that differ.
     */
    private function settingDifferences(string $field, array $expected, array $actual): array
    {
        $differences = [];
        // Storyblok stores unset values as null, empty strings or false; the generator omits them.
        $normalise = static fn ($value) => $value === null || $value === '' || $value === false || $value === []
            ? null
            : (is_scalar($value) && !is_bool($value) ? (string)$value : $value);

        foreach (self::FIELD_SETTINGS as $key => $label) {
            $want = $expected[$key] ?? null;
            $have = $actual[$key] ?? null;

            // An empty label in Storyblok shows the key-derived label, which is what the generator writes.
            if ($key === 'display_name') {
                $want = $want === $this->label($field) ? null : $want;
                $have = $have === $this->label($field) ? null : $have;
            }

            if ($normalise($want) !== $normalise($have)) {
                $differences[] = $label;
            }
        }

        // Option labels (values are compared separately).
        if (isset($expected['options'], $actual['options'])
            && array_column($expected['options'], 'name', 'value') != array_column($actual['options'], 'name', 'value')
            && array_keys(array_column($expected['options'], 'name', 'value')) == array_keys(array_column($actual['options'], 'name', 'value'))
        ) {
            $differences[] = 'option labels';
        }

        return $differences;
    }

    /**
     * @param array $pulled
     *
     * @return array<string, string>
     */
    private function folderNames(array $pulled): array
    {
        $items = $pulled['components'] ?? (array_is_list($pulled) ? $pulled : [$pulled]);
        $folders = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['uuid'], $item['name']) && !isset($item['schema'])) {
                $folders[$item['uuid']] = $item['name'];
            }
        }

        return $folders;
    }

    /**
     * @param array $issue
     *
     * @return string
     */
    private function reason(array $issue): string
    {
        $field = $issue['field'] !== null ? $issue['field'] . ': ' : '';

        return $field . match ($issue['kind']) {
            self::KIND_COMPONENT_NOT_PUSHED => 'new component',
            self::KIND_FIELD_NOT_PUSHED => 'field added',
            self::KIND_FIELD_REMOVED => 'field removed',
            self::KIND_FIELD_RENAMED => 'field renamed',
            self::KIND_TYPE_CHANGED => 'type changed',
            self::KIND_OPTION_SOURCE_CHANGED => 'option source changed',
            self::KIND_OPTION_VALUES_REMOVED => 'option values removed (' . implode(', ', $issue['values']) . ')',
            self::KIND_OPTION_VALUES_NOT_PUSHED => 'option values added (' . implode(', ', $issue['values']) . ')',
            self::KIND_WHITELIST_WITHOUT_TEMPLATE, self::KIND_WHITELIST_REMOVED => 'allowed bloks removed (' . implode(', ', $issue['values']) . ')',
            self::KIND_WHITELIST_NOT_PUSHED => 'allowed bloks added (' . implode(', ', $issue['values']) . ')',
            self::KIND_WHITELIST_UNRESTRICTED => 'whitelist added',
            self::KIND_LAYOUT => 'tabs/groups changed',
            self::KIND_FIELD_ORDER => 'field order changed',
            self::KIND_FIELD_SETTINGS, self::KIND_COMPONENT_SETTINGS => implode(', ', $issue['values']) . ' changed',
            default => $issue['kind'],
        };
    }

    /**
     * Tabs (by label) and groups (by key) with their members, compared ignoring order and Storyblok's ids.
     *
     * @param string $component
     * @param array $expectedFields
     * @param array $spaceFields
     *
     * @return array
     */
    private function compareLayout(string $component, array $expectedFields, array $spaceFields): array
    {
        if ($this->layoutOf($expectedFields) === $this->layoutOf($spaceFields)) {
            return [];
        }

        return [$this->issue(self::LEVEL_WARNING, self::KIND_LAYOUT, $component, null, 'Tabs or groups differ from the space (changed in the Storyblok UI?). The next push replaces them with the template layout; content isn\'t affected.')];
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    private function layoutOf(array $fields): array
    {
        $layout = [];

        foreach ($fields as $key => $field) {
            if (!is_array($field) || !in_array($field['type'] ?? '', self::LAYOUT_TYPES, true)) {
                continue;
            }

            $keys = $field['keys'] ?? [];
            sort($keys);
            $layout[$field['type'] . ':' . ($field['type'] === 'tab' ? ($field['display_name'] ?? $key) : $key)] = $keys;
        }

        ksort($layout);

        return $layout;
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    private function contentFields(array $fields): array
    {
        return array_filter(
            $fields,
            static fn ($field) => is_array($field) && !in_array($field['type'] ?? '', self::LAYOUT_TYPES, true)
        );
    }

    /**
     * @param string $expected
     * @param string $actual
     *
     * @return bool
     */
    private function typesMatch(string $expected, string $actual): bool
    {
        if ($expected === $actual) {
            return true;
        }

        foreach (self::COMPATIBLE_TYPES as $group) {
            if (in_array($expected, $group, true) && in_array($actual, $group, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $level
     * @param string $kind
     * @param string $component
     * @param string|null $field
     * @param string $message
     * @param string[] $values Option values or bloks the issue is about, for usage counts.
     *
     * @return array{level: string, kind: string, component: string, field: string|null, message: string, values: string[]}
     */
    private function issue(
        string $level,
        string $kind,
        string $component,
        ?string $field,
        string $message,
        array $values = [],
    ): array {
        return [
            'level' => $level,
            'kind' => $kind,
            'component' => $component,
            'field' => $field,
            'message' => $message,
            'values' => $values,
        ];
    }

    /**
     * The generator's default label for a key: underscores to spaces, first letter capitalised.
     *
     * @param string $value
     *
     * @return string
     */
    private function label(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}
