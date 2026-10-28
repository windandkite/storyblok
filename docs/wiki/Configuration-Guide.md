With the WindAndKite_Storyblok module successfully installed, the next crucial step is to configure it within your Magento 2 admin. This guide will walk you through all the available settings to get your Storyblok integration running smoothly.

## ⚙️ Accessing the Configuration
1. Log in to your Magento Admin Panel.
2. Navigate to Stores > Configuration in the left sidebar.
3. In the left navigation menu, expand the WindAndKite section and then click on Storyblok.
   You'll find all the module's settings organized into several groups.

## 🔑 General Settings
These settings control the core functionality of the module.
- Module Enabled
    - Description: Set to "Yes" to activate the entire Storyblok integration module.
    - Default: Yes
    - Recommendation: Keep this enabled to use the module.
- API Token
    - Description: This is your Storyblok Preview API Key. It's absolutely essential for the module to fetch any content from your Storyblok space.
    - How to get it: Refer to the official Storyblok guide on retrieving access tokens. Ensure you copy the Preview token.
    - Input Type: This field is obscured for security, as it stores a sensitive API key.
    - Scope: Can be set per website, so each website can use its own Storyblok space.
- Space Region
    - Description: The region your Storyblok space was created in (Europe, United States, Canada, Australia or China). The module uses that region's API, so a wrong region makes every request fail.
    - How to find it: In Storyblok, go to Settings > Space.
    - Default: Europe
    - Scope: Can be set per website, like the API Token.
- Webhook Secret
    - Description: Verifies that webhook requests come from Storyblok. Webhooks clear the cache when content is published. A secret is generated when the module is installed, and the "Generate New Secret" button replaces it. Set at the default scope only.
    - Action: The field shows the full webhook URL, with the secret as its `key` parameter. In Storyblok, go to Settings > Webhooks and add a webhook for "Story published" (and "Story unpublished" / "Story deleted" if you want those to clear the cache too) using that URL.
    - If your Storyblok plan lets you set a webhook secret, you can also paste the secret into the webhook's "Secret" field. Storyblok then signs each request and the module checks the signature.
    - If you generate a new secret, update the URL in Storyblok too, or webhooks will be rejected.
- Developer Mode
    - Description: When "Yes", every visitor sees draft content and page caching is turned off.
    - Default: No
    - Recommendation: **Never enable this in production.** Editors see drafts in the Visual Editor without it, and can share drafts with signed links (see below).
- Draft Share Link Lifetime (days)
    - Description: How long a draft share link works. Editors create share links with the "Copy share link" button in the Visual Editor. See [Sharing drafts](Working-With-Storyblok-Visual-Editor#-sharing-drafts).
    - Default: 7
## 🗺️ Page Routing
These settings control how Storyblok content is routed to specific URLs in your Magento store.
- Enable Page Routing
    - Description: Activates the custom router that allows Storyblok pages to be served for URLs not natively handled by Magento.
    - Default: Yes
    - Recommendation: Keep this enabled to utilize Storyblok's page content.
- Restrict to Folder
    - Description: When set to "Yes", the module will automatically prepend a specified folder path to all URL lookups in Storyblok. This means if a user visits your-store.com/about-us and you've set a folder restriction to "pages", the module will look for a Storyblok story with the slug "pages/about-us".
    - Default: No
    - Dependency: This field enables the "Folder Path" field below.
- Folder Path
    - Description: The Storyblok folder path that will be automatically prepended to all URL lookups. For example, if set to 'pages':
      - URL: your-store.com/about-us → Looks for Storyblok story: pages/about-us
      - URL: your-store.com/contact → Looks for Storyblok story: pages/contact
    - Default: pages
    - Recommendation: This is particularly useful for organizing your content in Storyblok while maintaining clean URLs in Magento.
- Restrict Content Types
    - Description: When set to "Yes", the module will restrict routing to only allow specific Storyblok content types to be rendered as full pages.
    - Default: No
    - Dependency: This field enables the "Allowed Full Page Content Types" field below.
- Allowed Full Page Content Types
    - Description: This is a dynamic row field that allows you to add multiple Storyblok component types (e.g., 'page', 'blog_post') that are allowed to be rendered as full pages by the custom router.
    - Recommendation: Use this to control which Storyblok content types can be directly accessed via Magento URLs.
    - Note: Only stories with these component slugs will be processed by the custom router for full page rendering.

## 🌐 Sitemap
Control how your Storyblok stories are included in Magento's XML sitemap for SEO purposes.

- Add Stories to Sitemap
    - Description: Set to "Yes" to automatically include all routable Storyblok stories in your Magento XML sitemap.
    - Default: Yes
    - Recommendation: Generally, keep this enabled for better SEO.
- Priority
    - Description: Sets the sitemap priority for all Storyblok pages, ranging from 0.0 (lowest) to 1.0 (highest).
    - Default: 0.5
    - Recommendation: Choose a value that reflects the general importance of your Storyblok content relative to other pages on your site.
- Change Frequency
    - Description: Tells search engines how often your content typically updates. Available options:
      - always: Content changes on every access (rare, use cautiously)
      - hourly: For very dynamic content like news feeds
      - daily: For frequently updated sections like blogs or news
      - weekly: For regularly updated content
      - monthly: For relatively stable content
      - yearly: For archive or reference content
      - never: For permanent content (use rarely)
    - Default: weekly
    - Recommendation: Be realistic about your update frequency - overstating it may affect search engine trust. Most content sites work well with weekly or monthly.
- Exclude Folders
    - Description: Enter a comma-separated list of Storyblok folder paths (e.g., 'drafts,private') to exclude their stories from being added to the Magento sitemap.
    - Recommendation: Use this for content you don't want indexed by search engines.

## 📜 Story List Settings
These settings are relevant if you are displaying lists of Storyblok stories on your Magento storefront.
- Enable Story Lists
    - Description: Set to "Yes" to enable the display of Story Lists, typically for Folder Root stories.
    - Default: Yes
- Stories Per Page
    - Description: Controls pagination for story lists:
      - Set a number: Enables pagination with that many stories per page
      - Leave empty: Uses the default value of 25 stories per page
    - Default: 25
    - Validation: Must be a positive number
    - Recommendation: Choose a value that balances user experience with page load performance. Lower numbers (10-30) work best for most sites.

## 💾 Saving Your Configuration
After making any changes to these settings, always click the Save Config button in the top right corner of the page.

Then, clear your Magento cache to ensure the new settings are immediately active:

```BASH
php bin/magento cache:clean
php bin/magento cache:flush
```

You have now fully configured the `WindAndKite_Storyblok` module! The next step is to dive into Usage & Template Development, where you'll learn how to render your Storyblok content beautifully within your Magento theme.
