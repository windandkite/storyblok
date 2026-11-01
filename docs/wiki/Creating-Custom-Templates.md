This guide delves into how to create and structure your custom `.phtml` templates in Magento 2 to render content fetched from Storyblok. The `WindAndKite_Storyblok` module acts as a highly flexible, unopinionated bridge that delivers Storyblok content payloads straight into your Magento theme layers.

## 🎨 The Templating Philosophy: Your Canvas
A core design principle of `WindAndKite_Storyblok` is to provide full layout control to developers. The module fetches raw Storyblok data and forwards it to Magento's native view fallback engine, allowing you to build pixel-perfect designs without being constrained by rigid pre-built HTML footprints.

The module provides foundational Magento Blocks to handle data distribution:
- `WindAndKite\Storyblok\Block\Story`: The main block responsible for fetching and holding full Storyblok story data.
- `WindAndKite\Storyblok\Block\Block`: Used for rendering individual nested components (called "bloks") within a layout.
- `WindAndKite\Storyblok\Block\StoryList`: Extends Story and is designed for displaying collections of stories, such as blog archives.

These blocks utilize Magento's view engine to locate relative `.phtml` templates. Your task is to create templates that correspond to your Storyblok component identifiers.

## 🏗️ The Templating Structure: How it Works
The module maps your Storyblok content types and components ("bloks") to relative `.phtml` template file paths. This structure allows the layout framework to dynamically seek overrides across themes and companion extensions seamlessly.

The template path resolution follows this relative pattern:

```text
[template_dir]/[component_name].phtml
```

Where:
- `[template_dir]` is determined by the Magento Block configuration (`block` or `story`).
- `[component_name]` is derived from the Storyblok component's technical identifier field (e.g., `page`, `hero_slider`). The engine automatically converts this string to lowercase and snake_case.

**Module's Core Blocks and Their Template Directories:**
The module provides key Magento Blocks, each pre-configured to look for templates in specific directories:
- `WindAndKite\Storyblok\Block\Story`:
    - Responsible for rendering a full Storyblok "Story" (typically a page).
    - Its `TEMPLATE_DIR` is set to `'story'`.
    - Thus, a Storyblok "Page" content type maps to the relative path `story/page.phtml`.
- `WindAndKite\Storyblok\Block\Block`:
    - Responsible for rendering individual components within a grid or Rich Text field.
    - Its `TEMPLATE_DIR` is set to `'block'`.
    - A Storyblok component named `HeroSlider` maps directly to the relative path `block/hero_slider.phtml`.

---

## 🏗️ Creating Compatibility Modules (Global Fallback Pattern)

Rather than forcing users to place component templates exclusively within their active theme, `WindAndKite_Storyblok` supports an automatic **Compatibility Module Pattern** based on Magento's layout engine fallback rules. This allows separate custom extensions to bundle out-of-the-box block components without manual file duplication or custom layout XML declarations.

### 1. Registering your Compatibility Module
Inside your companion module's `etc/di.xml`, add your module name as a string element into the simple `compatModules` argument array targeting `WindAndKite`Storyblok`Model`CompatModuleRegistry`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="WindAndKite\Storyblok\Model\CompatModuleRegistry">
        <arguments>
            <argument name="compatModules" xsi:type="array">
                <item name="mycompany_storyblok_additions" xsi:type="string">WindAndKite_StoryblokAdditions</item>
            </argument>
        </arguments>
    </type>
</config>
```

### 2. Organizing Templates
Once registered, you can build templates right inside your extension view tree following native fallback rules. For a Storyblok component identified as `hero_slider`, create your rendering template under:

`app/code/WindAndKite/StoryblokAdditions/view/frontend/templates/block/hero_slider.phtml`

Magento's engine will automatically prioritize files located in your compatibility module over the core module's stock templates.

### ⚠️ Crucial Note on Theme Overrides for Compatibility Templates

If a frontend developer wants to customize or override a template that is being supplied by an external compatibility module, they might instinctively try to copy the file to their theme using the compatibility module's name:

❌ **Incorrect Path (Will be ignored by Magento):**
`app/design/frontend/[Vendor]/[Theme]/WindAndKite_StoryblokAdditions/templates/block/hero_slider.phtml`

**Why this happens:** Even though the template file is supplied by a companion module, the underlying block instance rendering the component is still a core `WindAndKite\Storyblok\Block\Block` class. Magento's layout engine compiles template fallbacks using the block's native module context (`WindAndKite_Storyblok`), meaning it will completely skip theme directories named after companion extensions.

To successfully override any compatibility module template, developers must **always use the core module's theme directory**:

✅ **Correct Path:**
`app/design/frontend/[Vendor]/[Theme]/WindAndKite_Storyblok/templates/block/hero_slider.phtml`

Because the theme's core directory retains the absolute highest rendering priority in the fallback array, dropping the file here safely intercepts and overrides the template regardless of whether it originated from the core footprint or a compatibility package.

---

## 📄 Page-Level Templates (Stories)
When the custom router matches a Storyblok story, it dispatches the request to the `storyblok/page/view` action, which uses a layout handle to render the main Storyblok content.

The primary layout file for Storyblok pages is `view/frontend/layout/storyblok_page_view.xml`. This XML file references the `WindAndKite`Storyblok`Block`Story` block and assigns a default template:

```xml
<block name="storyblok.content"
       class="WindAndKite`Storyblok`Block`Story"
       template="story/page.phtml">
</block>
```

This means your main Storyblok page content will typically be rendered by `story/page.phtml`.

`story/page.phtml` (Example from module's default):

```php
<?php
/** @var `WindAndKite`Storyblok`Block`Story $block */
declare(strict_types=1);
?>
<?= /** @noEscape */ $block->getContentHtml() ?>
<?php if ($storyblokList = $block->getChildBlock('storyblok.list')): ?>
    <?= /** @noEscape */ $storyblokList->setStory($block->getStory())->toHtml() ?>
<?php endif ?>
```

In this example, `$block->getContentHtml()` is the crucial method that iterates through the main content blocks of your Storyblok page and renders each individual component using its corresponding relative template path.

---

## 🧱 Component-Level Templates (Bloks)
Storyblok content is built using a component-based approach, where a "Story" is composed of multiple nested "Bloks" (components). For each Storyblok component you define (e.g., `Hero`, `RichText`, `ImageGallery`), you will need to create a corresponding `.phtml` template file.

These templates are rendered by the `WindAndKite`Storyblok`Block`Block` class, meaning they reside in the block template directory.

**Example: Creating a Hero component template**

If you have a Storyblok component named `Hero`, you would create a template file in your theme (or compatibility module):
`app/design/frontend/[Your_Vendor]/[Your_Theme]/WindAndKite_Storyblok/templates/block/hero.phtml`

Inside `hero.phtml`:
```php
<?php
/** @var `WindAndKite`Storyblok`Block`Block $block */
declare(strict_types=1);

// Access Storyblok data passed to this block
$blokData = $block->getBlock()->getData(); // Get the raw data for this component
$title = $block->getTitle(); // Example of magic method access for a 'title' field
$description = $block->getDescription();
$image = $block->getImageAsset(); // For asset fields
?>

<div class="storyblok-hero">
    <?php if ($image): ?>
        <img src="<?= $block->escapeUrl($image->getUrl()) ?>" alt="<?= $block->escapeHtml($image->getAlt()) ?>" />
    <?php endif; ?>
    <h1><?= $block->escapeHtml($title) ?></h1>
    <p><?= $block->escapeHtml($description) ?></p>
</div>
```

**Key takeaways for component templates:**
- **Single Root Element (Crucial for Visual Editor):** Each `.phtml` template for a Storyblok component must have a single, outermost HTML element. If your template needs to render multiple elements, they should all be wrapped inside one parent container (e.g., a `<div>`). This is a critical requirement for the Storyblok Visual Editor's Bridge to correctly identify and update individual blocks when content is edited. The module's `Block::_toHtml()` method automatically inserts the necessary `_editable` HTML comments or attributes, which relies heavily on this single root element structure.
- **`$block` context:** Within these templates, the `$block` variable refers to an instance of `WindAndKite`Storyblok`Block`Block`.
- **Accessing Data:** You can access the Storyblok component's fields via magic methods (e.g., `$block->getTitle()`) or by directly getting the raw data object (`$block->getBlock()->getData()`).
- **Accessing Rendered HTML for Rich Text/Nested Blocks:** For fields that contain rich text or nested Storyblok blocks, you can use a magic getter with the `Html` suffix (e.g., `$block->getDescriptionHtml()`). This will automatically pass the field's content through the module's rendering service (`FieldRenderer`) to convert it into HTML, making it ready for direct output in your template.
- **Nesting Components:** If your Storyblok components contain nested bloks (e.g., a "Section" component containing multiple elements), you will use the magic `getFieldNameHtml()` method on the parent block. This method leverages the `FieldRenderer` to iterate and render each child component using its corresponding template.

---

## 📐 Declaring the Schema

A blok template can also declare the Storyblok component it renders, in an `@storyblok` docblock:

```php
/**
 * @storyblok {
 *   "fields": {
 *     "title": {"type": "string", "required": true},
 *     "image": {"type": "asset"}
 *   }
 * }
 */
```

With the [`windandkite/module-storyblok-schema`](https://github.com/windandkite/module-storyblok-schema) package, these generate the space's component schema, an IDE helper for each blok's getters, and checks that the space and templates agree. See [Generating the Component Schema](Generating-The-Component-Schema).

---

## 🛡️ Fallback Templates
The module includes fallback templates to provide a graceful degradation when a specific Storyblok component's template cannot be located. These are primarily for development and debugging, ensuring you always see descriptive diagnostic markers rather than a blank page.
- `story/fallback.phtml`: Used when a specific template for a Storyblok `Story` (page) cannot be found.
- `block/fallback.phtml`: Used when a specific template for a Storyblok `Block` (component) cannot be found.

These fallbacks will display an inline message indicating the missing template name and dump the raw context payload data, allowing you to quickly spot missing filenames during build processes.

---

## ➡️ What's Next
Now that you understand the templating patterns, continue exploring:

- [**Generating the Component Schema**](Generating-The-Component-Schema)
    - Keep the space's component schema in step with your templates.
- [**Accessing Storyblok Data**](Accessing-Storyblok-Data)
    - Learn the precise methods for retrieving fields, nested components, and assets from the Storyblok data within your Magento templates.
- [**Custom Block Hydrators**](Custom-Block-Hydrators)
    - Learn how to use programmatic Hydrators to inject dynamic frontend settings or frontend-specific asset dependencies before components evaluate to HTML.
- [**Working with the Storyblok Visual Editor**](Working-With-Storyblok-Visual_editor)
    - Integrate your Magento theme with Storyblok's real-time visual editor for an enhanced content creation experience.