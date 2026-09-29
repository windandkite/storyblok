<?php

declare(strict_types=1);

namespace WindAndKite\Storyblok\Service;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Design\Fallback\RulePool;
use Magento\Framework\View\Design\ThemeInterface;

/**
 * Builds a Storyblok CLI component schema ({"components": [...], "component_groups": [...]}) from the
 * "@storyblok" docblock tag of every blok template a theme would render.
 *
 * Templates are discovered with the same fallback the renderer uses (theme chain, compatibility modules,
 * this module), so the schema always describes the template that actually renders:
 * - the first template found for a component that has an @storyblok tag defines it;
 * - an override without the tag falls through to the next template down the chain;
 * - an override with the tag replaces the definition entirely.
 *
 * Tag format: "@storyblok" followed by JSON in the template's first docblock (other tags may follow it):
 *
 *     @storyblok {
 *       "folder": "Layout",
 *       "whitelists": ["content"],
 *       "fields": {
 *         "body": {"type": "array", "allowed": ["column", "@content"]},
 *         "span_tablet": {"type": "integer", "enum": [4, 8], "tab": "Responsive", "group": "Tablet"},
 *         "content": {"type": "array", "renamed_from": "body"}
 *       }
 *     }
 *
 * - "name": Storyblok component name, when it differs from the template file name (e.g. "form-input"
 *   renders block/form_input.phtml).
 * - "folder": Block Library folder.
 * - "whitelists": named lists this blok joins; fields allow a list's members with "@name", so any module
 *   or theme can add bloks to shared whitelists.
 * - "tab" / "group" on a field: Storyblok tab and group (collapsible section) it appears in. A group's key
 *   is its label, so group names must be unique within a component and not match a field name.
 * - "translatable" on a field: field-level translation. When omitted, the space's setting is kept.
 * - "nestable" on a component: whether it can be added to bloks fields. Defaults to true for bloks and
 *   false for content types ("root").
 * - Settings the format can't express (e.g. max_length, regex, multilink options) are kept from the space
 *   when it's pulled (see withSpaceSettings()); "storyblok" fields are used exactly as written.
 * - "labels" on an enum field: editor labels for values whose label isn't derived from the value, e.g.
 *   {"enum": ["s", "xl"], "labels": {"xl": "Extra Large"}}.
 * - "storyblok" on a field: a Storyblok field schema used verbatim (custom field plugins, datasources...).
 * - "renamed_from" on a field: the field was renamed; generates a copy migration and keeps the old field in
 *   the safe schema (see buildSafe()).
 */
class ComponentSchemaGenerator
{

    private const TEMPLATE_MODULE = 'WindAndKite_Storyblok';
    private const TEMPLATE_DIR = 'block';
    private const TAG = '@storyblok';
    private const IGNORED = ['fallback', 'fallback_item'];

    // Field settings templates always own: the space's values never survive a push.
    private const OWNED_FIELD_KEYS = [
        'type', 'pos', 'id', 'display_name', 'description', 'default_value', 'required', 'options', 'source',
        'folder_slug', 'filetypes', 'restrict_components', 'component_whitelist', 'restrict_type',
        'component_group_whitelist', 'component_tag_whitelist', 'keys',
    ];

    // Settings the generator writes a default for, but templates can't set: the space's value wins.
    private const SPACE_WINS_FIELD_KEYS = ['email_link_type', 'asset_link_type', 'show_anchor', 'allow_target_blank'];

    // Component settings the format can't express, kept from the space.
    private const SPACE_COMPONENT_KEYS = ['preview_field', 'preview_tmpl', 'color', 'icon', 'image'];

    /**
     * @var string[]
     */
    private array $warnings = [];

    /**
     * @var array<string, string>
     */
    private array $untagged = [];

    /**
     * @param RulePool $rulePool
     * @param File $file
     */
    public function __construct(
        private readonly RulePool $rulePool,
        private readonly File $file,
    ) {}

    /**
     * @param ThemeInterface $theme
     *
     * @return array{components: array, component_groups: array}
     * @throws LocalizedException
     */
    public function generate(ThemeInterface $theme): array
    {
        return $this->build($this->collect($theme));
    }

    /**
     * Non-fatal problems from the last collect(), e.g. templates without an @storyblok tag.
     *
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Templates found by the last collect() without an @storyblok tag anywhere in their fallback chain.
     *
     * @return array<string, string> Component name => highest-priority template path.
     */
    public function getUntagged(): array
    {
        return $this->untagged;
    }

    /**
     * @param ThemeInterface $theme
     *
     * @return array<string, array{file: string, definition: array}>
     * @throws LocalizedException
     */
    public function collect(ThemeInterface $theme): array
    {
        $this->warnings = [];
        $this->untagged = [];
        $found = [];
        $defined = [];
        $untagged = [];

        foreach ($this->getBlockDirectories($theme) as $blockDirectory) {
            foreach ($this->file->readDirectory($blockDirectory) as $path) {
                $template = basename($path, '.phtml');

                if (!str_ends_with($path, '.phtml') || isset($defined[$template]) || in_array($template, self::IGNORED, true)) {
                    continue;
                }

                $definition = $this->parse($this->file->fileGetContents($path), $path);

                if ($definition === null) {
                    $untagged[$template] ??= $path;
                    continue;
                }

                // "name" overrides the Storyblok component name, for names a file can't carry (e.g. "form-input").
                $name = (string)($definition['name'] ?? $template);

                // The renderer finds a component's template by name, so a name mapping to another file would
                // publish a schema this template never renders.
                if (self::templateName($name) !== $template) {
                    throw new LocalizedException(__(
                        '"name": "%1" in %2 is rendered by block/%3.phtml, not this template.',
                        $name,
                        $path,
                        self::templateName($name)
                    ));
                }

                if (isset($found[$name])) {
                    throw new LocalizedException(__('Storyblok component "%1" is defined by both %2 and %3.', $name, $found[$name]['file'], $path));
                }

                $defined[$template] = true;
                $found[$name] = ['file' => $path, 'template' => $template, 'definition' => $definition];
            }
        }

        $this->untagged = array_diff_key($untagged, $defined);

        foreach ($this->untagged as $name => $path) {
            $this->warnings[] = sprintf('No %s tag for "%s", not exported: %s', self::TAG, $name, $path);
        }

        ksort($found);

        return $found;
    }

    /**
     * Existing `block/` template directories for the theme, highest priority first (theme chain,
     * compatibility modules, this module): the same order the renderer resolves templates in.
     *
     * @param ThemeInterface $theme
     *
     * @return string[]
     */
    public function getBlockDirectories(ThemeInterface $theme): array
    {
        $directories = $this->rulePool->getRule(RulePool::TYPE_TEMPLATE_FILE)->getPatternDirs([
            'area' => Area::AREA_FRONTEND,
            'theme' => $theme,
            'module_name' => self::TEMPLATE_MODULE,
        ]);

        return array_values(array_filter(
            array_map(static fn ($directory) => $directory . '/' . self::TEMPLATE_DIR, $directories),
            fn ($directory) => $this->file->isDirectory($directory)
        ));
    }

    /**
     * IDE helper class (in \WindAndKite\Storyblok\Ide\Block) for a component, e.g. "form-input" -> FormInputBlok.
     *
     * @param string $component
     *
     * @return string
     */
    public static function ideClassName(string $component): string
    {
        $class = preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', self::templateName($component))))) . 'Blok';

        // Class names can't start with a digit (e.g. "3_column_grid").
        return ctype_digit($class[0]) ? '_' . $class : $class;
    }

    /**
     * Template file name (without .phtml) the renderer uses for a Storyblok component name, matching
     * AbstractStoryblok::getStoryblokTemplate(): camelCase and kebab-case become snake_case.
     *
     * @param string $component
     *
     * @return string
     */
    public static function templateName(string $component): string
    {
        return str_replace('-', '_', strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $component)));
    }

    /**
     * Extract the @storyblok JSON from a template's first docblock.
     *
     * @param string $source
     * @param string $path For error messages.
     *
     * @return array|null Null when the template has no tag.
     * @throws LocalizedException
     */
    public function parse(string $source, string $path): ?array
    {
        $docblock = null;

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                $docblock = $token[1];
                break;
            }
        }

        if ($docblock === null || ($position = strpos($docblock, self::TAG)) === false) {
            return null;
        }

        $body = substr($docblock, $position + strlen(self::TAG), -2);
        $json = preg_replace('/^[ \t]*\* ?/m', '', $body);
        // Other docblock tags may follow the schema: the JSON ends at the next line starting with "@".
        $json = preg_split('/^\s*@\w/m', $json, 2)[0];

        try {
            $definition = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new LocalizedException(__('Invalid %1 JSON in %2: %3', self::TAG, $path, $e->getMessage()));
        }

        if (!is_array($definition)) {
            throw new LocalizedException(__('%1 in %2 must be a JSON object.', self::TAG, $path));
        }

        return $definition;
    }

    /**
     * @param array<string, array{file: string, definition: array}> $components
     *
     * @return array{components: array, component_groups: array, renames: array, verbatim: array}
     *         renames: list of {component, from, to}; verbatim: component => field => true for "storyblok" fields.
     * @throws LocalizedException
     */
    public function build(array $components): array
    {
        $whitelists = [];

        foreach ($components as $name => $component) {
            foreach ($component['definition']['whitelists'] ?? [] as $whitelist) {
                $whitelists[$whitelist][] = (string)$name;
            }
        }

        $schema = [];
        $folders = [];
        $renames = [];
        $verbatim = [];
        // Numeric names ("404") become integer array keys.
        $componentNames = array_map('strval', array_keys($components));

        foreach ($components as $name => $component) {
            $name = (string)$name;
            $definition = $component['definition'];

            if (isset($definition['group']) && !isset($definition['folder'])) {
                throw new LocalizedException(__(
                    'Use "folder" for the Block Library folder in %1 ("group" is reserved for field groups).',
                    $component['file']
                ));
            }

            $fields = [];

            foreach ($definition['fields'] ?? [] as $fieldName => $field) {
                $fieldName = (string)$fieldName;
                $fields[$fieldName] = $this->toField($fieldName, $field, $whitelists, $componentNames, $component['file']);

                if (isset($field['storyblok'])) {
                    $verbatim[$name][$fieldName] = true;
                }

                if (!empty($field['renamed_from'])) {
                    $renames[] = ['component' => $name, 'from' => (string)$field['renamed_from'], 'to' => $fieldName];
                }
            }

            if (!empty($definition['folder'])) {
                $folders[$definition['folder']] = ['name' => $definition['folder'], 'parent_id' => null];
            }

            $schema[] = array_filter([
                'name' => $name,
                'display_name' => $definition['display_name'] ?? $this->label($name),
                'description' => $definition['description'] ?? null,
                'is_root' => !empty($definition['root']),
                'is_nestable' => isset($definition['nestable']) ? (bool)$definition['nestable'] : empty($definition['root']),
                'component_group_name' => $definition['folder'] ?? null,
                'schema' => $this->layout($name, $fields, $definition['fields'] ?? [], $component['file']),
            ], static fn ($value) => $value !== null);
        }

        return ['components' => $schema, 'component_groups' => array_values($folders), 'renames' => $renames, 'verbatim' => $verbatim];
    }

    /**
     * Assign positions and add Storyblok's layout entries for "tab" and "group", in the format Storyblok uses:
     * - group: key = its label, {"type": "section", "keys": [its fields]};
     * - tab: key = "tab-<uuid>", {"type": "tab", "display_name": ..., "keys": [its fields and groups]}.
     * Positions follow the docblock order; each tab and group is placed just before its first field.
     *
     * @param string $component
     * @param array $fields Built field schemas, keyed by field name.
     * @param array $definitions Docblock field definitions (for "tab" and "group").
     * @param string $file
     *
     * @return array
     * @throws LocalizedException
     */
    private function layout(string $component, array $fields, array $definitions, string $file): array
    {
        $schema = [];
        $tabs = [];
        $groups = [];
        $pos = 0;

        foreach ($fields as $name => $field) {
            $name = (string)$name;
            $tab = isset($definitions[$name]['tab']) ? (string)$definitions[$name]['tab'] : null;
            $group = isset($definitions[$name]['group']) ? (string)$definitions[$name]['group'] : null;

            if ($tab !== null && !isset($tabs[$tab])) {
                $tabs[$tab] = 'tab-' . $this->uuid('storyblok-tab:' . $component . ':' . $tab);
                $schema[$tabs[$tab]] = ['type' => 'tab', 'display_name' => $tab, 'keys' => [], 'pos' => $pos++];
            }

            if ($group !== null) {
                if (isset($fields[$group])) {
                    throw new LocalizedException(__('Group "%1" has the same name as a field in %2.', $group, $file));
                }

                // array_key_exists, not isset: a group outside any tab maps to null.
                if (!array_key_exists($group, $groups)) {
                    $groups[$group] = $tab;
                    $schema[$group] = ['type' => 'section', 'keys' => [], 'pos' => $pos++];

                    if ($tab !== null) {
                        $schema[$tabs[$tab]]['keys'][] = $group;
                    }
                } elseif ($groups[$group] !== $tab) {
                    throw new LocalizedException(__(
                        'Group "%1" is used in more than one tab in %2. Group names must be unique within a component.',
                        $group,
                        $file
                    ));
                }

                $schema[$group]['keys'][] = $name;
            }

            if ($tab !== null) {
                $schema[$tabs[$tab]]['keys'][] = $name;
            }

            $field['pos'] = $pos++;
            $schema[$name] = $field;
        }

        return $schema;
    }

    /**
     * Keep the space's settings that the docblock format can't express (e.g. max_length, regex, multilink
     * options), so a push never silently drops them. Templates own every setting in OWNED_FIELD_KEYS and
     * any other setting they write (such as "translatable"); "storyblok" fields are used exactly as written.
     * Only fields whose type and option source match the space's are merged.
     *
     * @param array{components: array, component_groups: array, renames: array, verbatim?: array} $final
     * @param array $spaceComponents Components pulled from the space (items with a schema).
     *
     * @return array Same shape as $final.
     */
    public function withSpaceSettings(array $final, array $spaceComponents): array
    {
        $space = array_column(
            array_filter($spaceComponents, static fn ($item) => is_array($item) && isset($item['name'], $item['schema'])),
            null,
            'name'
        );

        foreach ($final['components'] as &$component) {
            $spaceComponent = $space[$component['name']] ?? null;

            if ($spaceComponent === null) {
                continue;
            }

            foreach (self::SPACE_COMPONENT_KEYS as $key) {
                if (!array_key_exists($key, $component) && ($spaceComponent[$key] ?? null) !== null) {
                    $component[$key] = $spaceComponent[$key];
                }
            }

            foreach ($component['schema'] as $name => &$field) {
                $spaceField = $spaceComponent['schema'][$name] ?? null;

                if (!is_array($spaceField)
                    || isset($final['verbatim'][$component['name']][$name])
                    || in_array($field['type'] ?? '', ['tab', 'section'], true)
                    || ($field['type'] ?? null) !== ($spaceField['type'] ?? null)
                    || (($field['source'] ?? '') ?: 'self') !== (($spaceField['source'] ?? '') ?: 'self')
                ) {
                    continue;
                }

                $field = array_intersect_key($spaceField, array_flip(self::SPACE_WINS_FIELD_KEYS)) + $field
                    + array_diff_key($spaceField, array_flip(self::OWNED_FIELD_KEYS));
            }

            unset($field);
        }

        unset($component);

        return $final;
    }

    /**
     * Additive-only version of $final for pushing during development: the space's current schema plus
     * everything new from the templates. Nothing the space has is removed or changed:
     * - fields only in the space stay (a renamed field's old name gets a "Deprecated" description);
     * - option values and allowed bloks are merged;
     * - a field whose type changed keeps the space's type (report it: types can't change additively);
     * - settings the template doesn't set are kept from the space;
     * - settings that would restrict editors don't apply yet: `required` only if the space has it (never on
     *   new fields), file types are merged, a story picker keeps the space's folder, folder or tag whitelists
     *   and translation stay, and existing components keep their content type and nestable settings.
     *
     * @param array{components: array, component_groups: array, renames: array} $final
     * @param array $spaceComponents Components pulled from the space (items with a schema).
     *
     * @return array{components: array, component_groups: array, renames: array}
     */
    public function buildSafe(array $final, array $spaceComponents): array
    {
        $space = array_column(
            array_filter($spaceComponents, static fn ($item) => is_array($item) && isset($item['name'], $item['schema'])),
            null,
            'name'
        );
        $renamedTo = [];

        foreach ($final['renames'] ?? [] as $rename) {
            $renamedTo[$rename['component']][$rename['from']] = $rename['to'];
        }

        $safe = $final;

        foreach ($safe['components'] as &$component) {
            if (!isset($space[$component['name']])) {
                continue;
            }

            $spaceComponent = $space[$component['name']];

            // Where the blok can be used stays as it is until the final push.
            foreach (['is_root', 'is_nestable'] as $key) {
                if (array_key_exists($key, $spaceComponent)) {
                    $component[$key] = (bool)$spaceComponent[$key];
                }
            }

            // New fields can't be required yet: existing stories would fail validation on their next publish.
            foreach (array_diff_key($component['schema'], $spaceComponent['schema']) as $name => $field) {
                unset($component['schema'][$name]['required']);
            }

            $pos = count($component['schema']);

            foreach ($space[$component['name']]['schema'] as $name => $spaceField) {
                if (!is_array($spaceField) || in_array($spaceField['type'] ?? '', ['tab', 'section'], true)) {
                    continue;
                }

                unset($spaceField['id']);
                $field = $component['schema'][$name] ?? null;

                if ($field === null) {
                    if (isset($renamedTo[$component['name']][$name])) {
                        $spaceField['description'] = sprintf('Deprecated: renamed to "%s".', $renamedTo[$component['name']][$name]);
                    }

                    $spaceField['pos'] = $pos++;
                    $component['schema'][$name] = $spaceField;
                    continue;
                }

                // A different type or option source (e.g. a story picker becoming a fixed list) isn't additive.
                if (($field['type'] ?? null) !== ($spaceField['type'] ?? null)
                    || (($field['source'] ?? '') ?: 'self') !== (($spaceField['source'] ?? '') ?: 'self')
                ) {
                    $component['schema'][$name] = ['pos' => $field['pos']] + $spaceField;
                    continue;
                }

                // The template's settings over the space's, so nothing the space has is dropped.
                $component['schema'][$name] = $field + $spaceField;

                if (!empty($spaceField['translatable'])) {
                    $component['schema'][$name]['translatable'] = true;
                }

                if (isset($field['options'], $spaceField['options'])) {
                    $values = array_column($field['options'], 'value');

                    foreach ($spaceField['options'] as $option) {
                        if (!in_array($option['value'] ?? null, $values, true)) {
                            $component['schema'][$name]['options'][] = $option;
                        }
                    }
                }

                if (empty($spaceField['required'])) {
                    unset($component['schema'][$name]['required']);
                }

                // No file types in the space means any file type.
                if (empty($spaceField['filetypes'])) {
                    unset($component['schema'][$name]['filetypes']);
                } elseif (isset($field['filetypes'])) {
                    $component['schema'][$name]['filetypes'] = array_values(array_unique([...$field['filetypes'], ...$spaceField['filetypes']]));
                }

                if (($field['folder_slug'] ?? null) !== ($spaceField['folder_slug'] ?? null)) {
                    unset($component['schema'][$name]['folder_slug']);

                    if (!empty($spaceField['folder_slug'])) {
                        $component['schema'][$name]['folder_slug'] = $spaceField['folder_slug'];
                    }
                }

                // A folder or tag restriction can't be merged with a list: keep the space's as it is.
                if (!empty($spaceField['restrict_components']) && in_array($spaceField['restrict_type'] ?? '', ['groups', 'tags'], true)) {
                    foreach (['restrict_components', 'restrict_type', 'component_whitelist', 'component_group_whitelist', 'component_tag_whitelist'] as $key) {
                        unset($component['schema'][$name][$key]);

                        if (array_key_exists($key, $spaceField)) {
                            $component['schema'][$name][$key] = $spaceField[$key];
                        }
                    }
                } elseif (!empty($spaceField['restrict_components']) && !empty($field['restrict_components'])) {
                    $component['schema'][$name]['component_whitelist'] = array_values(array_unique([
                        ...$field['component_whitelist'] ?? [],
                        ...$spaceField['component_whitelist'] ?? [],
                    ]));
                } elseif (empty($spaceField['restrict_components'])) {
                    unset($component['schema'][$name]['restrict_components'], $component['schema'][$name]['component_whitelist']);
                }
            }
        }

        unset($component);

        return $safe;
    }

    /**
     * IDE-only stubs: one class per component with @method annotations for its fields, so templates can
     * type-hint `@var \WindAndKite\Storyblok\Ide\Block\<Component>Blok $block`. Never loaded by Magento.
     *
     * Storyblok returns option and number fields as strings, so they're annotated as strings.
     *
     * @param array<string, array{file: string, definition: array}> $components
     *
     * @return string PHP source.
     */
    public function buildIdeHelper(array $components): string
    {
        $classes = [];

        foreach ($components as $name => $component) {
            $lines = [];

            $methods = [];

            foreach ($component['definition']['fields'] ?? [] as $fieldName => $field) {
                $fieldName = (string)$fieldName;
                $method = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $fieldName)));

                // Magento maps getFooBar() to "foo_bar": skip names a getter can't reach (e.g. "fooBar", "image2x").
                if (self::getterKey($method) !== $fieldName || isset($methods[$method])) {
                    $lines[] = sprintf(' * Field "%s": no getter, use $block->getData(\'%s\')', str_replace('*/', '* /', $fieldName), str_replace(['*/', "'"], ['* /', "\\'"], $fieldName));
                    continue;
                }

                $methods[$method] = true;
                [$type, $note] = $this->ideType($field);
                $details = array_unique(array_filter([
                    $note,
                    isset($field['enum']) ? implode(' | ', array_map(static fn ($value) => '"' . $value . '"', $field['enum'])) : null,
                    isset($field['default']) ? 'default ' . var_export($field['default'], true) : null,
                    $field['description'] ?? null,
                ]));
                $lines[] = sprintf(' * @method %s %s()%s', $type, $method, $details ? ' ' . str_replace('*/', '* /', implode('. ', $details)) : '');

                if (in_array($field['type'] ?? '', ['array', 'richtext'], true)) {
                    $lines[] = sprintf(' * @method string %sHtml() Rendered HTML of "%s"', $method, $fieldName);
                }
            }

            $class = self::ideClassName($name);
            $classes[] = sprintf(
                "/**\n * Storyblok \"%s\" blok. Generated from %s\n *\n%s\n */\nclass %s extends \\WindAndKite\\Storyblok\\Block\\Block\n{\n}\n",
                $name,
                $component['file'],
                implode("\n", $lines),
                $class
            );
        }

        return "<?php\n/**\n * IDE helper generated by `bin/magento storyblok:schema:generate`. Do not edit, and never load it in Magento.\n */\n\n"
            . "namespace WindAndKite\\Storyblok\\Ide\\Block;\n\n"
            . implode("\n", $classes);
    }

    /**
     * The data key a magic getter reads, as Magento's DataObject::_underscore() derives it.
     *
     * @param string $method
     *
     * @return string
     */
    public static function getterKey(string $method): string
    {
        return strtolower(trim((string)preg_replace('/([A-Z]|[0-9]+)/', '_$1', lcfirst(substr($method, 3))), '_'));
    }

    /**
     * @param array $field
     *
     * @return array{0: string, 1: string|null} PHPDoc type and a short note.
     */
    private function ideType(array $field): array
    {
        return match ($field['type'] ?? 'string') {
            'boolean' => ['bool|null', null],
            'array' => ['array', 'Child bloks'],
            'richtext' => ['array|null', 'Rich text document'],
            'asset' => ['array|null', 'Storyblok asset (filename, alt, focus, ...)'],
            'multilink' => ['array|null', 'Storyblok link (resolve with the Link view model)'],
            'object' => ['array|null', 'Storyblok table (thead, tbody)'],
            'story' => ['string|null', 'Story UUID'],
            'integer' => ['string|null', 'Numeric string'],
            'datetime' => ['string|null', '"Y-m-d H:i", UTC'],
            default => ['string|null', null],
        };
    }

    /**
     * Shape a build() result for Storyblok CLI v4 (`storyblok components push`), which reads a flat list of
     * items from `<path>/components/<from>/*.json`: components (have "schema") and groups (have "uuid", no
     * "schema"), linked by component_group_uuid. Groups are matched to the target space by name, so the local
     * ids and UUIDs only need to be unique and consistent within the file.
     *
     * @param array{components: array, component_groups: array, renames?: array, verbatim?: array} $schema
     *
     * @return array
     */
    public function formatForCli(array $schema): array
    {
        $groups = [];

        foreach ($schema['component_groups'] as $group) {
            $groups[$group['name']] = [
                // The CLI keys groups by id: without a unique one, all groups collapse into the last.
                'id' => crc32('storyblok-group:' . $group['name']),
                'name' => $group['name'],
                'uuid' => $this->uuid('storyblok-group:' . $group['name']),
                'parent_id' => null,
                'parent_uuid' => null,
            ];
        }

        $components = array_map(function (array $component) use ($groups): array {
            $group = $component['component_group_name'] ?? null;
            unset($component['component_group_name']);

            if ($group !== null && isset($groups[$group])) {
                $component['component_group_uuid'] = $groups[$group]['uuid'];
            }

            return $component;
        }, $schema['components']);

        return [...array_values($groups), ...$components];
    }

    /**
     * JSON for a formatForCli() item list. Each component's schema is written as an object: PHP would write an
     * empty schema, or field names "0", "1", ..., as a list. Nested lists (options, whitelists) stay lists.
     *
     * @param array $items
     *
     * @return string
     */
    public static function toJson(array $items): string
    {
        $items = array_map(
            static function ($item) {
                if (is_array($item) && is_array($item['schema'] ?? null)) {
                    $item['schema'] = (object)$item['schema'];
                }

                return $item;
            },
            $items
        );

        return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Stable UUID-formatted id from a seed, so regenerating doesn't churn the file or recreate tabs.
     *
     * @param string $seed
     *
     * @return string
     */
    private function uuid(string $seed): string
    {
        $hash = md5($seed);

        return sprintf(
            '%s-%s-4%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex(8 | (hexdec($hash[16]) & 3)) . substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    /**
     * @param string $name
     * @param array $field
     * @param array<string, string[]> $whitelists
     * @param string[] $componentNames
     * @param string $file
     *
     * @return array
     * @throws LocalizedException
     */
    private function toField(
        string $name,
        array $field,
        array $whitelists,
        array $componentNames,
        string $file,
    ): array {
        // Escape hatch: a Storyblok field schema used verbatim, for anything the shorthand can't express
        // (custom field plugins, datasource options, multi-options...).
        if (isset($field['storyblok']) && is_array($field['storyblok'])) {
            $schema = $field['storyblok'];
            unset($schema['pos'], $schema['id']);
            $schema['display_name'] ??= $this->label($name);

            return $schema;
        }

        $type = $field['type'] ?? 'string';

        $schema = match (true) {
            isset($field['enum']) => [
                'type' => 'option',
                'options' => array_map(
                    fn ($value) => ['name' => (string)($field['labels'][(string)$value] ?? $this->label((string)$value)), 'value' => (string)$value],
                    $field['enum']
                ),
            ],
            $type === 'array' => array_filter([
                'type' => 'bloks',
                'restrict_components' => isset($field['allowed']),
                'component_whitelist' => $this->expandAllowed($field['allowed'] ?? [], $whitelists, $componentNames, $name, $file),
            ]),
            $type === 'asset' => ['type' => 'asset', 'filetypes' => $field['filetypes'] ?? ['images']],
            $type === 'multilink' => [
                'type' => 'multilink',
                'email_link_type' => true,
                'asset_link_type' => true,
                'show_anchor' => true,
                'allow_target_blank' => true,
            ],
            $type === 'richtext' => ['type' => 'richtext'],
            $type === 'datetime' => ['type' => 'datetime'],
            // Native story picker: stores the chosen story's UUID.
            $type === 'story' => array_filter([
                'type' => 'option',
                'source' => 'internal_stories',
                'folder_slug' => $field['folder'] ?? null,
            ]),
            $type === 'boolean' => ['type' => 'boolean'],
            $type === 'integer' => ['type' => 'number'],
            $type === 'object' => ['type' => 'table'],
            default => ['type' => $field['format'] ?? 'text'],
        };

        $schema['display_name'] = $field['display_name'] ?? $this->label($name);

        if (isset($field['default'])) {
            $schema['default_value'] = is_bool($field['default']) ? $field['default'] : (string)$field['default'];
        }

        if (!empty($field['required'])) {
            $schema['required'] = true;
        }

        if (isset($field['translatable'])) {
            $schema['translatable'] = (bool)$field['translatable'];
        }

        if (isset($field['description'])) {
            $schema['description'] = $field['description'];
        }

        return $schema;
    }

    /**
     * Expand "@whitelist" references and check every allowed name is an exported component.
     *
     * @param string[] $allowed
     * @param array<string, string[]> $whitelists
     * @param string[] $componentNames
     * @param string $field
     * @param string $file
     *
     * @return string[]
     * @throws LocalizedException
     */
    private function expandAllowed(
        array $allowed,
        array $whitelists,
        array $componentNames,
        string $field,
        string $file,
    ): array {
        $names = [];

        foreach ($allowed as $name) {
            if (!str_starts_with($name, '@')) {
                $names[] = $name;
                continue;
            }

            $key = substr($name, 1);

            if (!isset($whitelists[$key])) {
                throw new LocalizedException(__('Unknown whitelist "%1" in field "%2" of %3', $name, $field, $file));
            }

            $names = [...$names, ...$whitelists[$key]];
        }

        $names = array_values(array_unique($names));

        foreach ($names as $name) {
            if (!in_array($name, $componentNames, true)) {
                throw new LocalizedException(
                    __('Unknown component "%1" allowed in field "%2" of %3', $name, $field, $file)
                );
            }
        }

        return $names;
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
