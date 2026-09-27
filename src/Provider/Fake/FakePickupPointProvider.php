<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider\Fake;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Mahoudeau\UniversalShipping\Provider\AsPickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderInterface;
use Mahoudeau\UniversalShipping\Provider\PickupPointQuery;

/**
 * Always the same four points, whatever the address: for demos, tests and
 * developing a theme without carrier credentials.
 */
#[AsPickupPointProvider('fake')]
final class FakePickupPointProvider implements PickupPointProviderInterface
{
    private const POINTS = [
        ['FAKE01', 'Librairie du Canal', '12 quai de Valmy', '75010', 'Paris', 48.8702, 2.3632, 180],
        ['FAKE02', 'Épicerie Saint-Martin', '48 rue du Faubourg Saint-Martin', '75010', 'Paris', 48.8717, 2.3570, 420],
        ['FAKE03', 'Pressing des Récollets', '5 rue des Récollets', '75010', 'Paris', 48.8745, 2.3607, 650],
        ['FAKE04', 'Tabac de la Gare', '1 place du 11 Novembre 1918', '75010', 'Paris', 48.8765, 2.3590, 890],
    ];

    private const HOURS = [
        0 => ['09:00 - 19:00'],
        1 => ['09:00 - 19:00'],
        2 => ['09:00 - 19:00'],
        3 => ['09:00 - 19:00'],
        4 => ['09:00 - 12:30', '14:00 - 19:00'],
        5 => ['10:00 - 18:00'],
        6 => [],
    ];

    public function search(PickupPointQuery $query, DeliveryOption $option): array
    {
        return array_map(fn (array $point): PickupPoint => $this->build($point, $option), self::POINTS);
    }

    public function find(string $id, DeliveryOption $option): ?PickupPoint
    {
        foreach (self::POINTS as $point) {
            if ($point[0] === $id) {
                return $this->build($point, $option);
            }
        }

        return null;
    }

    /** @param array{string, string, string, string, string, float, float, int} $point */
    private function build(array $point, DeliveryOption $option): PickupPoint
    {
        return new PickupPoint(
            provider: 'fake',
            id: $point[0],
            carrier: $option->carrier,
            code: $point[0],
            name: $point[1],
            street: $point[2],
            postcode: $point[3],
            city: $point[4],
            countryCode: 'FR',
            latitude: $point[5],
            longitude: $point[6],
            openingHours: self::HOURS,
            distance: $point[7],
        );
    }
}
