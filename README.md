# ![image logo](docs/favicon.svg) WindAndKite_Storyblok

Magento 2 Module for Storyblok Integration

## Why is it needed?

This Magento 2 Module seamlessly integrates your e-commerce platform with Storyblok, a powerful headless CMS, offering numerous benefits for content management and development workflows:

* **Decoupled Content Management:** Separate content creation in Storyblok from Magento's presentation layer, allowing independent work for content editors and flexible frontend development.
* **Enhanced Content Editor Experience:** Storyblok's visual editor provides real-time previews for intuitive and efficient content creation.
* **Omnichannel Content Delivery:** Enable content delivery to various channels (Magento storefront, mobile apps, other websites) from a single source of truth.
* **Improved Performance:** Headless architecture can lead to faster website performance by offloading content rendering from Magento.
* **Scalability and Flexibility:** Offers greater scalability and flexibility for content model updates and adoption of modern frontend technologies.
* **Future-Proof Architecture:** Decoupling content facilitates adaptation to future technological changes and digital experience evolution.

This module empowers businesses to create richer, more dynamic content experiences while improving development workflows and overall agility.

## Installation

This module can be installed via Composer from Packagist.

1.  **Add the module to your project:**
    ```bash
    composer require windandkite/module-storyblok
    ```

2.  **Enable the module:**
    ```bash
    bin/magento module:enable WindAndKite_Storyblok
    bin/magento setup:upgrade
    ```
    (or simply run `bin/magento setup:upgrade` which enables new modules automatically.)

## Configuration & Usage

All detailed setup, configuration, user guides, and developer guides are available in the module's Wiki.

The Wiki covers:
* **Quick Start**
* **Installation Guide**
* **Configuration Guide**
* **SEO App Integration**
* **Content Routing**
* **Creating Custom Templates**
* **Accessing Storyblok Data**
* **Working with Visual Editor**
* **Displaying Story Lists**
* **Generating the Component Schema**
* **Troubleshooting Guide**

Please refer to the [Wiki](https://github.com/windandkite/storyblok/wiki) for comprehensive documentation.

## Storyblok Component Schema Commands

Blok templates can declare their Storyblok schema in an `@storyblok` docblock. The module collects these from every template a store's theme renders (the theme, compatibility modules and this module). It then generates the schema, content migrations and an IDE helper for the Storyblok CLI (v4), checks templates against a space, and imports a space's existing schemas into templates.

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

Common options: `--store` / `-s` (store view ID or code, whose theme is used; defaults to the default store view), `--path` (Storyblok CLI base directory, default `.storyblok`) and `--space` (space ID). `generate` also has `--no-ide-helper` and `--force`. The commands work with Storyblok CLI v4.

See [Generating the Component Schema](https://github.com/windandkite/storyblok/wiki/Generating-The-Component-Schema) for the docblock format, tabs and groups, renames, deploying and the checks.

## Contributions

We welcome contributions! Whether that be raising bugs, suggesting feature ideas or getting down and dirty with the code, head on over to the [Contributions Guide](https://github.com/windandkite/storyblok/wiki/Contribution-Guide) in the Wiki to see how you can get involved.
