This guide focuses on how to leverage the WindAndKite_Storyblok module to display dynamic lists of Storyblok content, such as blog post listings, news articles, or project portfolios. The module provides the StoryList block, which extends the core Story block, to handle the fetching and basic rendering of these content collections.

## 📜 Understanding Story Lists
Storyblok allows you to organize content into folders. Often, the "startpage" of a folder (a story with the same slug as the folder, e.g., /blog/index) acts as an overview or archive for the stories within that folder. Our module's Story List feature is designed to fetch and display these child stories.

The StoryList block (WindAndKite\Storyblok\Block\StoryList) is specifically tailored for this purpose. It queries Storyblok for child stories of the current "startpage" story, respecting the pagination and filtering settings configured in the Magento admin.

## ⚙️ Configuration for Story Lists
Before displaying Story Lists, ensure you have configured the following settings in your Magento Admin Panel under Stores > Configuration > WindAndKite > Storyblok > Story List Settings:
- Enable Story Lists: Set to "Yes" to activate this feature.
- Stories Per Page: Define the number of stories to display on each page of a listing. Leave empty to show all stories on a single page.

## 🏗️ Implementing a Story List in Your Theme
To display a Story List, you'll primarily work with the layout XML and .phtml templates. The template paths will reflect the Storyblok content type of the list page itself (for the main list template) and the individual story items within that list.

- The general template path pattern for lists will be: `WindAndKite_Storyblok::story/list/[list_content_type].phtml`
- And for individual items: `WindAndKite_Storyblok::story/item/[item_content_type].phtml`

Where:

- [list_content_type] is the snake_case name of the Storyblok content type for the story that is the list page (e.g., article_landing_page).
- [item_content_type] is the snake_case name of the Storyblok content type for each story within the list (e.g., article).

1. **Layout XML (storyblok_page_view_startpage.xml)**
   The module includes a specific layout handle, storyblok_page_view_startpage.xml, which is used when the router identifies a Storyblok story as a "startpage" (i.e., it has is_startpage: true in Storyblok). This layout is designed to include the StoryList block.

Example from module's default (storyblok_page_view_startpage.xml):
```XML
<?xml version="1.0"?>
<page xmlns:xsi="[http://www.w3.org/2001/XMLSchema-instance](http://www.w3.org/2001/XMLSchema-instance)"
      layout="1column"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <referenceBlock name="storyblok.content">
                <block name="storyblok.list" class="WindAndKite\Storyblok\Block\StoryList">
                    <arguments>
                        <!-- template_dir maps to the 'story/list' base, component_name is derived from the Storyblok 'list' content type -->
                        <argument name="template_dir" xsi:type="string">story/list</argument>
                    </arguments>

                    <block name="storyblok.list.item"
                           class="WindAndKite\Storyblok\Block\Story">
                        <arguments>
                            <!-- template_dir maps to the 'story/item' base, component_name is derived from the Storyblok 'item' content type -->
                            <argument name="template_dir" xsi:type="string">story/item</argument>
                        </arguments>
                    </block>
                </block>
            </referenceBlock>
        </referenceContainer>
    </body>
</page>
```

Key points from this layout:
- It uses referenceBlock name="storyblok.content" to place the story list within the main Storyblok page block.
- A new block, storyblok.list, is added, using WindAndKite\Storyblok\Block\StoryList.
    - It specifies template_dir="story/list". When the StoryList block is rendered, it will look for a template matching the content type of the current list story. For example, if your blog landing page has a content type article_landing_page, the module will seek WindAndKite_Storyblok::story/list/article_landing_page.phtml.
- Crucially, a child block storyblok.list.item is defined, using WindAndKite\Storyblok\Block\Story.
    - It specifies template_dir="story/item". When each individual story in the list is rendered, the Story block (used by storyblok.list.item) will look for a template matching that individual story's content type. For example, if your individual blog posts have a content type article, the module will seek WindAndKite_Storyblok::story/item/article.phtml.

2. **Main List Template (`story/list/[list_content_type].phtml`)**

This template will be responsible for the overall structure of your story listing, including iteration over the stories and potentially pagination controls.

Example: `story/list/article_landing_page.phtml`
```PHP
<?php
/** @var \WindAndKite\Storyblok\Block\StoryList $block */
/** @var \Magento\Framework\Escaper $escaper */
declare(strict_types=1);

$stories = $block->getStories(); // Get the SearchResultsInterface object
$storyItems = $stories ? $stories->getItems() : []; // Get the array of StoryblokStory objects
$stats = $block->getStats(); // Get the pagination stats phrase

$currentPage = (int)$stories->getSearchCriteria()->getCurrentPage();
$pageSize = (int)$stories->getSearchCriteria()->getPageSize();
$totalCount = (int)$stories->getTotalCount();
$totalPages = (int)ceil($totalCount / $pageSize);

// Define a range of pages to display around the current page
$paginationRange = 2; // e.g., current page +/- 2
$startPage = max(1, $currentPage - $paginationRange);
$endPage = min($totalPages, $currentPage + $paginationRange);
?>

<div class="story-list-wrapper">
    <h2 class="list-heading">Latest Stories</h2>

    <?php if ($stories && count($storyItems) > 0): ?>
        <p class="list-stats"><?= $escaper->escapeHtml($stats) ?></p>
        <div class="story-grid">
            <?php foreach ($storyItems as $storyItem): ?>
                <?php
                // Render each individual story using its assigned item template (e.g., story/item/article.phtml)
                echo $block->getChildBlock('storyblok.list.item')
                           ->setStory($storyItem) // Pass the individual story to the item block
                           ->toHtml();
                ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= $escaper->escapeUrl($block->getStoryUrl($block->getStory(), ['_query' => ['p' => $currentPage - 1]])) ?>">Previous</a>
                <?php endif; ?>

                <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                    <?php
                    $pageUrlParams = ($page === 1) ? [] : ['p' => $page]; // Don't add 'p=1' if it's the first page
                    $pageUrl = $block->getStoryUrl($block->getStory(), ['_query' => $pageUrlParams]);
                    ?>
                    <a href="<?= $escaper->escapeUrl($pageUrl) ?>" class="<?= $page === $currentPage ? 'active' : '' ?>">
                        <?= (int)$page ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= $escaper->escapeUrl($block->getStoryUrl($block->getStory(), ['_query' => ['p' => $currentPage + 1]])) ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <p>No stories found in this list.</p>
    <?php endif; ?>
</div>
```
_Note:_ The pagination example provided here is a basic, framework-agnostic implementation to demonstrate the usage of $block->getStoryUrl(). For more advanced pagination (e.g., ellipses, first/last page links, or complex styling), you would extend this logic in your theme.

**Key takeaways for `story/list/[list_content_type].phtml`:**
- **`$block->getStories()`:** Returns a `Magento\Framework\Api\SearchResultsInterface` object, which contains an array of `StoryblokStory` objects (`getItems()`) and pagination information (`getSearchCriteria()`, `getTotalCount()`).
- **`$block->getStats()`:** Returns a `Magento\Framework\Phrase` with pagination statistics (e.g., "Items 1 to 10 of 100 total").
- **Looping Items:** The `foreach ($storyItems as $storyItem)` loop is used to iterate over each story to be displayed.
- **Rendering Individual Items:** Inside the loop, `$block->getChildBlock('storyblok.list.item')->setStory($storyItem)->toHtml()`; is essential. It retrieves the child block defined in the layout XML, passes the individual $storyItem to it, and renders its associated template (e.g., `story/item/article.phtml`).
- **Pagination:** The included example demonstrates basic pagination. `getStoryUrl($block->getStory(), ['_query' => ['p' => $pageNumber]])` is used to generate the URLs for pagination links.

3. **Individual Item Template (`story/item/[item_content_type].phtml`)**

This template defines how each single story within the list will be displayed. It receives an individual `StoryblokStory` object.

Example: `story/item/article.phtml`
```PHP
<?php
/** @var \WindAndKite\Storyblok\Block\Story $block */
/** @var \Magento\Framework\Escaper $escaper */
declare(strict_types=1);

// Access the individual Storyblok story data set via setStory()
$story = $block->getStory();
$storyTitle = $story->getName();
$storyFullSlug = $story->getFullSlug();
$storyIntroText = $story->getContent()->getIntroTextHtml(); // Assuming 'intro_text' is a rich text field on your story's content type
// Assuming a 'thumbnail_image' asset field on the story's content type
$rawThumbnailData = $story->getContent()->getThumbnailImage();
$assetViewModel = $block->getAssetViewModel();
$thumbnailUrl = $rawThumbnailData ? $assetViewModel->transformImage($rawThumbnailData, ['width' => 300, 'height' => 200, 'quality' => 80]) : '';
$thumbnailAlt = $rawThumbnailData['alt'] ?? $storyTitle . ' thumbnail';
?>

<div class="story-list-item">
    <?php if ($thumbnailUrl): ?>
        <div class="story-thumbnail">
            <a href="<?= $escaper->escapeUrl($block->getStoryUrl($story)) ?>">
                <img src="<?= $escaper->escapeUrl($thumbnailUrl) ?>" alt="<?= $escaper->escapeHtml($thumbnailAlt) ?>" loading="lazy" />
            </a>
        </div>
    <?php endif; ?>
    <h3><a href="<?= $escaper->escapeUrl($block->getStoryUrl($story)) ?>"><?= $escaper->escapeHtml($storyTitle) ?></a></h3>
    <?php if ($storyIntroText): ?>
        <div class="intro-text">
            <?= /** @noEscape */ $storyIntroText ?>
        </div>
    <?php endif; ?>
    <a href="<?= $escaper->escapeUrl($block->getStoryUrl($story)) ?>" class="read-more">Read More</a>
</div>
```
**Key takeaways for `story/item/[item_content_type].phtml`:**
- **`$block->getStory()`:** This is the `StoryblokStory` object for the current item being rendered in the loop.
- **Accessing Story Data:** You can access fields from the `StoryblokStory` object directly (e.g., `$story->getName()`, `$story->getFullSlug()`).
- **Accessing Content Fields:** If your Storyblok Story content type has fields (e.g., `intro_text`, `thumbnail_image`), you access them via `$story->getContent()->getFieldName()`. Use the `Html` suffix for rich text/nested blocks, just as you would for component templates.
- **`$block->getStoryUrl($story)`:** This helper method is crucial for generating correct Magento URLs for each story in the list.

## ➡️ What's Next
This concludes the core documentation for setting up and rendering Storyblok content. You now have the knowledge to build rich, dynamic content experiences.
- [Troubleshooting](Troubleshooting-Guide)
    - Find solutions to common issues you might encounter.
- [Contributing](Contribution-Guide)
    - Learn how you can contribute to the development of this module.
