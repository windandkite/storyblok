The core module renders whatever templates you write. A **component library** is a companion module with ready-made blok templates and their Storyblok schemas, so a store can build pages without writing them first.

## 📦 The Packages

| Package | Module | What it is |
|---|---|---|
| `windandkite/module-storyblok-hyva-components` | `WindAndKite_StoryblokHyvaComponents` | A library of bloks for Hyvä themes: a responsive page grid with full-bleed sections, and content, media, interactive and commerce bloks. Install this one. |
| `windandkite/module-storyblok-components-common` | `WindAndKite_StoryblokComponentsCommon` | Theme-agnostic code shared by the libraries: the `product`, `product_list` and `reference` hydrators, view models and translations. Installed with a library, not on its own. |

```bash
composer require windandkite/module-storyblok-hyva-components
bin/magento module:enable WindAndKite_StoryblokComponentsCommon WindAndKite_StoryblokHyvaComponents
```

The [Hyvä library's README](https://github.com/windandkite/module-storyblok-hyva-components#readme) covers setup, every blok, overriding templates and the Content Security Policy.

---

## 🧱 How a Library Plugs In

A library uses the core module's extension points, so it never changes how core renders:
- **Templates:** it registers itself as a [compatibility module](Creating-Custom-Templates), so its `block/<component>.phtml` templates render when the theme has none.
- **Schemas:** each template declares its blok in an `@storyblok` docblock, so [`storyblok:schema:generate`](Generating-The-Component-Schema) pushes the library's bloks to a space.
- **Data:** [hydrators](Block-Hydration) registered by component name load what templates need, such as products for `product_list`.

---

## 🎨 Libraries for Other Frontends

Component names, fields and hydrators are shared: a library for another frontend (Luma, Breeze...) depends on `windandkite/module-storyblok-components-common` and only adds templates for the same components. Its hydrators then apply to that library's templates too, and content built in Storyblok renders with whichever library the store installs.

Only theme-agnostic code goes in the common package: anything that depends on a frontend (Hyvä view models, Alpine, Tailwind) stays in that frontend's library.

---

## ➡️ What's Next

- [**Creating Custom Templates**](Creating-Custom-Templates)
    - Override a library's templates in your theme.
- [**Generating the Component Schema**](Generating-The-Component-Schema)
    - Push a library's bloks to your space.
