# Universal Shipping for Sylius

[![CI](https://github.com/mahoudeau/universal-shipping-for-sylius/actions/workflows/ci.yaml/badge.svg)](https://github.com/mahoudeau/universal-shipping-for-sylius/actions/workflows/ci.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Shipping carriers for Sylius 2, starting with the part every French shop asks
for first: letting the customer pick a Mondial Relay point at checkout.

![Choosing a relay point at checkout](docs/images/checkout-picker.png)

Currently **0.1**, built alongside a small French shop that will be its first
production user. What changed, and when: [CHANGELOG.md](CHANGELOG.md).

> **Independent project.** Not affiliated with, endorsed by, or connected to
> Sylius, Sendcloud or Mondial Relay. "Sylius" is used here only to say what
> this plugin is for.

## What it does today

- **A pickup point picker in the checkout's shipping step.** Choose a method
  that needs a point and the nearest ones appear, with their address, distance
  and opening hours. Type another postcode and the list follows.
- **Mondial Relay through Sendcloud.** Relay points only, or lockers only, or
  both: one setting per delivery option.
- **The chosen point is kept on the shipment**, and shown on the checkout
  summary, the customer's order page and the admin order page, with the
  carrier's own point number for the drop-off.
- **An optional map** next to the list, drawn with MapLibre on OpenStreetMap
  data. Click a pin, the point is chosen. Free map sources only, and the
  checkout works the same without it.
- **A fake provider** with four fixed points, for demos, themes and tests
  without carrier credentials.
- **English and French** out of the box.

Labels and tracking come next. See the [roadmap](#roadmap).

## Why another shipping plugin

When this started, in September 2026, there was no maintained Mondial Relay
integration for Sylius 2. The existing plugins stop at Sylius 1, and the one
Sylius 2 plugin we tried did not work with the 2.2 checkout. French shops also
increasingly reach
Mondial Relay through Sendcloud rather than the carrier's own API, which none
of them covered.

So this plugin is carrier-neutral from the start. Mondial Relay through
Sendcloud is the first provider, not the whole design. Adding a carrier means
writing one class.

## How it works

A few choices worth knowing before you install it:

- **The picker needs no JavaScript.** Sylius 2 renders the shipping step as a
  live component, so the picker is a plain Symfony form that re-renders on the
  server. Choosing a method, searching an address and picking a point all
  work without a line of custom JS. The optional map is the only script, and
  it only mirrors the list.
- **The list of points is always built on the server.** The browser only ever
  sends back the id of a point from that list, so a customer cannot forge a
  point or an address.
- **The point is copied onto the shipment, not referenced.** The address on
  the order stays what the customer chose, even if the carrier moves or closes
  the point later. A saved point is checked again with the carrier before it
  is offered a second time.
- **A carrier outage never breaks the checkout.** Searches are cached (the
  checkout re-renders on every change), providers are only loaded when a
  method needs them, and a failing carrier shows "no point found" and a log
  line instead of an error page.

One more thing, found on the way: Sylius declares its shipping step as a live
component but its template never switches it on in the browser. This plugin
replaces that one template with a fixed copy. It is the only core template it
touches.

## Requirements

- PHP 8.2 or later
- Sylius 2.1 or later (developed and used on 2.2)
- A Sendcloud account with an API integration, for the Sendcloud provider

## Installation

**1. Require the package**

```sh
composer require mahoudeau/universal-shipping-for-sylius
```

**2. Enable the bundle** in `config/bundles.php`, if Flex did not:

```php
Mahoudeau\UniversalShipping\UniversalShippingPlugin::class => ['all' => true],
```

**3. Add the fields to your entities.** The point lives on the shipment, the
delivery option on the shipping method.

```php
// src/Entity/Shipping/Shipment.php
use Mahoudeau\UniversalShipping\Model\PickupPointAwareInterface;
use Mahoudeau\UniversalShipping\Model\PickupPointAwareTrait;

class Shipment extends BaseShipment implements PickupPointAwareInterface
{
    use PickupPointAwareTrait;
}
```

```php
// src/Entity/Shipping/ShippingMethod.php
use Mahoudeau\UniversalShipping\Model\DeliveryOptionAwareInterface;
use Mahoudeau\UniversalShipping\Model\DeliveryOptionAwareTrait;

class ShippingMethod extends BaseShippingMethod implements DeliveryOptionAwareInterface
{
    use DeliveryOptionAwareTrait;
    // ...
}
```

Then generate and run the migration. It adds two nullable columns,
`sylius_shipment.universal_shipping_pickup_point` and
`sylius_shipping_method.universal_shipping_delivery_option`.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

**4. Declare your delivery options** in `config/packages/universal_shipping.yaml`.
A delivery option is one way of delivering with one carrier. The Sylius
shipping method on top of it adds the price, the zone and the name customers
see.

```yaml
universal_shipping:
    sendcloud:
        public_key: '%env(SENDCLOUD_PUBLIC_KEY)%'
        secret_key: '%env(SENDCLOUD_SECRET_KEY)%'
    delivery_options:
        mondial_relay_point_relais:
            label: 'Mondial Relay, relay point'
            provider: sendcloud
            carrier: mondial_relay
            delivery: pickup_point
            options:
                point_types: [servicepoint]   # Sendcloud also returns lockers
```

The keys come from the Sendcloud panel: **Settings > Connected stores >
Sendcloud API**. Turn on pickup point delivery there and tick the carriers you
want. Keep the keys out of the repository.

**5. Link a shipping method** in the admin: edit it and choose the delivery
option in the **Universal Shipping** field. That method now asks for a point
at checkout.

![The Universal Shipping field on a shipping method](docs/images/admin-shipping-method.png)

## Configuration reference

```yaml
universal_shipping:
    cache_ttl: 600                    # seconds a search stays cached
    sendcloud:
        public_key: ''
        secret_key: ''
        service_points_url: 'https://servicepoints.sendcloud.sc/api/v2'
    delivery_options:
        <code>:                       # what the admin links a method to
            label: ~                  # shown in the admin
            provider: ~               # sendcloud, fake, or your own
            carrier: ~                # e.g. mondial_relay
            delivery: pickup_point    # or home
            options: {}               # passed to the provider as is
```

Sendcloud options:

| Option | Default | Meaning |
|---|---|---|
| `point_types` | all | `servicepoint` for relay points, `locker` for lockers |
| `radius` | 5000 | search radius in metres |
| `limit` | 10 | how many points the customer sees |

To develop or test without credentials, point a delivery option at the fake
provider:

```yaml
when@test:
    universal_shipping:
        delivery_options:
            mondial_relay_point_relais:
                label: 'Mondial Relay (fake)'
                provider: fake
                carrier: mondial_relay
```

## The map

Off by default. Turn it on and pick where the map comes from:

```yaml
universal_shipping:
    map:
        enabled: true
        style: 'https://tiles.openfreemap.org/styles/positron'   # the default
```

`style` is the URL of any MapLibre style. Three free sources work well:

| Source | `style` | Notes |
|---|---|---|
| [OpenFreeMap](https://openfreemap.org) | `https://tiles.openfreemap.org/styles/positron` (or `liberty`, `bright`) | OpenStreetMap data, worldwide, no key, no account. The default |
| [IGN Géoplateforme](https://geoservices.ign.fr) | `https://data.geopf.fr/annexes/ressources/vectorTiles/styles/PLAN.IGN/standard.json` | The French state's map, open licence, no key. Best in France, thin elsewhere |
| [Protomaps](https://protomaps.com), self-hosted | a style whose source is `pmtiles://https://your-server/france.pmtiles` | One file on your own server or object storage: no third party at all |

MapLibre and PMTiles ship with the plugin (both BSD-3), so no script comes
from a CDN. MapLibre is only downloaded on a page that shows a map.

**Privacy.** With OpenFreeMap or IGN, the customer's browser loads map tiles
from their servers, so they see the customer's IP address. Say so in your
privacy policy, or self-host with Protomaps. The pickup point list itself
never talks to a third party from the browser: carrier calls happen on your
server.

**Theming.** Give the map your shop's colours:

```yaml
universal_shipping:
    map:
        theme:
            accent: '#9c4a2a'          # pins, and the chosen pin
            pin_text: '#fbf8f3'        # number on the chosen pin
            colors:                    # the map itself
                background: '#f5f0e8'
                buildings: '#eae1d3'
                roads: '#fbf8f3'
                parks: '#e4e7d8'
                water: '#d5ddd8'
                labels: '#6e604f'
```

Every key is optional: leave one out and the style keeps its own colour.
Colours must be `#rgb` or `#rrggbb`. The map colours apply to OpenMapTiles
styles, which covers OpenFreeMap and most free styles; other styles are only
partly recoloured. Street names keep the style's font, since the tile server
only has its own. For full control, design a style in
[Maputnik](https://maplibre.org/maputnik/) and point `style` at it.

## Adding a carrier

Implement `PickupPointProviderInterface` and give it a code:

```php
use Mahoudeau\UniversalShipping\Provider\AsPickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderInterface;

#[AsPickupPointProvider('my_carrier')]
final class MyCarrierProvider implements PickupPointProviderInterface
{
    public function search(PickupPointQuery $query, DeliveryOption $option): array { /* ... */ }

    public function find(string $id, DeliveryOption $option): ?PickupPoint { /* ... */ }
}
```

Autoconfiguration registers it. Throw `ProviderUnavailableException` when the
carrier cannot be reached, and the plugin takes care of the rest. The
[Sendcloud provider](src/Provider/Sendcloud/SendcloudPickupPointProvider.php)
is a complete example.

![The chosen point on the admin order page](docs/images/admin-order.png)

## Roadmap

Roughly in this order. Nothing here is promised by a date.

1. **Labels and tracking through Sendcloud**: create the parcel from the
   admin, print the label, store the tracking number on the shipment.
2. **Colissimo**, home delivery and point retrait.
3. **Home delivery and lockers** as first-class delivery options.
4. **Better addresses**: autocomplete and geocoding with the French national
   address base (BAN), Photon for other countries.
5. **A shop API endpoint** for headless checkouts.
6. **Functional tests** of the whole checkout in a Sylius test application.

Want one of these sooner, or a carrier that is not on the list? Open an issue.

## Contributing

Contributions are welcome, from a typo to a carrier. See
[CONTRIBUTING.md](CONTRIBUTING.md) for how to run the checks and what a pull
request should look like.

## License

MIT. See [LICENSE](LICENSE).
