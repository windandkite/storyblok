# Storyblok SEO App Integration

This guide explains how to integrate the Storyblok SEO app with the Magento 2 Storyblok module to enhance your SEO capabilities.

## 1. Install the Storyblok SEO App

First, you need to install the Storyblok SEO app in your Storyblok space. You can find the app here: [Storyblok SEO App](https://www.storyblok.com/apps/seo) and its official guide here: [Storyblok SEO App Guide](https://www.storyblok.com/docs/native-apps/seo-app)

1. Log in to your Storyblok account.
2. Navigate to "Apps" in the left sidebar.
3. Search for "SEO" and install the official Storyblok SEO app.

## 2. Configure the SEO App in Storyblok

Once installed, you need to add the SEO app fields to the schema of each content type you want to manage SEO for. This is done by adding a new field of type `Plugin` and selecting `seo-metatags` as the custom type.

1. In Storyblok, navigate to "Block Library" and select the content type (e.g., "page") you want to add SEO fields to.
2. Add a new field to the schema, set its `Type` to `Plugin`.
3. In the `Custom Type` dropdown for this new field, select `seo-metatags` and set the `Field Name` to `metatags` (this is crucial for the module to work without customization).
4. Save the content type schema.

Now, when you go to "Content" and open a story of this content type, you should see a new tab or section for "SEO Settings" (this might vary slightly based on the app's UI).

5. Fill in the relevant SEO fields such as:
    - **SEO Title:** The title that appears in search engine results.
    - **SEO Description:** A brief summary of the page for search engine results.
    - **Open Graph Title:** The title used when sharing the page on social media.
    - **Open Graph Description:** The description used when sharing the page on social media.
    - **Open Graph Image:** An image URL for social media sharing.
    - **Twitter Card Type:** The type of Twitter card to display.
    - **Noindex:** Set to `true` to prevent search engines from indexing the page.
    - **Nofollow:** Set to `true` to prevent search engines from following links on the page.

### 3. Integrate SEO Data with Magento 2

Once the SEO app is configured in Storyblok and the `metatags` field is added to your content types, the Magento 2 module automatically makes this data available for rendering in your templates.

#### SEO Field Fallback Logic

The module includes a fallback mechanism to ensure that SEO meta tags are populated even if specific fields are not explicitly set in the Storyblok SEO app. This means that for certain meta tags, if the primary field is empty, the module will look for an alternative field to use. For example, if the `og_title` field is not set, the module will use the `title` field as a fallback.

This ensures that your SEO meta tags are as complete as possible, even if some fields are left blank in Storyblok. The module prioritizes specific Storyblok content keys for various meta tags. For instance:

*   **Open Graph Title (`og:title`)**: Falls back to the standard `title` field if `og_title` is empty.
*   **Open Graph Description (`og:description`)**: Falls back to the standard `description` field if `og:description` is empty.
*   **Twitter Title (`twitter:title`)**: Falls back to `og:title`, then `title` if `twitter:title` is empty.
*   **Twitter Description (`twitter:description`)**: Falls back to `og:description`, then `description` if `twitter:description` is empty.

This automatic fallback helps maintain comprehensive SEO data without requiring every single field to be manually populated.

### 4. Verification

After completing the above steps:

- **Check a Storyblok page on your Magento 2 frontend**: View the page source to ensure the meta tags are correctly rendered in the `<head>` section of your HTML.
- **Verify in Storyblok**: Ensure that the SEO fields are visible and editable within your Storyblok content entries.