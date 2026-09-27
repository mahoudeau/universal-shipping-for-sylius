<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Address;

/**
 * Splits the house number off a street line written the French way, number first:
 * "12 bis rue de la Paix" is "12 bis" and "rue de la Paix".
 *
 * Only a number at the start counts. A line with the number at the end ("rue de la
 * Paix 12", the Belgian way) or no number at all ("Lieu-dit Les Pins") stays whole:
 * a carrier reads a whole line better than a wrong split.
 */
final class HouseNumber
{
    /**
     * A number of one to four digits, or a range ("3-5"), then an optional
     * repetition suffix, attached ("12B", "12bis") or apart ("12 bis"). Then
     * a comma or spaces, and a street that starts with a letter.
     */
    private const PATTERN = '/^
        (?<number>
            \d{1,4}(?:\s*-\s*\d{1,4})?
            (?:\s*(?:bis|ter|quater|quinquies)\b|[a-z]\b)?
        )
        (?:\s*,\s*|\s+)
        (?<street>\p{L}.*)
    $/xiu';

    /** @return array{0: ?string, 1: string} the number, or null when there is none, and the street */
    public static function split(string $line): array
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $line));

        if (1 !== preg_match(self::PATTERN, $line, $matches)) {
            return [null, $line];
        }

        $number = (string) preg_replace('/\s*-\s*/', '-', $matches['number']);

        return [$number, trim($matches['street'])];
    }
}
