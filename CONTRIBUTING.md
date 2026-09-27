# Contributing

Contributions are welcome, from a typo fix to a new carrier. This page covers
how to get the plugin running, how to check a change, and what a pull request
should look like. The [README](README.md) explains what the plugin is; read
[How it works](README.md#how-it-works) first, because most conventions below
follow from it.

## Setup

```sh
git clone https://github.com/mahoudeau/universal-shipping-for-sylius.git
cd universal-shipping-for-sylius
composer install
```

PHP 8.2 or later with the extensions Sylius needs (`intl`, `gd`, `exif`,
`fileinfo`, `sodium`).

## Checking a change

```sh
composer check      # coding standard, static analysis and tests, in that order
composer test       # tests only
composer cs-fix     # fix what the coding standard can fix on its own
```

Tests never reach the network. The Sendcloud and BAN providers are tested
against recorded answers in `tests/Fixtures/`, served by Symfony's
`MockHttpClient`. If you need a new fixture, record a real response and trim
it to the few points the test needs.

## Trying it in a shop

Unit tests cover the logic; the checkout itself needs a real Sylius. Point a
shop at your clone with a path repository:

```json
"repositories": [
    { "type": "path", "url": "../universal-shipping-for-sylius", "options": { "symlink": true } }
]
```

Then `composer require mahoudeau/universal-shipping-for-sylius:*@dev` and
follow the installation steps in the README. The `fake` provider lets you go
through the whole checkout without carrier credentials.

## Layout

```
src/Model/            The pickup point, delivery modes, and the traits shops add to their entities
src/DeliveryOption/   Delivery options declared in config, and the registry that resolves them
src/Provider/         The provider contract, the finder (cache, outages), and one folder per provider
src/Address/          The optional address module: provider contract, finder, the BAN, house numbers
src/Form/Extension/   The checkout picker and the admin field
src/Twig/             The map settings and the address suggestion URL, exposed to templates
templates/            Shop and admin templates, plugged in through Twig hooks
public/               The map and address scripts and styles, and vendored libraries (see public/vendor/README.md)
config/               Services, Twig hooks and routes
tests/Unit/           One test class per class, mirroring src/
```

## Conventions

- English in code, comments, commits and docs. User-facing text goes through
  translations, English and French at least.
- A new provider comes with tests against a recorded answer, like
  `SendcloudPickupPointProviderTest`.
- User-visible changes get a line in `CHANGELOG.md`, under **Unreleased**.
- No JavaScript unless the server really cannot do it. The map and the
  address suggestions are the two exceptions, and both only add to a form
  that works without them. Plain ES modules, no build step, no CDN.

## Pull requests

- One concern per pull request. A fix and a feature are two PRs.
- `composer check` passes.
- New behaviour comes with tests.
- Each commit builds, passes the tests, and is described in a sentence.

Not sure whether something would be welcome? Open an issue and ask before
building it. That costs a day of waiting; building the wrong thing costs a
week of work.
