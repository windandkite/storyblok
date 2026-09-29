<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

/**
 * The reverse of ComponentSchemaGenerator: turns components pulled from a Storyblok space into
 * `@storyblok` docblock definitions, and writes them into templates.
 *
 * Field types the docblock shorthand covers are converted to it; anything else (custom field plugins,
 * datasource options, multi-options, multi-assets...) is kept verbatim with the "storyblok" key, so an
 * import followed by `storyblok:schema:generate` reproduces the space's schema.
 */
class ComponentSchemaImporter
{
    private const TAG = '@storyblok';
    private const LAYOUT_TYPES = ['tab', 'section'];
    private const IDE_NAMESPACE = '\\WindAndKite\\Storyblok\\Ide\\Block\\';

    /**
     * @param array $component A component pulled from the space.
     * @param array<string, string> $folders Component group UUID => name.
     *
     * @return array The @storyblok definition.
     */
    public function toDefinition(array $component, array $folders = []): array
    {
        $name = (string)$component['name'];
        $schema = $component['schema'] ?? [];
        [$tabOf, $groupOf] = $this->layoutOf($schema);

        $fields = array_filter(
            $schema,
            static fn ($field) => is_array($field) && !in_array($field['type'] ?? '', self::LAYOUT_TYPES, true)
        );
        uasort($fields, static fn ($a, $b) => ($a['pos'] ?? 0) <=> ($b['pos'] ?? 0));

        $definition = array_filter([
            'name' => ComponentSchemaGenerator::templateName($name) !== $name ? $name : null,
            'folder' => $folders[$component['component_group_uuid'] ?? ''] ?? null,
            'root' => !empty($component['is_root']) ? true : null,
            // Only when it differs from the default for bloks (nestable) and content types (not nestable).
            'nestable' => array_key_exists('is_nestable', $component) && (bool)$component['is_nestable'] === !empty($component['is_root'])
                ? (bool)$component['is_nestable']
                : null,
            'display_name' => ($component['display_name'] ?? null) && $component['display_name'] !== $this->label($name)
                ? $component['display_name']
                : null,
            'description' => ($component['description'] ?? '') !== '' ? $component['description'] : null,
        ], static fn ($value) => $value !== null);

        $definition['fields'] = [];

        foreach ($fields as $key => $field) {
            $key = (string)$key;
            $definition['fields'][$key] = array_filter(
                $this->toField($key, $field) + ['tab' => $tabOf[$key] ?? null, 'group' => $groupOf[$key] ?? null],
                static fn ($value) => $value !== null
            );
        }

        return $definition;
    }

    /**
     * The docblock lines for a definition: top-level keys one per line, one line per field.
     *
     * @param array $definition
     *
     * @return string Docblock body lines (each starting with " * "), without the opening or closing.
     */
    public function formatTag(array $definition): string
    {
        $encode = fn ($value) => str_replace('*/', '*\/', $this->encode($value));
        $lines = [' * ' . self::TAG . ' {'];
        $keys = array_keys($definition);

        foreach ($keys as $index => $key) {
            $comma = $index < count($keys) - 1 ? ',' : '';

            if ($key !== 'fields') {
                $lines[] = sprintf(' *   %s: %s%s', $encode($key), $encode($definition[$key]), $comma);
                continue;
            }

            $lines[] = ' *   "fields": {';
            $fieldNames = array_keys($definition['fields']);

            foreach ($fieldNames as $fieldIndex => $fieldName) {
                $lines[] = sprintf(
                    ' *     %s: %s%s',
                    $encode($fieldName),
                    $encode($definition['fields'][$fieldName] ?: new \stdClass()),
                    $fieldIndex < count($fieldNames) - 1 ? ',' : ''
                );
            }

            $lines[] = ' *   }' . $comma;
        }

        $lines[] = ' * }';

        return implode("\n", $lines);
    }

    /**
     * Put the tag into a template's first docblock (replacing an existing tag), and add the IDE helper
     * class to the template's `@var ... $block` hint. The markup is never changed.
     *
     * @param string $source
     * @param string $tag From formatTag().
     * @param string $component Storyblok component name.
     *
     * @return string
     */
    public function applyToTemplate(string $source, string $tag, string $component): string
    {
        $docblock = $this->firstDocblock($source);

        // Plain string operations only: space text in the tag ("$0", "\\") must never reach a regex replacement.
        if ($docblock !== null && str_contains($docblock, self::TAG)) {
            $source = $this->replaceFirst($source, $docblock, $this->replaceTag($docblock, $tag));
        } elseif ($docblock !== null && $this->isHeader($source, $docblock)) {
            $source = $this->replaceFirst($source, $docblock, rtrim(substr($docblock, 0, -2)) . "\n *\n" . $tag . "\n */");
        } elseif (str_starts_with($source, '<?php') && !ctype_alnum(substr($source, 5, 1))) {
            // Right after the opening tag, whatever follows it (a newline, CRLF or `declare(...)` on the same line).
            $source = "<?php\n/**\n" . $tag . "\n */" . substr($source, 5);
        } else {
            // A template starting with markup gets its own PHP block.
            $source = "<?php\n/**\n" . $tag . "\n */\n?>\n" . $source;
        }

        return $this->addIdeHint($source, $component);
    }

    /**
     * A new template for a component with no template: the docblock, the IDE hint and minimal escaped
     * markup per field, with one root element for the Visual Editor. Meant to be replaced by real markup.
     *
     * @param string $component
     * @param array $definition
     * @param string $tag
     *
     * @return string
     */
    public function newTemplate(string $component, array $definition, string $tag): string
    {
        $lines = [];

        // Names and labels come from the space: never let them close a comment.
        $comment = static fn (string $text) => str_replace('*/', '*\\/', $text);

        foreach ($definition['fields'] ?? [] as $name => $field) {
            $data = sprintf('$block->getData(%s)', var_export($name, true));
            $method = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name))) . 'Html';

            $lines[] = match ($field['type'] ?? null) {
                'string', 'integer', 'datetime', null => isset($field['storyblok'])
                    ? '    <?php /* ' . $comment(sprintf('%s (%s): %s', $name, $field['storyblok']['type'] ?? 'custom', $data)) . ' */ ?>'
                    : sprintf("    <?php if ((string)%1\$s !== ''): ?>\n        <p><?= \$escaper->escapeHtml(%1\$s) ?></p>\n    <?php endif ?>", $data),
                'richtext', 'array' => sprintf('    <?= /** @noEscape */ $block->%s() ?>', $method),
                'asset' => sprintf(
                    "    <?php if (!empty(%1\$s['filename'])): ?>\n        <img src=\"<?= \$escaper->escapeUrl(%1\$s['filename']) ?>\" alt=\"<?= \$escaper->escapeHtmlAttr(%1\$s['alt'] ?? '') ?>\" loading=\"lazy\">\n    <?php endif ?>",
                    $data
                ),
                default => '    <?php /* ' . $comment(sprintf('%s (%s): %s', $name, $field['type'], $data)) . ' */ ?>',
            };
        }

        return "<?php\n/**\n * " . $comment((string)($definition['display_name'] ?? $this->label($component)))
            . " (created by storyblok:schema:import: replace the starter markup below).\n *\n"
            . $tag . "\n */\n\n"
            . "declare(strict_types=1);\n\n"
            . "use Magento\\Framework\\Escaper;\n\n"
            . '/** @var \\WindAndKite\\Storyblok\\Block\\Block|' . self::IDE_NAMESPACE . ComponentSchemaGenerator::ideClassName($component) . " \$block */\n"
            . "/** @var Escaper \$escaper */\n"
            . "?>\n<div>\n" . implode("\n", $lines) . "\n</div>\n";
    }

    /**
     * Single-line JSON in the docblock house style: `{"type": "string", "enum": ["a", "b"]}`.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function encode(mixed $value): string
    {
        // stdClass forces an object, e.g. labels keyed "0", "1", which would otherwise encode as a list.
        $object = $value instanceof \stdClass;
        $value = $object ? (array)$value : $value;

        if (!is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (!$object && array_is_list($value)) {
            return '[' . implode(', ', array_map([$this, 'encode'], $value)) . ']';
        }

        $pairs = [];

        foreach ($value as $key => $item) {
            $pairs[] = json_encode((string)$key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ': ' . $this->encode($item);
        }

        return '{' . implode(', ', $pairs) . '}';
    }

    /**
     * @param string $key
     * @param array $field
     *
     * @return array
     */
    private function toField(string $key, array $field): array
    {
        $type = $field['type'] ?? '';
        $source = ($field['source'] ?? '') ?: 'self';

        // Anything the shorthand can't express keeps the field verbatim, so import never drops a setting.
        if (!$this->isExpressible($field, $type, $source)) {
            $raw = $field;
            unset($raw['id'], $raw['pos']);

            return ['storyblok' => $raw];
        }

        $common = array_filter([
            'required' => !empty($field['required']) ? true : null,
            'description' => ($field['description'] ?? '') !== '' ? $field['description'] : null,
            'display_name' => ($field['display_name'] ?? null) && $field['display_name'] !== $this->label($key)
                ? $field['display_name']
                : null,
            'translatable' => !empty($field['translatable']) ? true : null,
        ], static fn ($value) => $value !== null);

        $mapped = match (true) {
            in_array($type, ['text', 'textarea', 'markdown'], true) => ['type' => 'string'] + ($type !== 'text' ? ['format' => $type] : []),
            $type === 'number' => ['type' => 'integer'],
            $type === 'boolean' => ['type' => 'boolean'],
            $type === 'datetime' => ['type' => 'datetime'],
            $type === 'richtext' => ['type' => 'richtext'],
            $type === 'table' => ['type' => 'object'],
            $type === 'multilink' => ['type' => 'multilink'],
            // No file types means any file type; the shorthand's default is images only.
            $type === 'asset' => ['type' => 'asset'] + (($field['filetypes'] ?? []) !== ['images'] ? ['filetypes' => array_values($field['filetypes'] ?? [])] : []),
            $type === 'bloks' => ['type' => 'array'] + (!empty($field['restrict_components']) ? ['allowed' => array_values($field['component_whitelist'] ?? [])] : []),
            $type === 'option' && $source === 'internal_stories' => ['type' => 'story'] + (!empty($field['folder_slug']) ? ['folder' => $field['folder_slug']] : []),
            $type === 'option' && $source === 'self' => $this->toEnum($field['options'] ?? []),
            default => null,
        };

        if ($mapped === null) {
            $raw = $field;
            unset($raw['id'], $raw['pos']);

            return ['storyblok' => $raw];
        }

        if (array_key_exists('default_value', $field) && $field['default_value'] !== '' && $field['default_value'] !== null) {
            $mapped['default'] = match ($mapped['type']) {
                'boolean' => (bool)$field['default_value'],
                // Integers only when lossless: "1.5" stays "1.5".
                'integer' => (string)(int)$field['default_value'] === (string)$field['default_value'] ? (int)$field['default_value'] : $field['default_value'],
                default => $field['default_value'],
            };
        }

        return $mapped + $common;
    }

    /**
     * Whether every setting of a pulled field has a docblock equivalent. Unset values (null, "", false, [])
     * don't count: Storyblok writes many of them for every field.
     *
     * @param array $field
     * @param string $type
     * @param string $source
     *
     * @return bool
     */
    private function isExpressible(array $field, string $type, string $source): bool
    {
        $common = ['type', 'pos', 'id', 'display_name', 'description', 'required', 'default_value', 'translatable'];
        $byType = match (true) {
            in_array($type, ['text', 'textarea', 'markdown', 'number', 'boolean', 'datetime', 'richtext', 'table'], true) => [],
            $type === 'asset' => ['filetypes'],
            $type === 'bloks' => ['restrict_components', 'component_whitelist', 'restrict_type'],
            $type === 'multilink' => ['email_link_type', 'asset_link_type', 'show_anchor', 'allow_target_blank'],
            $type === 'option' && $source === 'self' => ['options', 'source'],
            // Story pickers store the story's UUID (use_uuid), which is what the shorthand generates.
            $type === 'option' && $source === 'internal_stories' => ['source', 'folder_slug', 'use_uuid'],
            default => null,
        };

        if ($byType === null) {
            return false;
        }

        $set = array_filter($field, static fn ($value) => $value !== null && $value !== '' && $value !== false && $value !== []);

        if (array_diff_key($set, array_flip([...$common, ...$byType]))) {
            return false;
        }

        return match ($type) {
            // The shorthand writes every link type on.
            'multilink' => array_filter(
                ['email_link_type', 'asset_link_type', 'show_anchor', 'allow_target_blank'],
                static fn ($key) => ($field[$key] ?? false) !== true
            ) === [],
            // Folder and tag whitelists have no shorthand.
            'bloks' => in_array($field['restrict_type'] ?? '', ['', 'components'], true),
            'option' => $source !== 'internal_stories' || ($field['use_uuid'] ?? true) !== false,
            default => true,
        };
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function toEnum(array $options): array
    {
        $values = array_map(static fn ($option) => (string)($option['value'] ?? ''), $options);
        // Integers only when lossless: "01" must stay "01", or content using it would be orphaned.
        $numeric = $values && array_filter($values, static fn ($value) => (string)(int)$value !== $value) === [];

        // Only labels the generator wouldn't derive from the value are kept.
        $labels = [];

        foreach ($options as $option) {
            $value = (string)($option['value'] ?? '');
            $name = (string)($option['name'] ?? '');

            if ($name !== '' && $name !== $this->label($value)) {
                $labels[$value] = $name;
            }
        }

        return [
            'type' => $numeric ? 'integer' : 'string',
            'enum' => $numeric ? array_map('intval', $values) : $values,
        ] + ($labels ? ['labels' => (object)$labels] : []);
    }

    /**
     * Field key => tab label, and field key => group key.
     *
     * @param array $schema
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function layoutOf(array $schema): array
    {
        $tabOf = [];
        $groupOf = [];

        foreach ($schema as $key => $entry) {
            if (($entry['type'] ?? null) === 'section') {
                foreach ($entry['keys'] ?? [] as $field) {
                    $groupOf[$field] = (string)$key;
                }
            }
        }

        foreach ($schema as $key => $entry) {
            if (($entry['type'] ?? null) !== 'tab') {
                continue;
            }

            foreach ($entry['keys'] ?? [] as $member) {
                // A tab lists its groups and their fields; either way the fields belong to the tab.
                foreach (isset($schema[$member]) && ($schema[$member]['type'] ?? null) === 'section' ? $schema[$member]['keys'] ?? [] : [$member] as $field) {
                    $tabOf[$field] = (string)($entry['display_name'] ?? $key);
                }
            }
        }

        return [$tabOf, $groupOf];
    }

    /**
     * Replace the tag in a docblock, keeping the text before it and any tags after it. Finds the tag the way
     * ComponentSchemaGenerator::parse() does, so single-line (`/** @storyblok {...} *\/`) and mid-line tags work.
     *
     * @param string $docblock
     * @param string $tag
     *
     * @return string
     */
    private function replaceTag(string $docblock, string $tag): string
    {
        $position = strpos($docblock, self::TAG);
        // The text before the tag, without the tag line's own " * " or blank " *" lines before it.
        $head = (string)preg_replace('/(\n[ \t]*\*[ \t]*)+$/', '', rtrim(substr($docblock, 0, $position)));
        $rest = substr($docblock, $position);
        // The JSON ends at the next line starting with "@", as in parse().
        $tail = preg_match('/^[ \t]*\*[ \t]*@\w/m', $rest, $matches, PREG_OFFSET_CAPTURE)
            ? " *\n" . rtrim(substr($rest, $matches[0][1])) . "\n"
            : '';
        $tail = (string)preg_replace('/\s*\*\/\n?$/', '', $tail);

        return ($head === '/**' ? '/**' : $head . "\n *") . "\n" . $tag . "\n" . ($tail !== '' ? $tail . "\n" : '') . ' */';
    }

    /**
     * @param string $haystack
     * @param string $search
     * @param string $replace
     *
     * @return string
     */
    private function replaceFirst(string $haystack, string $search, string $replace): string
    {
        $position = strpos($haystack, $search);

        return $position === false ? $haystack : substr_replace($haystack, $replace, $position, strlen($search));
    }

    /**
     * @param string $source
     * @param string $component
     *
     * @return string
     */
    private function addIdeHint(string $source, string $component): string
    {
        $class = self::IDE_NAMESPACE . ComponentSchemaGenerator::ideClassName($component);

        if (str_contains($source, $class)) {
            return $source;
        }

        $updated = preg_replace_callback(
            '/@var\s+([^\s*]+)\s+\$block\b/',
            static fn (array $matches) => '@var ' . $matches[1] . '|' . $class . ' $block',
            $source,
            1,
            $count
        );

        if ($count) {
            return (string)$updated;
        }

        // No `@var ... $block` yet: add one after the header docblock.
        $docblock = $this->firstDocblock($source);

        return $docblock === null
            ? $source
            : $this->replaceFirst($source, $docblock, $docblock . "\n\n/** @var \\WindAndKite\\Storyblok\\Block\\Block|" . $class . ' $block */');
    }

    /**
     * @param string $source
     *
     * @return string|null
     */
    private function firstDocblock(string $source): ?string
    {
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                return $token[1];
            }
        }

        return null;
    }

    /**
     * Whether the docblock is a file header (directly after `<?php`), not a variable hint.
     *
     * @param string $source
     * @param string $docblock
     *
     * @return bool
     */
    private function isHeader(string $source, string $docblock): bool
    {
        return (bool)preg_match('/^<\?php\s*' . preg_quote($docblock, '/') . '/', $source)
            && !preg_match('/^\/\*\*\s*@var\b/', $docblock);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function label(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}
