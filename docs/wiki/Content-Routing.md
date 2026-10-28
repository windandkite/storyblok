With the `WindAndKite_Storyblok` module now configured, it's time to bring your Storyblok content to life on your Magento 2 storefront! This guide provides a high-level overview of how the module handles content routing and, most importantly, the philosophy behind its flexible templating system.

## 🗺️ How Content Routing Works
The `WindAndKite_Storyblok` module features a custom router designed to seamlessly integrate Storyblok pages into your Magento store's URL structure without interfering with existing Magento routes.
- Late Routing Order: This module's router is intentionally set with a very low priority (high sortOrder, typically 95 in Magento's routing chain). This means it will only attempt to match a Storyblok slug if no other Magento router (e.g., CMS pages, catalog, checkout) has successfully matched the requested URL. If no Magento page exists for {{BASE_URL}}/your-content-page, our router then steps in.
- Slug Matching: When an unmatched URL like {{BASE_URL}}/content-page is requested, the module checks for the existence of a Storyblok story with a matching slug (content-page in this example).
- Folder Restriction: If you have configured Page Routing > Restrict to Folder to "Yes" in the admin, the router will prepend the Folder Path (e.g., pages) to the requested slug before querying Storyblok. So, {{BASE_URL}}/about-us would look for a Storyblok story at pages/about-us.
- Development Mode & Visual Editor:
    - If General Settings > Developer Mode is enabled in the admin, the module will fetch the draft version of the Storyblok story.
    - Similarly, if the Storyblok Visual Editor is active and passes the _storyblok query parameter, the draft version is fetched, enabling real-time previews during content creation.

If a matching Storyblok story is found, the module takes over the request and prepares the data for rendering.
