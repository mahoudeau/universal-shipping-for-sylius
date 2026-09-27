<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Label;

/**
 * Why a label could not be made, printed or cancelled. The message is shown in the admin,
 * so it says what the carrier said, not a stack trace.
 */
final class LabelException extends \RuntimeException
{
    public static function because(string $provider, \Throwable $previous): self
    {
        return new self(sprintf('%s could not be reached: %s', $provider, $previous->getMessage()), 0, $previous);
    }
}
