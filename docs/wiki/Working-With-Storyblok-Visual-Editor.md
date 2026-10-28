The Storyblok Visual Editor is a powerful tool that allows content creators to manage and preview content in real-time, directly on your Magento storefront. This guide will explain how your `WindAndKite_Storyblok` module integrates with this editor and what you, as a developer, need to know to ensure a seamless experience.

## 👁️ How the Visual Editor Integrates
When a content editor opens a page in the Storyblok Visual Editor, Storyblok injects a special query parameter, _`storyblok`, into the URL (e.g., `{{YOUR_MAGENTO_URL}}/content-page?_storyblok=...`).

Our module's custom router (`WindAndKite\Storyblok\Controller\Router`) detects this _`storyblok` parameter. When present, it automatically triggers the fetching of the draft version of the Storyblok content, bypassing the cached published version. This allows content editors to see their changes instantly as they type or arrange components.

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

## ⚠️ Troubleshooting the Visual Editor
If you're experiencing issues with the Storyblok Visual Editor:
- **Cache:** Ensure your Magento cache is cleared after any configuration changes (`php bin/magento cache:clean`).
- **API Token:** Verify your Storyblok API Token is correct and is the Preview token.
- **Storyblok Location:** Confirm that the "Location" URL in your Storyblok space settings exactly matches your Magento storefront URL.
- **CORS Issues:** If you see "Cross-Origin" errors in your browser console, ensure your web server configuration (Apache/Nginx) allows embedding your Magento site in an iframe from `app.storyblok.com`.
- **`_editable` Comment:** Inspect your rendered HTML source to ensure the `_editable` HTML comments are correctly appearing before your component's root elements.
- **Webhook Secret (for Cache Invalidation):** While not required for the Visual Editor's real-time preview, configuring the Webhook Secret in Magento and Storyblok is crucial for ensuring that published content updates are quickly reflected on your live Magento storefront.
- **Developer Mode (for Theme Development):** Remember that the Magento "Developer Mode" setting in Stores > Configuration > WindAndKite > Storyblok > General Settings (when set to "Yes") forces the module to always fetch the latest draft content. While not essential for the Visual Editor itself, it's beneficial for developers working on theme templates to always see the most up-to-date content without needing to open the Visual Editor.

## ➡️ What's Next
Now that you know how to integrate with the Visual Editor, you can move on to other aspects of rendering Storyblok content:
- [Displaying Story Lists](Displaying-Story-Lists)
    - Detailed instructions on how to use the StoryList block to create paginated content archives.
