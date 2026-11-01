# Upgrading to 3.0

3.0 hardens draft access, webhooks and caching, and adds view models, rich text images and plugin field hydration. Most sites only need to update the webhook URL in Storyblok. Read the sections below before upgrading a site with custom code.

```bash
composer require windandkite/module-storyblok:^3.0
bin/magento setup:upgrade
```

## Steps for every site

1. **Update the webhook in Storyblok.** Webhooks are now rejected unless they prove they come from Storyblok. After `setup:upgrade`, open **Stores > Configuration > WindAndKite > Storyblok** and copy the webhook URL shown under Webhook Secret: it includes the secret as its `key` parameter. In Storyblok, go to **Settings > Webhooks** and replace the old URL with it. If your plan lets you set a webhook secret, paste the secret there too, so Storyblok signs each request. See the [Configuration Guide](Configuration-Guide).
   - A secret is generated on install unless one is already set. An existing plain text secret is encrypted.
2. **Set the Space Region** if your space isn't in Europe (US, CA, AP or CN). Non-EU spaces didn't work before 3.0.
3. **Share drafts with share links.** `?_storyblok_version=draft` no longer shows draft content. Drafts are served to Visual Editor sessions, in developer mode, or through a signed share link from the editor's "Copy share link" button. Share links expire after 7 days by default.

## Changes that can affect custom code

- **`FieldRendererInterface::createBlockInstance()`:** the interface gains this method. The default `FieldRenderer` already had it, so only custom implementations of the interface need to add it.
- **Blok fields named `story` or `slug`:** they no longer replace the block's story. Read the raw field from the blok, e.g. `$block->getBlock()->getData('story')`.
- **Data set on a block wins:** a value a hydrator sets on the block, even `null`, is returned instead of the stored field, including for indexed reads such as `getData('clip', 'id')`.
- **API failures:** errors other than "not found" throw `StoryblokUnavailableException` and mark the page uncacheable, instead of rendering and caching a 404.
- **Missing templates:** "Missing Template" and the blok's JSON only show in the Visual Editor or developer mode.
- **Content Security Policy:** `script-src` and `frame-ancestors` allow `app.storyblok.com` only, not `*.storyblok.com`. Add any other Storyblok hosts your theme relies on to your own CSP whitelist.
- **Rich text images** render through the new `TipTap\Image` override, capped at 1200px with `srcset` and lazy loading. Override it to change that.

## Packages and licence

- **One repository, several packages.** The module is developed in [windandkite/storyblok](https://github.com/windandkite/storyblok) and published to read-only package repositories. `windandkite/module-storyblok` installs as before.
- **Schema commands** (`storyblok:schema:*`) are a separate package: `composer require --dev windandkite/module-storyblok-schema`. See [Generating the Component Schema](Generating-The-Component-Schema).
- **Licence:** 3.0 is licensed under the [Mozilla Public License 2.0](https://github.com/windandkite/storyblok/blob/master/LICENSE), replacing GPL-3.0. Changes to the module's own files must stay open source; your themes and modules that use it can have any licence.
