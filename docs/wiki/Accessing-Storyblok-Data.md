This guide provides a detailed explanation of how to effectively access and utilize the data retrieved from Storyblok within your Magento `.phtml` templates. Understanding these methods is crucial for accurately rendering your content and taking full advantage of the module's capabilities.

## 🔑 Understanding the `$block` Context

Within your Storyblok-related `.phtml` templates (both for Stories and Components), the `$block` variable will be an instance of either:

* `WindAndKite\Storyblok\Block\Story` (for page-level templates like `story/page.phtml`)
* `WindAndKite\Storyblok\Block\Block` (for component-level templates like `block/hero.phtml`)

Both of these classes extend `Magento\Framework\View\Element\Template` and, crucially, implement custom logic for accessing Storyblok data.

## 📋 Direct Data Access (`getData()` and Magic Getters)

The module leverages Magento's standard `getData()` method and provides convenient "magic getter" methods for accessing Storyblok fields.

### 1. Basic Fields (Text, Number, Boolean, etc.)

For simple fields (like `text`, `number`, `boolean`, `single-option`, `multi-option`), you can access their values directly:

* **Using `getData()`:**
    ```php
    <?php
    /** @var \WindAndKite\Storyblok\Block\Block $block */
    $title = $block->getData('title');
    $subtitle = $block->getData('subtitle');
    ?>
    <h1><?= $block->escapeHtml($title) ?></h1>
    <p><?= $block->escapeHtml($subtitle) ?></p>
    ```

* **Using Magic Getters (`getFieldName()`):** This is the most common and recommended approach for readability.
  The module dynamically creates getter methods based on your Storyblok field names (converted to `PascalCase`).
    ```php
    <?php
    /** @var \WindAndKite\Storyblok\Block\Block $block */
    $title = $block->getTitle(); // Accesses the 'title' field
    $isEnabled = $block->getIsEnabled(); // Accesses the 'is_enabled' field
    ?>
    <?php if ($isEnabled): ?>
        <h2><?= $block->escapeHtml($title) ?></h2>
    <?php endif; ?>
    ```

  **Note on Naming:** If your Storyblok field is `my_field_name`, the magic getter will be `getMyFieldName()`.

### 2. Asset Fields (Images, Files)

Storyblok asset fields (e.g., images, files) return an array of data from the Storyblok API. To work with these assets and apply transformations (like resizing or cropping), you should use the `WindAndKite\Storyblok\ViewModel\Asset` ViewModel.

You can retrieve the `Asset` ViewModel via `$block->getAssetViewModel()`. This ViewModel then provides the `transformImage()` method, which takes the raw asset data and an options array for transformations.

* **Example: Using the `Asset` ViewModel to transform an image:**
    ```php
    <?php
    declare(strict_types=1);

    use Magento\Framework\Escaper;
    use WindAndKite\Storyblok\Block\Block;

    /** @var Block $block */
    /** @var Escaper $escaper */

    /** @var \WindAndKite\Storyblok\ViewModel\Asset $assetViewModel */
    $assetViewModel = $block->getAssetViewModel();

    // Assuming 'hero_image' is a Storyblok asset field on your component
    $rawHeroImageData = $block->getHeroImage(); // Get the raw array data for the 'hero_image' field

    // Transform the image to a specific width and height
    $transformedImageUrl = $assetViewModel->transformImage($rawHeroImageData, ['width' => 600, 'height' => 400]);

    // Get the alt text (assuming 'hero_image' also holds alt text in Storyblok)
    // You might need to adjust how alt text is retrieved based on your Storyblok setup
    $altText = $rawHeroImageData['alt'] ?? 'Hero Image';
    ?>

    <?php if ($transformedImageUrl): ?>
        <img src="<?= $escaper->escapeUrl($transformedImageUrl) ?>" alt="<?= $escaper->escapeHtml($altText) ?>" loading="lazy" />
    <?php endif; ?>
    ```

  The `Asset` ViewModel (`WindAndKite\Storyblok\ViewModel\Asset`) provides the `transformImage(array $assetData, array $options)` method, which takes the raw Storyblok asset data (obtained via `$block->getFieldName()`) and an `$options` array to apply Storyblok's Image Service transformations. It returns the transformed image URL.

  **Supported `transformImage` Options:**
  The `transformImage` method supports a wide range of Storyblok Image Service operations. Common options include:
    * `width` (int): Resizes the image to the specified width.
    * `height` (int): Resizes the image to the specified height.
    * `format` (string): Changes the image format (e.g., `'webp'`, `'jpeg'`, `'png'`, `'avif'`).
    * `quality` (int): Sets the image quality (0-100).
    * `fill` (string): Fills transparent areas with a color (e.g., `'#RRGGBB'`, `'transparent'`).
    * `focus` (string): Sets the focal point for smart cropping (e.g., `'100:100/200:200'`).
    * `brightness` (int): Adjusts brightness (-100 to 100).
    * `blur` (int): Applies a blur effect (0-100).
    * `rotate` (int): Rotates the image (0, 90, 180, 270 degrees).
    * `round_corner` (array): Applies rounded corners.
    * `grayscale` (bool): Converts the image to grayscale.

  For a comprehensive list of all possible image transformation options and their detailed usage, please refer to the [Official Storyblok Image Service Operations Documentation](https://www.storyblok.com/docs/api/image-service/operations/).

### 3.- Rich Text Fields and Nested Blocks (Rendered HTML)

    > **Note:** Rich Text and Block content are automatically escaped by the module's rendering service, ensuring safe output in your templates.

For Storyblok fields of type `Richtext` or fields containing nested `Bloks`, the module provides a powerful magic getter with the `Html` suffix. This method automatically processes the raw Storyblok JSON/array data for that field and renders it into a complete HTML string using the module's `FieldRenderer` service.

This is the **preferred method** for outputting content from these complex field types.

* **Using Magic Getters (`getFieldNameHtml()`):**
    ```php
    <?php
    /** @var \WindAndKite\Storyblok\Block\Block $block */

    // For a Storyblok field named 'body_content' of type 'Richtext'
    $bodyContentHtml = $block->getBodyContentHtml();

    // For a Storyblok field named 'sections' of type 'Bloks' (containing nested components)
    $sectionsHtml = $block->getSectionsHtml();
    ?>

    <div class="main-content">
        <?= /** @noEscape */ $bodyContentHtml ?>
    </div>

    <div class="page-sections">
        <?= /** @noEscape */ $sectionsHtml ?>
    </div>
    ```
  **Important:** Because `getFieldNameHtml()` returns raw HTML, you *must* use `<?= /** @noEscape */ ... ?>` when outputting it in your `.phtml` templates. This tells Magento's escaper that the content is already safe HTML.

  The `FieldRenderer` service (`WindAndKite\Storyblok\Service\FieldRenderer`) is responsible for:
    * Converting Storyblok rich text JSON into HTML.
    * Iterating through nested blocks and dynamically rendering them using their corresponding `block/[component_name].phtml` templates.


## 📖 Accessing Raw Story and Block Data

In some advanced scenarios, you might need to access the entire raw data structure of the current Storyblok story or block.

* **Accessing current Story data (in `Story` or `Block` contexts):**
    ```php
    <?php
    /** @var \WindAndKite\Storyblok\Block\Story|\WindAndKite\Storyblok\Block\Block $block */
    $storyData = $block->getStory()->getData(); // Gets the full raw array data for the current Story
    // Example: Accessing Story meta data
    $storyName = $storyData['name'];
    ?>
    ```

* **Accessing current Block (component) data (in `Block` context):**
    ```php
    <?php
    /** @var \WindAndKite\Storyblok\Block\Block $block */
    $blockRawData = $block->getBlock()->getData(); // Gets the full raw array data for the current component
    // Example: Accessing a field directly from raw data
    $rawImageField = $blockRawData['image_field'];
    ?>
    ```

## ➡️ What's Next

Now that you have a solid understanding of how to access Storyblok data, explore these related topics:

* [**Working with the Storyblok Visual Editor**](Working-With-Storyblok-Visual_editor)
    * Integrate your Magento theme with Storyblok's real-time visual editor for an enhanced content creation experience.
* [**Displaying Story Lists**](Displaying-Story-Lists)
    * Detailed instructions on how to use the `StoryList` block to create paginated content archives.
