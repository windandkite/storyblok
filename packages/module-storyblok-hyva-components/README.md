# WindAndKite_StoryblokHyvaComponents

A library of Storyblok bloks for Hyvä themes, built on [WindAndKite_Storyblok](https://github.com/windandkite/module-storyblok). Its hydrators and view models are in [`windandkite/module-storyblok-components-common`](https://github.com/windandkite/module-storyblok-components-common), installed with it. It provides a responsive page grid with full-bleed sections, content, media, interactive and commerce bloks. Every template:

- has CSP-safe Alpine (no inline expressions, data read from `data-*` attributes);
- is Tailwind JIT-safe (all class maps are literal strings inside the template);
- defers third-party scripts (maps, video players) until they're needed.

Templates are deliberately unopinionated: they use Tailwind utilities and your theme's tokens, so a project re-skins them by overriding the template.

## Requirements

- Magento 2.4.6+ with a Hyvä theme (`hyva-themes/magento2-theme-module` 1.3+).
- `windandkite/module-storyblok` 2.2+.
- `windandkite/module-storyblok-schema` (development only) for the `storyblok:schema:*` commands used below, which push the bloks to Storyblok and generate IDE hints.
- Optional:
  - `Magento_Newsletter` for the `newsletter` blok;
  - `Magento_PageBuilder` for the default Google Maps API key used by `google_map`.

## Installation

```bash
composer require windandkite/module-storyblok-hyva-components
bin/magento module:enable WindAndKite_StoryblokComponentsCommon WindAndKite_StoryblokHyvaComponents
```

The module has no database schema or data patches, so `setup:upgrade` isn't needed. In production mode, run your usual `setup:di:compile` and static content deploy.

Then rebuild your theme's CSS, so Tailwind picks up the classes in this module's templates. The module registers itself with `hyva-themes.json` automatically:

```bash
bin/magento hyva:config:generate
cd app/design/frontend/<Vendor>/<theme>/web/tailwind && npm run build
```

### Push the bloks to Storyblok

The block library is generated from the `@storyblok` docblock at the top of each template, including the templates your theme overrides (see [Generating the Component Schema](https://github.com/windandkite/storyblok/wiki/Generating-The-Component-Schema)):

```bash
bin/magento storyblok:schema:generate --store=default
storyblok components push --from default --space <SPACE_ID>
```

Then, in Storyblok:

1. Open the `page` content type's `body` field and allow `section` (optionally only `section`, so every page is built on the grid).
2. Create a story `global/announcement-bar` with the `announcement_bar` content type if you use the announcement bar (see below).

`storyblok:schema:generate` also writes `.storyblok/ide-helper.php`, which gives templates typed getters (e.g. `$block->getSpanDesktop()`) through the `@var` hints already in each template.

> **Storefront homepage:** if a Storyblok story is your homepage, disable Magento's CMS page `home`. Magento renders its own homepage whenever that page is enabled.

## The grid

- **`section`** creates the page grid: 4 columns on mobile, 8 on tablet (`md`) and 12 on desktop (`lg`). Its inner width is boxed, narrow or full.
- **`column`** spans a number of tracks per breakpoint (`span_mobile`, `span_tablet`, `span_desktop`). It's a subgrid, so bloks nested inside a column stay aligned with the page grid.
- **Breakouts:** `full_bleed` (`full` / `left` / `right`) extends a section to the viewport edge.
  - `bleed_target: background` extends the section's background only.
  - `bleed_target: content` extends the edge column, e.g. an image running off the side of the page.
- A section with a breakout adds `sb-bleed`, and `body:has(.sb-bleed)` clips horizontal overflow, so a breakout never causes a horizontal scrollbar.
- **Anchors:** a section's `anchor_id` makes it a link target (`#delivery`). Set `--sb-anchor-offset` in your theme to clear a sticky header. The default is `6rem`.

## Bloks

| Folder | Bloks |
|---|---|
| Layout | `section`, `column` |
| Content | `title_section`, `rich_text`, `card`, `link`, `link_list`, `button`, `divider`, `table`, `usp_bar` / `usp_item`, `newsletter`, `reference` |
| Interactive | `details` / `details_item` (accordion), `tabs` / `tab_panel`, `modal` |
| Media | `image_caption`, `carousel`, `video`, `embed`, `google_map` / `map_marker` |
| Commerce | `product`, `product_grid`, `product_list` |
| Global (content type) | `announcement_bar` |

Notes:
- **`link` vs `button`:** `link` is navigation (`<a>`). `button` is an action (`<button>`) that dispatches a window event; a `modal` with a matching `anchor_id` opens on it.
- **`product` / `product_list`** render Hyvä's native product card, so prices, add to cart and swatches behave as on category pages.
  - `product_list` sources: a category, a SKU list (kept in the typed order), newest, or the visitor's recently viewed products.
  - Unavailable products (disabled, not visible, other website, out of stock unless *Display Out of Stock Products* is on) are skipped.
  - Product and category cache tags are added to the page, so it's purged when they change.
- **`reference`** renders another story in place (a shared USP bar, a promo). The referenced story's cache tag is added, so publishing it purges every page that uses it.
- **Above the fold:** `section` and `google_map` have an `above_the_fold` option that loads their image eagerly with `fetchpriority="high"` (and, for the map, loads it immediately instead of on scroll). Turn it on for the first section only.
- **Structured data:** `details` (FAQ), `video`, `product_grid` and `product_list` (item list) output JSON-LD.

### Announcement bar

`layout/default.xml` renders the story at `global/announcement-bar` above the header. Change the slug, or remove the block in projects that don't use it (a missing story costs one API lookup per uncached page):

```xml
<referenceBlock name="storyblok.announcement_bar">
    <arguments>
        <argument name="slug" xsi:type="string">site/banner</argument>
    </arguments>
</referenceBlock>
<!-- or -->
<referenceBlock name="storyblok.announcement_bar" remove="true"/>
```

## Overriding templates

Copy a template into your theme under the **core** module's name:

```
app/design/frontend/<Vendor>/<theme>/WindAndKite_Storyblok/templates/block/section.phtml
```

Bloks render through `WindAndKite_Storyblok`, so an override under `WindAndKite_StoryblokHyvaComponents/` is currently ignored.

- **Keep the `@storyblok` docblock** and edit it when you add or rename fields. Re-run `storyblok:schema:generate` and push; renames are migrated with `renamed_from` (see [Generating the Component Schema](https://github.com/windandkite/storyblok/wiki/Generating-The-Component-Schema)).
- **Keep one root element:** the Visual Editor attributes are added to the first tag.
- **Keep class maps literal:** don't build class names from strings (`'md:col-span-' . $n`), or Tailwind won't generate them.
- **Adding a blok:** a new `block/<component>.phtml` with a `@storyblok` docblock in your theme is picked up by `storyblok:schema:generate`.

## Content Security Policy

`etc/csp_whitelist.xml` allows the hosts these bloks use:

| Blok | Hosts |
|---|---|
| `google_map` | `maps.googleapis.com`, `maps.gstatic.com`, `fonts.googleapis.com`, `fonts.gstatic.com` |
| `video` | `www.youtube-nocookie.com`, `player.vimeo.com`, `i.ytimg.com`, `i.vimeocdn.com` |
| Images | `a.storyblok.com` |

`embed` sanitises the editor's code to a single `https:` iframe, but its host varies by project. Add those hosts to `frame-src` in your project's own `csp_whitelist.xml`.

## Translations

Front-end strings are in `windandkite/module-storyblok-components-common`'s `i18n/en_US.csv`. Add a translation to your theme's or language pack's CSV as usual.

## Testing

The tests are in the common package:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/windandkite/module-storyblok-components-common/Test/Unit
```
