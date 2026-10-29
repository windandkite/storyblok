# ![image logo](docs/favicon.svg) WindAndKite_Storyblok

Magento 2 Module for Storyblok Integration

## Why is it needed?

This Magento 2 Module seamlessly integrates your e-commerce platform with Storyblok, a powerful headless CMS, offering numerous benefits for content management and development workflows:

* **Decoupled Content Management:** Separate content creation in Storyblok from Magento's presentation layer, allowing independent work for content editors and flexible frontend development.
* **Enhanced Content Editor Experience:** Storyblok's visual editor provides real-time previews for intuitive and efficient content creation.
* **Omnichannel Content Delivery:** Enable content delivery to various channels (Magento storefront, mobile apps, other websites) from a single source of truth.
* **Improved Performance:** Headless architecture can lead to faster website performance by offloading content rendering from Magento.
* **Scalability and Flexibility:** Offers greater scalability and flexibility for content model updates and adoption of modern frontend technologies.
* **Future-Proof Architecture:** Decoupling content facilitates adaptation to future technological changes and digital experience evolution.

This module empowers businesses to create richer, more dynamic content experiences while improving development workflows and overall agility.

## Installation

This module can be installed via Composer from Packagist.

1.  **Add the module to your project:**
    ```bash
    composer require windandkite/module-storyblok
    ```

2.  **Enable the module:**
    ```bash
    bin/magento module:enable WindAndKite_Storyblok
    bin/magento setup:upgrade
    ```
    (or simply run `bin/magento setup:upgrade` which enables new modules automatically.)

## Configuration & Usage

All detailed setup, configuration, user guides, and developer guides are available in the module's Wiki.

The Wiki covers:
* **Quick Start**
* **Installation Guide**
* **Configuration Guide**
* **SEO App Integration**
* **Content Routing**
* **Creating Custom Templates**
* **Accessing Storyblok Data**
* **Working with Visual Editor**
* **Displaying Story Lists**
* **Troubleshooting Guide**

Please refer to the [Wiki](https://github.com/windandkite/storyblok/wiki) for comprehensive documentation.

## Contributions

We welcome contributions! Whether that be raising bugs, suggesting feature ideas or getting down and dirty with the code, head on over to the [Contributions Guide](https://github.com/windandkite/storyblok/wiki/Contribution-Guide) in the Wiki to see how you can get involved.
