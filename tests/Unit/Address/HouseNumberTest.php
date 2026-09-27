<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Address;

use Mahoudeau\UniversalShipping\Address\HouseNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HouseNumber::class)]
final class HouseNumberTest extends TestCase
{
    /** @param array{0: ?string, 1: string} $expected */
    #[DataProvider('lines')]
    public function testSplitsTheNumberOffAFrenchStreetLine(string $line, array $expected): void
    {
        self::assertSame($expected, HouseNumber::split($line));
    }

    /** @return iterable<string, array{string, array{0: ?string, 1: string}}> */
    public static function lines(): iterable
    {
        yield 'plain' => ['12 rue de la Paix', ['12', 'rue de la Paix']];
        yield 'bis, apart' => ['12 bis rue de la Paix', ['12 bis', 'rue de la Paix']];
        yield 'bis, attached' => ['12bis rue de la Paix', ['12bis', 'rue de la Paix']];
        yield 'ter, capitalised' => ['7 Ter Place du Marché', ['7 Ter', 'Place du Marché']];
        yield 'quater' => ['4 quater impasse des Lilas', ['4 quater', 'impasse des Lilas']];
        yield 'letter' => ['12B chemin des Oliviers', ['12B', 'chemin des Oliviers']];
        yield 'range' => ['3-5 avenue Foch', ['3-5', 'avenue Foch']];
        yield 'range with spaces' => ['3 - 5 avenue Foch', ['3-5', 'avenue Foch']];
        yield 'comma' => ['12, rue de la Paix', ['12', 'rue de la Paix']];
        yield 'extra spaces' => ['  12   rue  de la Paix ', ['12', 'rue de la Paix']];
        yield 'accented street' => ['1 Élysée Village', ['1', 'Élysée Village']];
        yield 'metric numbering' => ['1250 route de Grasse', ['1250', 'route de Grasse']];
    }

    #[DataProvider('unsplit')]
    public function testLeavesALineWithoutALeadingNumberWhole(string $line): void
    {
        self::assertSame([null, $line], HouseNumber::split($line));
    }

    /** @return iterable<string, array{string}> */
    public static function unsplit(): iterable
    {
        yield 'lieu-dit' => ['Lieu-dit Les Pins'];
        yield 'number at the end, Belgian style' => ['rue de la Paix 12'];
        yield 'ordinal' => ['1er étage, rue de la Paix'];
        yield 'number only' => ['12'];
        yield 'postcode typed in the street field' => ['13001 Marseille'];
        yield 'two numbers' => ['12 13 rue de la Paix'];
        yield 'post box' => ['BP 42'];
        yield 'bisous is not bis' => ['12bisous rue'];
        yield 'empty' => [''];
    }
}
