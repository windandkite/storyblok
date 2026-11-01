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

* **Draft Content Not Showing:**
    * **Issue:** You're expecting to see draft content but the published version appears.
    * **Solution:** Drafts are only shown in the Visual Editor (with a valid `_storyblok_tk` token), through a valid [share link](Working-With-Storyblok-Visual-Editor#-sharing-drafts), or with **"General Settings" > "Developer Mode"** set to "Yes" (development environments only). Adding `_storyblok` or `_storyblok_version=draft` to a URL by hand shows the published version. If a share link shows the published version, it has expired, was created for a different page or store view, or was altered.

* **Storyblok API Errors:**
    * **Issue:** A page fails to load, or a Storyblok block on a Magento page renders empty.
    * **Solution:** Check `var/log/system.log` and `var/log/exception.log` for Storyblok API errors. When the API fails (anything other than "not found"), the module logs it and doesn't cache the page, so it recovers once the API is back.

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

* **Webhook Rejected:**
    * **Issue:** Storyblok shows the webhook failing with `401 Unauthorized`, so the cache isn't cleared.
    * **Solution:**
        1.  In Magento, go to **Stores** > **Configuration** > **WindAndKite** > **Storyblok** > **General Settings** (default scope). Copy the webhook URL shown under "Webhook Secret". It ends in `storyblok/webhook?key=...`.
        2.  In your Storyblok space, go to **Settings** > **Webhooks**, and set the webhook's endpoint to that exact URL. If you generated a new secret, the old URL no longer works.
        3.  If you have also filled in the webhook's "Secret" field in Storyblok, it must match the Magento secret exactly, or remove it.
        4.  Test the webhook from Storyblok's webhook settings. `400 Bad Request` means the request was authorised but its body wasn't valid JSON.

* **Magento Cache Not Flushing:**
    * **Issue:** Despite the webhook being triggered, Magento's cache isn't actually clearing for the relevant Storyblok entries.
    * **Solution:** The webhook clears only the pages showing the published story (or stories that reference or link to it) and story lists, not the whole page cache. Check `var/log/system.log` for the "Storyblok webhook" entry to confirm it arrived. To clear everything manually, use **System** > **Cache Management** or `php bin/magento cache:clean full_page block_html`.

* **External Caching Layers:**
    * **Issue:** A CDN (like Cloudflare) or Varnish keeps serving the old page.
    * **Solution:** The module purges pages by cache tag, using Magento's standard purge, so Varnish works when it is configured as Magento's full page cache (**Stores** > **Configuration** > **Advanced** > **System** > **Full Page Cache**). Other CDNs need their own Magento integration to purge by tag.

## 👁️ Storyblok Visual Editor Issues

If the Visual Editor isn't loading or highlighting components correctly:

* **Storyblok Space Location Mismatch:**
    * **Issue:** The "Location" URL in your Storyblok space doesn't exactly match your Magento storefront URL, preventing the iframe from loading.
    * **Solution:** In your Storyblok space, go to **Settings** > **General**. Ensure the "Location" field is precisely `https://your-magento-domain.com/` (or your exact development URL).

* **Page Refused in the Iframe:**
    * **Issue:** The Visual Editor shows a blank or "refused to connect" preview, and the browser console mentions `X-Frame-Options` or `frame-ancestors`.
    * **Solution:** The module removes Magento's `X-Frame-Options` header for requests with a valid Visual Editor token, so check that the token is valid (the API Token must be the space's Preview token). If the header is still there, your web server, CDN or another module is adding it. Don't remove it for every request: allow framing only for the Visual Editor, or by `app.storyblok.com` with `Content-Security-Policy: frame-ancestors`.

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
