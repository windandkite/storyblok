Ready to unlock the power of Storyblok content in your Magento 2 store? This guide will quickly get the `WindAndKite_Storyblok` module up and running.

**Composer is the exclusive and supported method for installation.** This ensures all necessary dependencies are correctly managed for a seamless setup and reliable operation.

## 🚀 Installation via Composer

From your Magento 2 project root, execute these commands:

1.  **Add the module via Composer:**
    ```bash
    composer require windandkite/module-storyblok
    ```

2.  **Enable the module:**
    ```bash
    php bin/magento module:enable WindAndKite_Storyblok
    ```

3.  **Compile and deploy static content (if in production or developer mode):**
    ```bash
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy -f
    ```

4.  **Clear your Magento cache:**
    ```bash
    php bin/magento cache:clean
    php bin/magento cache:flush
    ```

## ✅ Quick Verification

To confirm the module is enabled, run:

```bash
php bin/magento module:status WindAndKite_Storyblok
```

You should see `WindAndKite_Storyblok` listed as Enabled. If so, you're all set for the next step: [Configuration](Configuration-Guide)!
