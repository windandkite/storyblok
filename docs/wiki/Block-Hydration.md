This guide delves into how to create, register, and leverage custom Block Hydrators in Magento 2 when using the `WindAndKite_Storyblok` module. This feature provides advanced architectural control, allowing developers to inject dynamic context, assets, or layout configurations into components ahead of HTML generation.

## 🎨 The Hydration Philosophy: Extending the Bridge
A core design principle of `WindAndKite_Storyblok` is to maintain an unopinionated bridge between raw Storyblok API payloads and your Magento front-end. By default, the module maps components directly to standard `.phtml` layout templates. 

However, specialized front-end ecosystems like **Hyvä Themes** allow developers to register JavaScript dependencies for a block. Because Storyblok components are generated entirely dynamically at runtime within the layout rendering pipeline, developers cannot target individual content types using traditional, static Magento layout XML.

**Block Hydrators** bridge this gap. They allow you to register programmatic interceptors tailored to specific Storyblok component types. Instead of treating a block as a passive data container, a Hydrator intercepts the Magento Block instance immediately after instantiation, giving you a structured environment to "hydrate" it with custom dependencies before `toHtml()` execution.

## 🏗️ The Hydration Architecture: How it Works
The module processes incoming component structures sequentially during the rendering lifecycle. When the rendering pipeline isolates a block payload, it relies on two foundational classes to manage modifications:

- `WindAndKite`Storyblok`Service`BlockHydrationManager`: The registry class that aggregates custom hydrators injected via dependency injection (`di.xml`) and returns them filtered by component context.
- `WindAndKite`Storyblok`Api`BlockHydratorInterface`: The standard contract that all custom block hydrators must implement.

When `FieldRenderer` parses an individual component, the sequence executes as follows:
1. A standard `WindAndKite`Storyblok`Block`Block` instance is created and populated with its native Storyblok content data.
2. The `FieldRenderer` passes the component's unique name to the `BlockHydrationManager`.
3. The manager filters down any registered hydrators matching that component.
4. Each matching hydrator modifies or builds upon the block instance through a chainable `populate()` routine.
5. The block instance is returned to the renderer and proceeds to generate HTML output.

---

## 🧱 1. Creating a Custom Hydrator
To build a custom interceptor, create a class within your custom module that implements \WindAndKite\Storyblok\Api\BlockHydratorInterface\.

In this example, we will build a hydrator designed for a `hero_slider` component. This hydrator will dynamically assign an Alpine.js operational array and load a target layout handle required by Hyvä for specialized JavaScript caching:

```php
<?php

declare(strict_types=1);

namespace MyCompany`StoryblokHyva`Model`Hydrator;

use WindAndKite`Storyblok`Api`BlockHydratorInterface;
use WindAndKite`Storyblok`Block`Block;

/**
 * Class HeroSliderHydrator
 *
 * Programmatically injects layout requirements and asset payloads
 * into the Hero Slider block prior to HTML evaluation.
 */
class HeroSliderHydrator implements BlockHydratorInterface
{
    /**
     * Enrich or modify the block instance data/dependencies before layout generation.
     *
     * @param Block $blockInstance
     * @return Block
     */
    public function populate(Block $blockInstance): Block
    {
        // 1. Inject custom front-end configuration data directly into the block instance
        $blockInstance->setData('slider_config', [
            'autoplay' => true,
            'interval' => 5000,
            'animation' => 'fade'
        ]);

        // 2. Register JavaScript dependencies via Hyvä's block data properties
        $blockInstance->setData('hyva_js_template_dependencies', 'MyCompany_StoryblokHyva::js/slider.phtml');

        return $blockInstance;
    }
}
```

---

## 🛠️ 2. Registering your Hydrator via Dependency Injection
Hydrators are mapped explicitly to your content types using Magento's dependency injection system. This preserves the open-closed principle, allowing you to attach structural modifications without rewriting core rendering services.

Configure your layout injections inside your module's `etc/di.xml` file. The entry key designated under the `hydrators` argument array **must match the technical identifier string** of your Storyblok component exactly.

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="WindAndKite`Storyblok`Service`BlockHydrationManager">
        <arguments>
            <argument name="hydrators" xsi:type="array">
                <item name="hero_slider" xsi:type="array">
                    <item name="hyva_slider_asset_hydrator" xsi:type="object">MyCompany\StoryblokHyva\Model\Hydrator\HeroSliderHydrator</item>
                </item>
            </argument>
        </arguments>
    </type>
</config>
```

> 💡 **Tip:** You can assign multiple distinct hydrator instances to a single Storyblok component identifier. The `BlockHydrationManager` will parse and process them sequentially in the order they are ordered inside the dependency array.

---

## 📺 3. Consuming Hydrated Context within Templates
Once a hydrator alters the state of a block instance, any keys supplied using `$blockInstance->setData()` become smoothly accessible inside your theme's corresponding `.phtml` template file.

`app/design/frontend/[Vendor]/[Theme]/WindAndKite_Storyblok/templates/block/hero_slider.phtml`:

```php
<?php
/** @var `WindAndKite`Storyblok`Block`Block $block */
declare(strict_types=1);

// Retrieve custom layout variables appended programmatically by your Hydrator
$sliderConfig = $block->getData('slider_config'); 

// Retrieve standard editorial entries managed inside the CMS UI
$storyblokBlock = $block->getBlock(); 
?>

<div x-data="initSlider(<?= $block->escapeHtmlAttr(json_encode($sliderConfig)) ?>)" class="hero-slider-wrapper">
    <?php foreach ($storyblokBlock->getSlides() as $slide): ?>
        <div class="slide-item">
            <h2><?= $block->escapeHtml($slide['title']) ?></h2>
        </div>
    <?php endforeach; ?>
</div>
```

---

## 🔄 Rich Text Processing & Context Preservation
Hydrators interact uniformly across your component footprint, regardless of where they sit in the layout hierarchy. The system executes block hydration identically for top-level component grids and deep-nested elements parsed inside a **Rich Text field** via the Tiptap editor extension.

If an advanced component hydrator requires parent page traits, language parameters, or global configuration settings to customize its behavior, you can safely extract the active `Story` model wrapper directly from the block environment:

```php
/** @var `WindAndKite`Storyblok`Api`Data`StoryInterface|null $story */
$story = $blockInstance->getData('story');

if ($story) {
    // Extract metadata tags or categories assigned to the host page
    $pageTags = $story->getTagList();
    
    // Adapt block properties or layout behaviors based on page context
}
```

---

## ➡️ What's Next
Now that you can programmatically hydrate dynamic components, continue customizing your implementation:

- [**Creating Custom Templates**](Creating-Custom-Templates)
    - Review the baseline rules for component-to-template file mappings and single root element requirements.
- [**Accessing Storyblok Data**](Accessing-Storyblok-Data)
    - Learn how to unpack core variables, extract complex media assets, or process magic getters inside your template layers.
- [**Working with the Storyblok Visual Editor**](Working-With-Storyblok-Visual_editor)
    - Learn how editable markup wrappers attach safely to frontend templates to ensure fluid real-time visual authoring previews.