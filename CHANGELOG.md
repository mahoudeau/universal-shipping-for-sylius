# Changelog

Every release of Universal Shipping for Sylius, newest first. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions
follow [semantic versioning](https://semver.org/).

## Unreleased

### Added

- An optional map next to the pickup point list, drawn with MapLibre GL JS
  6.11.2. Pins are numbered like the list, and clicking one chooses the point.
  Any MapLibre style works: OpenFreeMap by default, IGN, or a self-hosted
  Protomaps file (PMTiles 4.5.0 ships with the plugin). Off by default
- Map theming in config: pin colours, and the map's own background, water,
  parks, roads, buildings and labels
- Labels from the admin order page: create, print and cancel, through
  Sendcloud's shipments API v3. The tracking number goes into Sylius's own
  tracking field, so the "shipped" email carries it
- `sendcloud.test_labels`, which makes every label Sendcloud's free
  "Unstamped letter", for development and staging
- Tracking by Sendcloud webhook, signed and checked, with statuses shown on
  the admin order page. Late updates never overwrite newer ones
- `LabelProviderInterface` and `ParcelTracker`, so other carriers can add
  labels and tracking the same way
- Fake labels: a made-up tracking number and a PDF marked as a test, with no
  carrier involved. `label_provider` on a delivery option mixes real points
  with fake labels, and `universal-shipping:fake-tracking` moves a fake parcel
  along as a carrier would

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
