This guide delves into how to create, register, and leverage custom Block Hydrators in Magento 2 when using the `WindAndKite_Storyblok` module. This feature provides advanced architectural control, allowing developers to inject dynamic context, assets, or layout configurations into components ahead of HTML generation.

## 🎨 The Hydration Philosophy: Extending the Bridge
A core design principle of `WindAndKite_Storyblok` is to maintain an unopinionated bridge between raw Storyblok API payloads and your Magento front-end. By default, the module maps components directly to standard `.phtml` layout templates. 

However, specialized front-end ecosystems like **Hyvä Themes** allow developers to register JavaScript dependencies for a block. Because Storyblok components are generated entirely dynamically at runtime within the layout rendering pipeline, developers cannot target individual content types using traditional, static Magento layout XML.

**Block Hydrators** bridge this gap. They allow you to register programmatic interceptors tailored to specific Storyblok component types. Instead of treating a block as a passive data container, a Hydrator intercepts the Magento Block instance immediately after instantiation, giving you a structured environment to "hydrate" it with custom dependencies before `toHtml()` execution.

## 🏗️ The Hydration Architecture: How it Works
The module processes incoming component structures sequentially during the rendering lifecycle. When the rendering pipeline isolates a block payload, it relies on two foundational classes to manage modifications:

- `WindAndKite\Storyblok\Service\BlockHydrationManager`: The registry class that aggregates custom hydrators injected via dependency injection (`di.xml`) and returns them filtered by component context.
- `WindAndKite\Storyblok\Api\BlockHydratorInterface`: The standard contract that all custom block hydrators must implement.

When `FieldRenderer` parses an individual component, the sequence executes as follows:
1. A standard `WindAndKite\Storyblok\Block\Block` instance is created and populated with its native Storyblok content data.
2. The `FieldRenderer` passes the component's unique name to the `BlockHydrationManager`.
3. The manager returns the hydrators registered for every component (under `*`), followed by those registered for that component.
4. Each matching hydrator modifies or builds upon the block instance through a chainable `populate()` routine.
5. The block instance is returned to the renderer and proceeds to generate HTML output.

The story's content type (e.g. `page`) is hydrated the same way before its template renders, so hydrators registered for a content type, or under `*`, apply to it too.

---

## 🧱 1. Creating a Custom Hydrator
To build a custom interceptor, create a class within your custom module that implements \WindAndKite\Storyblok\Api\BlockHydratorInterface\.

In this example, we will build a hydrator designed for a `hero_slider` component. This hydrator will dynamically assign an Alpine.js operational array and load a target layout handle required by Hyvä for specialized JavaScript caching:

```php
<?php

declare(strict_types=1);

namespace MyCompany\StoryblokHyva\Model\Hydrator;

use WindAndKite\Storyblok\Api\BlockHydratorInterface;
use WindAndKite\Storyblok\Block\Block;

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
    <type name="WindAndKite\Storyblok\Service\BlockHydrationManager">
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

To run a hydrator for **every** component, register it under `*`. These run before the component's own hydrators:

```xml
<argument name="hydrators" xsi:type="array">
    <item name="*" xsi:type="array">
        <item name="my_global_hydrator" xsi:type="object">MyCompany\StoryblokHyva\Model\Hydrator\GlobalHydrator</item>
    </item>
</argument>
```

---

## 📺 3. Consuming Hydrated Context within Templates
Once a hydrator alters the state of a block instance, any keys supplied using `$blockInstance->setData()` become smoothly accessible inside your theme's corresponding `.phtml` template file.

`app/design/frontend/[Vendor]/[Theme]/WindAndKite_Storyblok/templates/block/hero_slider.phtml`:

```php
<?php
/** @var \WindAndKite\Storyblok\Block\Block $block */
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

## 🔌 Hydrating Plugin Field Values

Custom field plugins (a video picker, a product picker, a colour picker...) store their own data. Storyblok saves the plugin's name with every value, in a `plugin` key:

```json
"clip": {"plugin": "storyblok-vimeo", "id": "76979871"}
```

A **plugin value hydrator** turns that raw value into whatever templates should get: a value object, a product collection, a category. It's registered by plugin name and applies to that plugin's fields in every blok, so the blok's components and field names don't matter, and third-party plugins work too.

### 1. Create the hydrator

Implement `\WindAndKite\Storyblok\Api\PluginValueHydratorInterface`. `hydrate()` gets the stored value and the blok being rendered, and returns the field's new value:

```php
<?php

declare(strict_types=1);

namespace MyCompany\StoryblokVideo\Model\Hydrator;

use MyCompany\StoryblokVideo\Model\Clip;
use MyCompany\StoryblokVideo\Model\ClipFactory;
use WindAndKite\Storyblok\Api\PluginValueHydratorInterface;
use WindAndKite\Storyblok\Block\Block;

class VimeoClip implements PluginValueHydratorInterface
{
    public function __construct(
        private readonly ClipFactory $clipFactory,
    ) {}

    public function hydrate(array $value, Block $blockInstance): Clip
    {
        return $this->clipFactory->create(['id' => (string)($value['id'] ?? '')]);
    }
}
```

### 2. Register it by plugin name

The item name is the plugin's name, as stored in the value's `plugin` key (the field type name in the plugin's settings):

```xml
<type name="WindAndKite\Storyblok\Model\Hydrator\PluginValues">
    <arguments>
        <argument name="hydrators" xsi:type="array">
            <item name="storyblok-vimeo" xsi:type="object">MyCompany\StoryblokVideo\Model\Hydrator\VimeoClip</item>
        </argument>
    </arguments>
</type>
```

### 3. Use it in templates

The field's getter returns the hydrated value. The raw value is still on the Storyblok block:

```php
$clip = $block->getClip();                       // Clip, from the hydrator
$raw = $block->getBlock()->getData('clip');      // ['plugin' => 'storyblok-vimeo', 'id' => '76979871']
```

Things to know:
- Only a blok's own fields are hydrated, not plugin values nested inside other values (such as rich text).
- Plugin values without a registered hydrator are left as they are.
- Whatever `hydrate()` returns is what the getter returns, including `null` (for example, a picker whose product no longer exists).
- A field named like data the block instance already holds (`type`, `story`, `block`...) keeps its raw value, so hydrating it can't break rendering.
- Hydration runs when the blok is created, before its template renders, so keep `hydrate()` cheap or defer expensive work (for example, return a lazy collection).
- With the [schema package](Generating-The-Component-Schema#plugin-fields), the field's `php` key declares the hydrated type for the IDE helper: `{"type": "plugin", "plugin": "storyblok-vimeo", "php": "\\MyCompany\\StoryblokVideo\\Model\\Clip"}`.

---

## 🔄 Rich Text Processing & Context Preservation
Hydrators interact uniformly across your component footprint, regardless of where they sit in the layout hierarchy. The system executes block hydration identically for top-level component grids and deep-nested elements parsed inside a **Rich Text field** via the Tiptap editor extension.

If an advanced component hydrator requires parent page traits, language parameters, or global configuration settings to customize its behavior, you can safely extract the active `Story` model wrapper directly from the block environment:

```php
/** @var \WindAndKite\Storyblok\Api\Data\StoryInterface|null $story */
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