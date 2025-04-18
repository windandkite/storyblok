# ![image logo](docs/favicon.svg) WindAndKite_{MODULE_NAME}}

A Magento 2 Module {Brief description of Module}

## Why is it needed
{Explanation for why the module is needed}

---

{For Private Module}
## Installation
Install via composer:

```BASH
composer config repositories.wind-and-kite composer https://wind-and-kite.repo.repman.io
composer config --auth http-basic.wind-and-kite.repo.repman.io token <project_token>
composer require windandkite/module-{package-name}
```

To create a new project token please login to Repman, select the correct organisation, navigate to the tokens area and
generate the token with an appropriate label.

Project specific tokens are used in this manner so that access to wind and kite repositories can be revoked if the need
arises. Never use the same token on multiple projects.

---

{For Public Module}
## Installation

### Install cia composer:

Our modules are hosted on our public packagist account and can very easily be installed via composer

```BASH
composer require windandkite/module-{package-name}
```

---

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
- {Setup Instructions if any}

## Contributions
to contribute to this project please install the package with the `--prefer-source` flag to download the Git source, any
changes should then be pushed to GitHub and follow the usual Code Review Process. Once reviewed and merged in please
create a release and appropriate tag for the changes ensuring to follow Semver.
