<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Address;

use Mahoudeau\UniversalShipping\Address\AddressFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(AddressFinder::class)]
final class AddressFinderTest extends TestCase
{
    public function testSuggestionsAreCachedPerCountryAndQuery(): void
    {
        $provider = new FakeAddressProvider();
        $finder = new AddressFinder($provider, new ArrayAdapter());

        $finder->suggest('18 rue Francis', 'FR');
        $finder->suggest(' 18  RUE francis ', 'FR');
        $finder->suggest('18 rue Francis de', 'FR');

        self::assertSame(2, $provider->calls);
        self::assertSame('Marseille', $finder->suggest('18 rue Francis', 'FR')[0]->city ?? null);
    }

    public function testGeocodingIsCachedEvenWhenNothingMatches(): void
    {
        $provider = new FakeAddressProvider();
        $provider->match = null;
        $finder = new AddressFinder($provider, new ArrayAdapter());

        self::assertNull($finder->geocode('nowhere', 'FR'));
        self::assertNull($finder->geocode('nowhere', 'FR'));
        self::assertSame(1, $provider->calls);
    }

    public function testUnsupportedCountriesAndEmptyQueriesNeverReachTheProvider(): void
    {
        $provider = new FakeAddressProvider();
        $finder = new AddressFinder($provider, new ArrayAdapter());

        self::assertSame([], $finder->suggest('Kerkstraat 12', 'NL'));
        self::assertSame([], $finder->suggest('   ', 'FR'));
        self::assertNull($finder->geocode('Kerkstraat 12, Amsterdam', 'NL'));
        self::assertFalse($finder->supports('NL'));
        self::assertSame(0, $provider->calls);
    }

    public function testAnOutageGivesNothingAndALogLine(): void
    {
        $provider = new FakeAddressProvider();
        $provider->down = true;
        $logger = new class() extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->lines[] = $level . ': ' . $message;
            }
        };
        $finder = new AddressFinder($provider, new ArrayAdapter(), 86400, $logger);

        self::assertSame([], $finder->suggest('18 rue Francis', 'FR'));
        self::assertNull($finder->geocode('18 rue Francis de Pressensé, 13001 Marseille', 'FR'));
        self::assertSame([
            'warning: Address provider "fake" is unavailable: down',
            'warning: Address provider "fake" is unavailable: down',
        ], $logger->lines);

        $provider->down = false;
        self::assertCount(1, $finder->suggest('18 rue Francis', 'FR'), 'An outage is not cached');
    }
}
