# Changelog

Every release of Universal Shipping for Sylius, newest first. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions
follow [semantic versioning](https://semver.org/).

## Unreleased

Nothing yet.

## 0.4.0 · 29 September 2026

**No second paid label, and a checkout the keyboard can use**

Labels are still covered by unit tests only, not yet run against the real
Sendcloud. Upgrading: nothing to change. Shops using `sylius/refund-plugin`
can add the few lines in the README ("Partly refunded orders") so refunded
pieces leave the label.

### Added

- `RefundedAmountProviderInterface`: what was refunded per order item unit.
  A unit refunded in full leaves the label's weight, and every refund comes
  off the declared and insured value. The default refunds nothing; a shop
  with `sylius/refund-plugin` plugs it in with a few lines (README, "Partly
  refunded orders").

### Fixed

- A Sendcloud label is no longer paid twice when the first try's answer is
  lost (a timeout after Sendcloud created it). Before announcing, the
  provider looks for a live shipment with the same order number and
  reference, and takes it. A cancelled or failed one doesn't count.
- The pickup point map no longer puts keyboard users on controls a screen
  reader can't see: it is `aria-hidden`, so its canvas, zoom buttons,
  attribution links and pins leave the tab order. Mouse and touch work as
  before; the list stays the control.
- The pickup point fieldset's legend is its first child, so it names the
  group.
- The street field says it's `address-line1` instead of turning
  autocomplete off, so browsers can fill a saved address (WCAG 1.3.5).

## 0.3.1 · 29 September 2026

### Fixed

- The coding standard check failed on 0.3.0 (a missing blank line between
  two constants). No change in behaviour.

## 0.3.0 · 29 September 2026

**Insured parcels, and partly refunded orders ship**

Like 0.2.0, labels are covered by unit tests but haven't run against the real
Sendcloud yet.

### Added

- Sendcloud's additional insurance, per delivery option. `insure_above` (in
  euros) turns it on for orders above that total, and `carrier_cover` (in
  euros, 0 by default) takes off what the carrier already covers: 25 for
  Mondial Relay. The amount goes out as `additional_insured_price`, within the
  2 to 5000 euros Sendcloud accepts. Sendcloud charges it per label. Off
  without `insure_above`, and never on test labels

### Changed

- An order with part of its payment refunded can still get a label, so the
  items left in it can go out. A fully refunded order still can't

## 0.2.0 · 28 September 2026

**Labels, tracking, a map and French addresses**

Labels and tracking are covered by unit tests but haven't run against the real
Sendcloud yet: see the Status section of the README, and issues
[#1](https://github.com/mahoudeau/universal-shipping-for-sylius/issues/1) and
[#2](https://github.com/mahoudeau/universal-shipping-for-sylius/issues/2).

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
- An optional address module on the Base Adresse Nationale (BAN), through the
  IGN Géoplateforme. Off by default. It covers metropolitan France and the
  five overseas departments
- Address suggestions under the street fields of the checkout's address step:
  an accessible combobox that fills street, postcode and city. The browser
  only talks to the shop, through the new shop route
  `/universal-shipping/address/suggest` (`config/routes/shop.yaml`)
- With the address module, the pickup point search is centred on the
  geocoded address, and falls back to the address as text when geocoding
  finds nothing or fails
- `AddressProviderInterface`, so another address source can be plugged in
- `PickupPointQuery` takes optional coordinates. Existing providers are
  unaffected

### Changed

- Sendcloud labels send the house number apart from the street
  (`house_number`), split off the start of the street line: "12 bis rue de la
  Paix" gives "12 bis" and "rue de la Paix". Lines without a leading number
  are sent whole, as before

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
