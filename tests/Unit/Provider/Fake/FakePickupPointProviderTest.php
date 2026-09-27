<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Provider\Fake;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Mahoudeau\UniversalShipping\Provider\Fake\FakePickupPointProvider;
use Mahoudeau\UniversalShipping\Provider\PickupPointQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FakePickupPointProvider::class)]
final class FakePickupPointProviderTest extends TestCase
{
    public function testItAlwaysReturnsTheSamePointsInTheSameOrder(): void
    {
        $provider = new FakePickupPointProvider();
        $option = new DeliveryOption('relay', 'Relay', 'fake', 'mondial_relay', DeliveryMode::PickupPoint);

        $first = $provider->search(new PickupPointQuery('FR', 'Marseille'), $option);
        $second = $provider->search(new PickupPointQuery('BE', 'Bruxelles'), $option);

        self::assertEquals($first, $second);
        self::assertSame(['FAKE01', 'FAKE02', 'FAKE03', 'FAKE04'], array_map(static fn ($point) => $point->id, $first));
        self::assertSame('mondial_relay', $first[0]->carrier, 'Takes the carrier of the option it stands in for');
        self::assertSame('Épicerie Saint-Martin', $provider->find('FAKE02', $option)?->name);
        self::assertNull($provider->find('FAKE99', $option));
    }
}
