The Storyblok Visual Editor is a powerful tool that allows content creators to manage and preview content in real-time, directly on your Magento storefront. This guide will explain how your `WindAndKite_Storyblok` module integrates with this editor and what you, as a developer, need to know to ensure a seamless experience.

## 👁️ How the Visual Editor Integrates
When a content editor opens a page in the Storyblok Visual Editor, Storyblok adds query parameters to the URL, including a `_storyblok_tk` token signed with your space's preview token (e.g., `{{YOUR_MAGENTO_URL}}/content-page?_storyblok=...&_storyblok_tk[token]=...`).

The module checks that token. When it is valid, the page:
- shows the draft version of the content, so editors see their changes as they type or arrange components;
- is not stored in the page cache, and is sent with `X-Robots-Tag: noindex, nofollow`;
- is sent without the `X-Frame-Options` header, so the Visual Editor can show it in its iframe.

Draft content can't be requested any other way, apart from Developer Mode and [share links](#-sharing-drafts). Adding `_storyblok` or `_storyblok_version=draft` to a URL yourself shows the published version.

## ⚙️ Essential Prerequisites
To ensure the Visual Editor functions correctly, please ensure the following:

1. Configure Storyblok Space Location:
    - In your Storyblok space, go to Settings > General.
    - Under "Location", set the URL of your Magento 2 storefront (e.g., `https://your-magento-domain.com/`). This tells Storyblok where to load the visual editor iframe.

## ✨ Enabling Component Highlighting and Editing
For the best editor experience, you'll want the visual editor to highlight individual Storyblok components (bloks) on your Magento page and allow editors to click them to edit. This is achieved by adding a special `_editable` HTML comment directly before the root HTML element of each of your component templates.

The `WindAndKite\Storyblok\Block\Block` class (which renders your component templates) automatically generates this comment as a prefix to the template's output. Therefore, you do not need to make any explicit changes to your block templates for the Visual Editor to identify and highlight blocks.

Example: Enhancing your `block/hero.phtml` for Visual Editor integration

Recall our `hero.phtml` example. The necessary `_editable` HTML comment will be automatically prefixed by the block before your template's output:

```PHP
<?php
/** @var \WindAndKite\Storyblok\Block\Block $block */
declare(strict_types=1);

$title = $block->getTitle();
$description = $block->getDescription();
$rawHeroImageData = $block->getHeroImage(); // Get raw image data
$assetViewModel = $block->getAssetViewModel();
$transformedImageUrl = $assetViewModel->transformImage($rawHeroImageData, ['width' => 600, 'height' => 400]);
$altText = $rawHeroImageData['alt'] ?? 'Hero Image';
?>

<div class="storyblok-hero"> <!-- The _editable HTML comment will be automatically prefixed before this div -->
    <?php if ($transformedImageUrl): ?>
        <img src="<?= $block->escapeUrl($transformedImageUrl) ?>" alt="<?= $block->escapeHtml($altText) ?>" loading="lazy" />
    <?php endif; ?>
    <h1><?= $block->escapeHtml($title) ?></h1>
    <p><?= $block->escapeHtml($description) ?></p>
    <?php // ... additional content for your Hero component ... ?>
</div>
```
**Explanation:**
- **Root Element Automatic `_editable` Comment:** The `WindAndKite\Storyblok\Block\Block` class automatically handles adding the necessary `_editable` HTML comment (e.g., `<!--#storyblok#{"name":"hero", ...}-->`) immediately before the _first_ direct child _HTML element_ of your template when it renders. This is how Storyblok identifies and highlights the entire component, making it clickable for editing. You do not need to explicitly add this comment to your component template.

## 🔗 Sharing Drafts
Editors can share a draft with people who don't have a Storyblok account, for example to get sign-off.

1. Open the page in the Visual Editor and save your changes (no need to publish).
2. Click "Copy share link" in the bottom-right corner of the preview. If the browser blocks clipboard access, the link is shown so you can copy it.
3. Send the link. Anyone with it sees the latest saved draft of that page.

Share links:
- only work for the page and store view they were created on;
- expire after the number of days set in General Settings > Draft Share Link Lifetime (default 7);
- can't be edited: changing the page or expiry makes the link show the published version instead;
- are never cached, and tell search engines not to index the page.

## ⚠️ Troubleshooting the Visual Editor
If you're experiencing issues with the Storyblok Visual Editor:
- **Cache:** Ensure your Magento cache is cleared after any configuration changes (`php bin/magento cache:clean`).
- **API Token:** Verify your Storyblok API Token is correct and is the Preview token.
- **Storyblok Location:** Confirm that the "Location" URL in your Storyblok space settings exactly matches your Magento storefront URL.
- **Page Blocked in the Iframe:** The module removes Magento's `X-Frame-Options` header for valid editor requests. If the browser still refuses to frame the page, check that your web server, CDN or another module doesn't add its own `X-Frame-Options` or `Content-Security-Policy: frame-ancestors` header.
- **`_editable` Comment:** Inspect your rendered HTML source to ensure the `_editable` HTML comments are correctly appearing before your component's root elements.
- **Webhook Secret (for Cache Invalidation):** While not required for the Visual Editor's real-time preview, configuring the Webhook Secret in Magento and Storyblok is crucial for ensuring that published content updates are quickly reflected on your live Magento storefront.
- **Developer Mode (for Theme Development):** The "Developer Mode" setting in Stores > Configuration > WindAndKite > Storyblok > General Settings shows draft content to every visitor and turns off page caching. It is useful on a local or development environment, but never enable it in production.

## ➡️ What's Next
Now that you know how to integrate with the Visual Editor, you can move on to other aspects of rendering Storyblok content:
- [Displaying Story Lists](Displaying-Story-Lists)
    - Detailed instructions on how to use the StoryList block to create paginated content archives.
