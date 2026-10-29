# WindAndKite_StoryblokComponentsCommon

> Installed by a component library such as [`windandkite/module-storyblok-hyva-components`](https://github.com/windandkite/module-storyblok-hyva-components), not on its own. Install the library instead.

Theme-agnostic code shared by the WindAndKite Storyblok component libraries, so a library for another frontend (Luma, Breeze...) only adds templates:

- **Hydrators**, registered by component name:
  - `product` loads the chosen product;
  - `product_list` loads products from a category, a SKU list, newest or recently viewed;
  - `reference` loads the referenced story.
- **View models:** `Embed` (sanitises embed code to a single `https:` iframe), `JsonLd` (structured data), `ReferenceStack` (stops a story referencing itself), `Table` and `Video`.
- **Translations** for the libraries' front-end strings (`i18n/en_US.csv`).

Component names are shared across libraries, so the hydrators apply to whichever library renders the blok.

## Testing

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/windandkite/module-storyblok-components-common/Test/Unit
```
