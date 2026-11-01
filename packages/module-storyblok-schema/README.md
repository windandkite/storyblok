# WindAndKite_StoryblokSchema

Developer tooling for [`windandkite/module-storyblok`](https://github.com/windandkite/module-storyblok): `bin/magento` commands that keep Storyblok component schemas in step with blok templates. The storefront never runs them.

```bash
composer require windandkite/module-storyblok-schema
bin/magento setup:upgrade
```

## Commands

Blok templates can declare their Storyblok schema in an `@storyblok` docblock. This module collects these from every template a store's theme renders (the theme, compatibility modules and the core module). It then generates the schema, content migrations and an IDE helper for the Storyblok CLI (v4), checks templates against a space, and imports a space's existing schemas into templates.

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

# Check templates against the space, or bring a space's schemas into templates
bin/magento storyblok:schema:validate
bin/magento storyblok:schema:import --dry-run
```

| Command | Purpose |
|---|---|
| `storyblok:schema:generate` | Writes `.storyblok/components/<store_code>/` (final schema), `.storyblok/components/<store_code>-safe/` (additive, when it differs), `.storyblok/migrations/<store_code>/*.renames.js` (copy migrations) and `.storyblok/ide-helper.php`. Changes that would orphan stored content are listed with usage counts and need confirmation, or `--force`. `--changed-only` also writes `.storyblok/components/<store_code>-changes/`: just the components that differ from the space, for review. |
| `storyblok:schema:validate` | Compares the pulled space with the templates: missing templates, type and option mismatches, fields on one side only, changed field settings (labels, defaults, descriptions, required), field order, layout (tab/group) and folder differences. Exits non-zero on errors; `--strict` also fails on warnings. |
| `storyblok:schema:import` | Adds `@storyblok` docblocks and IDE type hints to untagged templates, and creates starter templates for components that have none. Templates in `vendor/` are never edited; they can be copied into the theme (`--copy-vendor` / `--skip-vendor`, or answer the prompt). `--dry-run`, `--yes`, `--overwrite`. |

The `@storyblok` format, the workflow and every option are documented in the wiki: [Generating the Component Schema](https://github.com/windandkite/storyblok/wiki/Generating-The-Component-Schema).

Common options: `--store` / `-s` (store view ID or code, whose theme is used; defaults to the default store view), `--path` (Storyblok CLI base directory, default `.storyblok`) and `--space` (space ID). `generate` also has `--no-ide-helper` and `--force`. The commands work with Storyblok CLI v4.

## License

[Mozilla Public License 2.0](LICENSE). Changes to these files must be shared under the same license when distributed; modules that only build on them can use any license. Provided without warranty.
