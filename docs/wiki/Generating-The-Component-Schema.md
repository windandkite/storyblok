Blok templates can declare the Storyblok component they render in an `@storyblok` docblock. The `windandkite/module-storyblok-schema` package turns these into the component schema for the Storyblok CLI, checks templates against a space, and brings a space's existing components into templates. The templates become the source of truth: a field added to a template reaches the space with the next push, and a mismatch between the two is reported before it breaks a page.

## 📦 Installation

The commands are developer tooling: the storefront never runs them.

```bash
composer require windandkite/module-storyblok-schema
bin/magento setup:upgrade
```

They work with [Storyblok CLI v4](https://github.com/storyblok/storyblok-cli) (`npm i -g storyblok`), logged in with `storyblok login`.

---

## 🔁 The Workflow

```bash
# Pull the space (the space ID comes from --space, STORYBLOK_SPACE_ID or storyblok.config.ts)
storyblok components pull && storyblok stories pull

# Generate the schema, migrations and IDE helper, checked against the pulled space
bin/magento storyblok:schema:generate --store=default

# During development: push the additive "safe" schema (only written when it differs)
storyblok components push --from default-safe

# At deploy: push the final schema, then run the copy migrations for renamed fields
storyblok components push --from default
storyblok migrations run --from default
```

**Why two schemas?** Content editors keep working in the space while templates change. The final schema is exactly what the templates declare: pushing it removes fields that are no longer in a template, and stored content in those fields becomes "out of schema". The **safe** schema only adds: it keeps everything the space has (a renamed field's old name gets a "Deprecated" description) and holds back changes that would restrict editors, such as new required fields or a narrower whitelist. Push the safe schema while developing, and the final one when the templates using it are deployed.

**Destructive changes are guarded.** When the final schema would orphan stored content (a removed field, a changed type, a removed option value or blok), `generate` lists each change with how many stories and bloks use it (from `storyblok stories pull`) and asks before writing it. `--force` skips the question, for CI.

**Generated files** (under `--path`, default `.storyblok`):

| File | Contents |
|---|---|
| `components/<store_code>/` | The final schema. |
| `components/<store_code>-safe/` | The additive schema, when it differs from the final one. |
| `components/<store_code>-changes/` | With `--changed-only`: just the components that differ from the space, for review. |
| `migrations/<store_code>/*.renames.js` | Copy migrations for renamed fields, translations included. |
| `ide-helper.php` | IDE stubs for every blok's getters (see [IDE helper](#-ide-helper)). Never loaded by Magento. |

The schema comes from the templates the store's theme renders: the theme, its parents, compatibility modules and the core module, in the same fallback order as the storefront. A theme override without a tag uses the definition further down the chain; one with a tag replaces it.

---

## ✍️ Declaring a Blok

The tag goes in the template's first docblock, followed by JSON. Other tags may follow it.

```php
<?php
/**
 * Hero banner.
 *
 * @storyblok {
 *   "folder": "Content",
 *   "fields": {
 *     "title": {"type": "string", "required": true, "max_length": 80},
 *     "image": {"type": "asset"},
 *     "link": {"type": "multilink", "show_anchor": true},
 *     "theme": {"type": "string", "enum": ["light", "dark"], "default": "light", "tab": "Settings"}
 *   }
 * }
 */

declare(strict_types=1);

/** @var \WindAndKite\Storyblok\Block\Block|\WindAndKite\Storyblok\Ide\Block\HeroBlok $block */
```

Fields appear in the editor in the order they're declared. Labels are derived from field names (`span_desktop` becomes "Span desktop") unless set.

### Component keys

| Key | Meaning |
|---|---|
| `fields` | The blok's fields, keyed by field name. |
| `name` | The Storyblok component name, when it differs from the template file name: `"form-input"` renders `block/form_input.phtml`. |
| `display_name`, `description` | The editor label and description. |
| `folder` | The Block Library folder. |
| `root` | `true` for a content type. |
| `nestable` | Whether it can be added to bloks fields. Defaults to `true` for bloks and `false` for content types. |
| `whitelists` | Named lists the blok joins (see [Whitelists](#whitelists)). |

### Field types

| Shorthand | Storyblok field |
|---|---|
| `{"type": "string"}` | Text. The default when `type` is left out. |
| `{"type": "string", "format": "textarea"}` | Textarea (`"markdown"` for Markdown). |
| `{"type": "richtext"}` | Rich text. `"allowed": [...]` restricts the bloks that can be inserted. |
| `{"type": "number"}` | Number. |
| `{"type": "boolean"}` | Boolean. |
| `{"type": "datetime"}` | Date and time (`"disable_time": true` for a date). |
| `{"type": "string", "enum": ["s", "l"]}` | Single option. `{"type": "number", "enum": [4, 8]}` for numeric values. |
| `{"type": "string", "source": "internal", "datasource_slug": "colours"}` | Single option from a datasource. `"source": "internal_languages"` for languages, `"external"` with `external_datasource` for external JSON. |
| `{"type": "story"}` | Single story, stored as its UUID. `"folder"` limits it to a folder. |
| `{"type": "asset"}` | Asset, images only unless `"filetypes"` is set (`["images", "videos", "audios", "texts"]`; `[]` for any). |
| `{"type": "multilink"}` | Link. Link types are settings: `"email_link_type"`, `"asset_link_type"`, `"show_anchor"`, `"allow_target_blank"`. |
| `{"type": "object"}` | Table. |
| `{"type": "array"}` | Bloks. `"allowed": [...]` restricts which bloks can be added. |
| `{"type": "plugin", "plugin": "storyblok-vimeo"}` | A custom field plugin (see [Plugin fields](#plugin-fields)). |

`"multiple": true` turns an option, datasource, story or asset field into its multiple version: multi-options, multiple stories, a multi-asset.

### Field keys

| Key | Meaning |
|---|---|
| `display_name`, `description` | The editor label and description. |
| `default` | The default value. |
| `required` | Whether the field must be filled in. |
| `translatable` | Field-level translation. When left out, the space's setting is kept. |
| `labels` | Editor labels for enum values whose label isn't derived from the value: `{"enum": ["s", "xl"], "labels": {"xl": "Extra Large"}}`. |
| `tab`, `group` | The tab and group (collapsible section) the field appears in. A group's name is its label, so it must be unique within the blok and not match a field name. |
| `renamed_from` | The field's old name (see [Renaming a field](#renaming-a-field)). |

**Any other key is a Storyblok field setting**, copied to the field as it is: `max_length`, `regex`, `tooltip`, `min_value`, `max_value`, `decimals`, `steps`, `rich_markdown`, `max_options`, `filter_content_type`, bloks `minimum` and `maximum`, and so on. Settings a template doesn't set are kept from the space, so a setting changed in the Storyblok UI survives a push until a template sets it.

### Plugin fields

Any custom field plugin, third-party ones included, uses the plugin's field type name:

```json
"clip": {"type": "plugin", "plugin": "storyblok-vimeo", "options": {"player": "lite"}, "php": "\\Vendor\\Video\\Clip|null"}
```

- `options` are the plugin's options, as name/value pairs.
- `php` is the value's type in the IDE helper. It defaults to `array|null`, the raw value Storyblok stores.

Changing a field to another plugin is reported as a type change: the stored values were written by the old plugin.

### Whitelists

`allowed` lists the bloks a bloks or rich text field accepts. A blok can also join a named list with `"whitelists": ["content"]`, and a field allows every member with `"@content"`:

```json
"body": {"type": "array", "allowed": ["column", "@content"]}
```

Any module or theme can add its bloks to a shared list without editing the field's template.

### Renaming a field

Rename the field in the template and add `"renamed_from": "<old name>"`. `generate` writes a migration that copies the content across (translations included), and the safe schema keeps the old field, marked "Deprecated", until the final push. The new field must accept the old field's content: an incompatible type or a missing option value is reported against the old field.

### Anything else

A field can still be written as a raw Storyblok field schema, used exactly as written:

```json
"special": {"storyblok": {"type": "text", "some_new_setting": true}}
```

`import` only writes these when a field can't be expressed any other way.

---

## 🔍 Validating

```bash
bin/magento storyblok:schema:validate
```

Compares the pulled space with the templates and reports:
- **Errors:** bloks in the space with no template, type changes (including a different plugin or option source), option values the space has that the template doesn't, and whitelists allowing bloks that have no template.
- **Warnings:** components or fields on one side only, changed settings, field order, tabs and groups, folders, whitelist differences.

It exits non-zero on errors, and on warnings too with `--strict`, so it can run in CI after `storyblok components pull`.

---

## 📥 Importing an Existing Space

```bash
bin/magento storyblok:schema:import --dry-run
```

Adds `@storyblok` docblocks and IDE type hints to templates without one, and creates starter templates for components that have no template. Starter markup renders each field simply and is meant to be replaced.

- Templates in `vendor/` are never edited: copy them into the theme first (`--copy-vendor`, `--skip-vendor`, or answer the prompt).
- `--component` / `-c` only imports the named components; `--yes` applies every change without asking.
- `--overwrite` also replaces existing tags with the space's version. Shared whitelists (`@name`) are lost, as the space only has the expanded list.

---

## 💡 IDE Helper

`generate` writes `.storyblok/ide-helper.php`, with a class per blok that lists its getters and their types. Add it to the template's `@var` hint (`import` does this for you):

```php
/** @var \WindAndKite\Storyblok\Block\Block|\WindAndKite\Storyblok\Ide\Block\HeroBlok $block */
```

Your IDE then completes `$block->getTitle()`, `$block->getBodyHtml()` and so on. Fields whose names a magic getter can't reach (`fooBar`, which Magento reads as `foo_bar`) are listed with `$block->getData('fooBar')` instead. The file is only for the IDE: add it to `.gitignore`, or commit it so the whole team gets the hints.

---

## ⚙️ Options

| Option | Commands | Meaning |
|---|---|---|
| `--store` / `-s` | all | The store view (ID or code) whose theme is used. Defaults to the default store view. |
| `--path` | all | The Storyblok CLI base directory. Defaults to `.storyblok`. |
| `--space` | all | The space ID. |
| `--force` | `generate` | Write the final schema even if it would orphan stored content, without asking. |
| `--changed-only` | `generate` | Also write `<store_code>-changes/`. |
| `--no-ide-helper` | `generate` | Don't write the IDE helper. |
| `--strict` | `validate` | Fail on warnings as well as errors. |
| `--dry-run`, `--yes`, `--component`, `--copy-vendor`, `--skip-vendor`, `--overwrite` | `import` | See [Importing an Existing Space](#-importing-an-existing-space). |

---

## ➡️ What's Next

- [**Creating Custom Templates**](Creating-Custom-Templates)
    - How blok templates are found and rendered.
- [**Accessing Storyblok Data**](Accessing-Storyblok-Data)
    - Reading fields, assets and nested bloks in templates.
