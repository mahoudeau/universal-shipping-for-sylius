<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Provider;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\Model\DeliveryMode;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Mahoudeau\UniversalShipping\Provider\PickupPointFinder;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderInterface;
use Mahoudeau\UniversalShipping\Provider\PickupPointProviderRegistry;
use Mahoudeau\UniversalShipping\Provider\PickupPointQuery;
use Mahoudeau\UniversalShipping\Provider\ProviderUnavailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(PickupPointFinder::class)]
#[CoversClass(PickupPointProviderRegistry::class)]
final class PickupPointFinderTest extends TestCase
{
    public function testSearchesAreCachedPerOptionAndAddress(): void
    {
        $provider = $this->countingProvider();
        $finder = $this->finder($provider);

        $finder->search(new PickupPointQuery('FR', '13001 Marseille'), $this->option());
        $finder->search(new PickupPointQuery('FR', ' 13001 MARSEILLE '), $this->option());
        $finder->search(new PickupPointQuery('FR', '75010 Paris'), $this->option());

        self::assertSame(2, $provider->searches);
    }

    public function testAnEmptyQueryNeverReachesTheCarrier(): void
    {
        $provider = $this->countingProvider();

        self::assertSame([], $this->finder($provider)->search(new PickupPointQuery('FR', '  '), $this->option()));
        self::assertSame(0, $provider->searches);
    }

    public function testACarrierOutageGivesNoPointsAndALogLine(): void
    {
        $provider = new class() implements PickupPointProviderInterface {
            public function search(PickupPointQuery $query, DeliveryOption $option): array
            {
                throw new ProviderUnavailableException('Sendcloud is down');
            }

            public function find(string $id, DeliveryOption $option): ?PickupPoint
            {
                throw new ProviderUnavailableException('Sendcloud is down');
            }
        };
        $logger = new class() extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->lines[] = $level . ': ' . $message;
            }
        };

        $finder = new PickupPointFinder($this->registry($provider), new ArrayAdapter(), 600, $logger);

        self::assertSame([], $finder->search(new PickupPointQuery('FR', 'Marseille'), $this->option()));
        self::assertNull($finder->find('123', $this->option()));
        self::assertSame(['warning: Sendcloud is down', 'warning: Sendcloud is down'], $logger->lines);
    }

    public function testFindDropsTheDistance(): void
    {
        self::assertNull($this->finder($this->countingProvider())->find('A', $this->option())?->distance);
    }

    public function testAnOptionWithAnUnknownProviderIsAConfigurationError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('uses provider "sendcloud", which is not registered');

        (new PickupPointProviderRegistry(new ServiceLocator([])))->forOption($this->option());
    }

    private function finder(PickupPointProviderInterface $provider): PickupPointFinder
    {
        return new PickupPointFinder($this->registry($provider), new ArrayAdapter());
    }

    private function registry(PickupPointProviderInterface $provider): PickupPointProviderRegistry
    {
        return new PickupPointProviderRegistry(new ServiceLocator(['sendcloud' => static fn () => $provider]));
    }

    private function option(): DeliveryOption
    {
        return new DeliveryOption('mondial_relay', 'Mondial Relay', 'sendcloud', 'mondial_relay', DeliveryMode::PickupPoint);
    }

    private function countingProvider(): CountingPickupPointProvider
    {
        return new CountingPickupPointProvider();
    }
}
