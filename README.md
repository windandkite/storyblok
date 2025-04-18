# ![image logo](docs/favicon.svg) WindAndKite_Storyblok

A Magento 2 Module Designed to Integrate with Storyblok headless CMS

## Why is it needed
This Magento 2 module provides a robust integration with Storyblok, a headless CMS, offering several key advantages:

* **Decoupled Content Management:** It separates content creation and management (in Storyblok) from the presentation layer (Magento). This allows content editors to work independently without needing deep Magento knowledge, and developers to build flexible, custom frontends.
* **Enhanced Content Editor Experience:** Storyblok's visual editor empowers content editors with a real-time preview of their changes, making content creation more intuitive and efficient.
* **Omnichannel Content Delivery:** By using a headless CMS, content can be delivered not only to the Magento storefront but also to other channels (e.g., mobile apps, other websites, etc.) from a single source.
* **Improved Performance:** Headless architecture can lead to faster website performance as Magento is freed from the responsibility of content rendering.
* **Scalability and Flexibility:** It provides greater scalability and flexibility, allowing for easier content model updates and the adoption of modern frontend technologies.
* **Future-Proof Architecture:** Decoupling content makes it easier to adapt to future technological changes and evolve the digital experience.

Essentially, this module empowers businesses to create richer, more dynamic content experiences while improving development workflows and overall agility.

---

## Installation
Install via composer:

```BASH
composer config repositories.wind-and-kite composer https://wind-and-kite.repo.repman.io
composer config --auth http-basic.wind-and-kite.repo.repman.io token <project_token>
composer require windandkite/module-storyblok
```

To create a new project token please login to Repman, select the correct organisation, navigate to the tokens area and
generate the token with an appropriate label.

Project specific tokens are used in this manner so that access to wind and kite repositories can be revoked if the need
arises. Never use the same token on multiple projects.

### Enable the module

You can enable the module by running one of the following commands

```BASH
bin/magento module:enable WindAndKite_{MODULE_NAME}
```

or

```BASH
bin/magento setup:upgrade
```

## Setup

// TODO: Add setup instructions

## Contributions
to contribute to this project please install the package with the `--prefer-source` flag to download the Git source, any
changes should then be pushed to GitHub and follow the usual Code Review Process. Once reviewed and merged in please
create a release and appropriate tag for the changes ensuring to follow Semver.
