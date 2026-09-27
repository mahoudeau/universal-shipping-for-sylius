# Changelog

Every release of Universal Shipping for Sylius, newest first. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions
follow [semantic versioning](https://semver.org/).

## Unreleased

## 0.1.0 · 27 September 2026

**Pick a Mondial Relay point at checkout**

### Added

- A pickup point picker in the checkout's shipping step: nearest points with
  address, distance and opening hours, and a search by postcode or address.
  No JavaScript of its own
- Mondial Relay through Sendcloud, with relay points, lockers or both
- A fake provider with four fixed points, for demos and tests
- The chosen point on the checkout summary, the customer's order page and the
  admin order page, with the carrier's point number
- Delivery options declared in config, and a Universal Shipping field on
  shipping methods in the admin to link them
- English and French translations

### Fixed

- Sylius's shipping step is declared as a live component but never switched on
  in the browser. The plugin's template for that step turns it on
