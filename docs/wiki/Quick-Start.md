# Quick Start Guide

Welcome to the WindAndKite Storyblok integration for Magento 2! This guide will help you get up and running quickly, focusing on the essential steps to create and display your first Storyblok content.

## 1. Install the Module

If you haven't already, follow the [Installation Guide](Installation-Guide) to install the module in your Magento 2 project.

## 2. Configure Your API Token

After installation, the first step is to connect your Magento 2 store to your Storyblok space. You'll need your Storyblok API token.

1. Log in to your Magento Admin Panel.
2. Navigate to **Stores > Configuration > WindAndKite > Storyblok**.
3. Under **General Settings**, enter your **Storyblok API Token**.
   - You can find your API token in your Storyblok space under **Settings > Access Tokens**.
4. Click **Save Config**.

## 3. Create Your First Storyblok Blok

Let's create a simple block in Storyblok and see it displayed in your Magento store.

1. Log in to your Storyblok space.
2. Go to **Content** and create a new Story (e.g., a page).
3. Inside your Story, click **+ Add block**.
4. Give your block a name (e.g., `my_first_block`).
5. Define a simple schema for it, for example, a `text` field named `title`.
6. Add some content to the `title` field (e.g., "Hello from Storyblok!").
7. **Save** and **Publish** your Story.

## 4. Create the Magento Template for Your Blok

Once you have created your Storyblok block, you will need to create a corresponding PHTML template file in your Magento 2 theme to render it. This module expects block templates to be located in `[Your_Theme_Directory]/WindAndKite_Storyblok/templates/block/`.

For Storyblok stories, a default template is provided for stories with the `page` content type. If your story uses a different content type, you will need to create a corresponding story template in `[Your_Theme_Directory]/WindAndKite_Storyblok/templates/story/` that matches the story's slug.
2. Create a new file named `my_first_block.phtml` (matching the name of your Storyblok block) in this directory.
   - Example path: `app/design/frontend/YourVendor/YourTheme/WindAndKite_Storyblok/templates/block/my_first_block.phtml`
3. Add the following content to `my_first_block.phtml`:

```phtml
<?php /** @var \WindAndKite\Storyblok\Block\Story $block */ ?>

<div>
    <h1><?= $block->getTitle(); ?></h1>
</div>
```

## 5. View Your Block in Magento

Now, let's see your Storyblok content live in Magento.

1. Clear your Magento cache: `php bin/magento cache:clean`
2. Access the URL of the Story you created in Storyblok (e.g., if your Storyblok story slug is `my-test-page`, and your Magento base URL is `your-store.com`, navigate to `your-store.com/my-test-page`).

You should now see "Hello from Storyblok!" displayed on your Magento page!

This demonstrates the basic flow of creating content in Storyblok and rendering it in Magento. You can now explore more advanced features like [Creating Custom Templates](Creating-Custom-Templates) and [Accessing Storyblok Data](Accessing-Storyblok-Data).