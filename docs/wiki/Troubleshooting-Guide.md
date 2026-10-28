This guide provides solutions to common issues you might encounter while installing, configuring, or using the `WindAndKite_Storyblok` module with your Magento 2 store. Before diving into specific problems, always start with the general troubleshooting steps.

## 🔍 General Troubleshooting Steps

If you're experiencing any unexpected behavior, always start with these fundamental Magento troubleshooting steps:

1.  **Clear Magento Cache:**
    ```bash
    php bin/magento cache:clean
    php bin/magento cache:flush
    ```

2.  **Recompile and Deploy Static Content:** (Especially after theme or template changes)
    ```bash
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy -f
    ```

3.  **Check Module Status:** Ensure the module is enabled.
    ```bash
    php bin/magento module:status WindAndKite_Storyblok
    ```
    It should display `Enabled`. If not, enable it using `php bin/magento module:enable WindAndKite_Storyblok` and re-run `setup:di:compile` and `cache:clean`.

4.  **Check Magento Logs:** Review `var/log/system.log` and `var/log/exception.log` for any errors related to Storyblok.

## 🚫 Content Not Appearing on the Storefront

If your Storyblok content isn't showing up as expected:

* **API Token:**
    * **Issue:** Incorrect or missing API Token in Magento config.
    * **Solution:** Go to **Stores** > **Configuration** > **WindAndKite** > **Storyblok** > **General Settings**. Ensure the "API Token" field is correctly populated with your **Storyblok Preview API Key**. Double-check for typos or extra spaces. Refer to the [official Storyblok guide on retrieving access tokens](https://www.storyblok.com/faq/retrieve-and-generate-access-tokens).

* **Module/Page Routing Not Enabled:**
    * **Issue:** The Storyblok module or its page routing feature is disabled.
    * **Solution:** In Magento config (`Stores` > `Configuration` > `WindAndKite` > `Storyblok`), ensure **"General Settings" > "Module Enabled"** is "Yes" and **"Page Routing" > "Enable Page Routing"** is "Yes".

* **Developer Mode / `_storyblok` Parameter:**
    * **Issue:** You're expecting to see draft content but it's not appearing, or you're debugging.
    * **Solution:** For developers, enable **"General Settings" > "Developer Mode"** to "Yes" to always fetch draft content. For editors using the Visual Editor, ensure the `_storyblok` query parameter is present in the URL.

* **Storyblok Slug Mismatch / Folder Restriction:**
    * **Issue:** The URL slug in Magento doesn't match the Storyblok story's full slug, or the "Folder Path" configuration is incorrect.
    * **Solution:** Verify that the URL you're visiting in Magento exactly matches the Storyblok story's full slug (e.g., if Magento URL is `your-site.com/blog/my-post`, Storyblok slug should be `blog/my-post`). If `Page Routing > Restrict to Folder` is "Yes", ensure `Folder Path` is correctly set (e.g., `blog`) and that the story actually resides within that folder in Storyblok.

* **Missing Templates:**
    * **Issue:** The module cannot find a `.phtml` template for your Storyblok content type or component.
    * **Solution:** Check your theme's `app/design/frontend/[Your_Vendor]/[Your_Theme]/WindAndKite_Storyblok/templates/` directory. For a Storyblok "page" content type, ensure `story/page.phtml` exists. For a Storyblok "hero" component, ensure `block/hero.phtml` exists. The module will fall back to `story/fallback.phtml` or `block/fallback.phtml` and display raw data if a specific template is missing, which can help in debugging.

* **Magento Layout XML Issues:**
    * **Issue:** The `storyblok_page_view.xml` or `storyblok_page_view_startpage.xml` files in your theme (or the module's defaults) are not being processed correctly, preventing the main `storyblok.content` block from rendering.
    * **Solution:** Verify your theme's `view/frontend/layout/` directory structure. Ensure your theme is correctly configured to inherit or override the `WindAndKite_Storyblok` module's layout files if you've made customizations.

## ⚡️ Content Not Updating After Publishing in Storyblok

If you publish content in Storyblok but it doesn't appear on your live Magento storefront:

* **Webhook Secret Misconfiguration:**
    * **Issue:** The "Webhook Secret" in Magento and/or Storyblok's webhook configuration doesn't match, preventing cache invalidation.
    * **Solution:**
        1.  In Magento, go to **Stores** > **Configuration** > **WindAndKite** > **Storyblok** > **General Settings**. Copy the "Webhook Secret".
        2.  In your Storyblok space, go to **Settings** > **Webhooks**. Find the webhook configured for your Magento instance (or create a new one for `Content Published`). Paste the exact secret from Magento into the webhook's "Secret" field. Ensure the webhook URL is correctly pointing to your Magento webhook endpoint (i.e., `{{BASE_URL}}/storyblok/webhook`).
        3.  Test the webhook from Storyblok's webhook settings.

* **Magento Cache Not Flushing:**
    * **Issue:** Despite the webhook being triggered, Magento's cache isn't actually clearing for the relevant Storyblok entries.
    * **Solution:** Manually clear cache from Magento Admin (**System** > **Cache Management**) or via CLI (`php bin/magento cache:clean full_page block_html`).

* **External Caching Layers:**
    * **Issue:** A CDN (like Cloudflare) or Varnish is caching the Magento page, and is not configured to respect cache invalidation requests from Magento.
    * **Solution:** This requires configuration outside of Magento. Consult your CDN or Varnish documentation to ensure it honors cache invalidation headers (e.g., `Cache-Control: no-cache`, `Pragma: no-cache`, `Expires: Sat, 26 Jul 1997 05:00:00 GMT`) or that you have a custom invalidation mechanism in place.

## 👁️ Storyblok Visual Editor Issues

If the Visual Editor isn't loading or highlighting components correctly:

* **Storyblok Space Location Mismatch:**
    * **Issue:** The "Location" URL in your Storyblok space doesn't exactly match your Magento storefront URL, preventing the iframe from loading.
    * **Solution:** In your Storyblok space, go to **Settings** > **General**. Ensure the "Location" field is precisely `https://your-magento-domain.com/` (or your exact development URL).

* **CORS (Cross-Origin Resource Sharing) Errors:**
    * **Issue:** Your browser console shows CORS errors, typically preventing `app.storyblok.com` from embedding your Magento site in an iframe.
    * **Solution:** This usually requires server-level configuration. For Apache, you might add headers like `Header always set X-Frame-Options "ALLOWALL"` (use with caution, consider specific domains) or configure CORS specifically for requests from `app.storyblok.com`. For Nginx, similar configurations are needed. Consult your web server's documentation or your hosting provider.

* **`_editable` HTML Comment Missing/Incorrect:**
    * **Issue:** The `_editable` HTML comment that Storyblok uses to identify blocks is not present in your rendered HTML, or it's incorrectly placed.
    * **Solution:** Use your browser's "Inspect Element" tool to view the source code of your page. Look for comments starting with `<!--#storyblok#` directly before the root HTML element of your Storyblok components. If they are missing, ensure your component templates have a single root element, as the module automatically prefixes this comment based on that structure.

* **JavaScript Errors:**
    * **Issue:** JavaScript errors on your Magento storefront are preventing the Storyblok Bridge from initializing or functioning correctly.
    * **Solution:** Open your browser's developer console (F12) and check the "Console" tab for any JavaScript errors. Resolve these errors in your theme or other modules, as they can interfere with the Storyblok Bridge.

## 🐌 Performance Concerns

If you notice slow loading times for Storyblok content:

* **Magento Caching Strategy:**
    * **Issue:** Magento's full page cache (FPC) or block caches are not effectively utilized for Storyblok content.
    * **Solution:** Ensure Magento's FPC is enabled. The module's blocks are designed to be cacheable, but external factors (like uncacheable blocks on the same page) can prevent FPC. Regularly clear and warm your caches.

* **Image Optimization:**
    * **Issue:** Large, unoptimized images from Storyblok are slowing down your page.
    * **Solution:** Always use the `Asset` ViewModel's `transformImage()` method to serve appropriately sized and optimized images (e.g., using `width`, `height`, `quality`, `format='webp'`). This offloads image manipulation to Storyblok's CDN.

---

If you've tried these solutions and are still encountering issues, please refer to the module's GitHub repository for further assistance, opening an issue with detailed steps to reproduce, or consult the official Storyblok documentation.
