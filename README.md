# Wind & Kite Storyblok packages

The development repo for the Wind & Kite Storyblok integration for Magento. Every pull request goes here.

Each folder in `packages/` is a Composer package, published on every push to `master` (and on every tag) to its own read-only repo, which Packagist installs from:

| Package | Repo |
| --- | --- |
| [`packages/module-storyblok`](packages/module-storyblok) | [windandkite/module-storyblok](https://github.com/windandkite/module-storyblok) |
| [`packages/module-storyblok-components-common`](packages/module-storyblok-components-common) | [windandkite/module-storyblok-components-common](https://github.com/windandkite/module-storyblok-components-common) |
| [`packages/module-storyblok-hyva-components`](packages/module-storyblok-hyva-components) | [windandkite/module-storyblok-hyva-components](https://github.com/windandkite/module-storyblok-hyva-components) |

Don't open pull requests on the package repos: changes there are overwritten by the next publish.

## Working on the packages in a store

Add the checkout as a Composer path repository in the store, then require the packages you're working on:

```json
"repositories": [
    {"type": "path", "url": "/path/to/storyblok/packages/*", "options": {"symlink": true}}
]
```

```bash
composer require windandkite/module-storyblok:@dev
```

## Releases

Every package shares one version. Tag `master` (e.g. `2.2.0`) and the tag is pushed to every package repo.

## Documentation

The [wiki](https://github.com/windandkite/storyblok/wiki) is written in [`docs/wiki`](docs/wiki) and published when it changes on `master`.

## License

[Mozilla Public License 2.0](LICENSE). Changes to files in this repo must be shared under the same license when distributed; modules that only build on them can use any license. Provided without warranty.
