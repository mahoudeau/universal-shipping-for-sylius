<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Provider;

final class ProviderUnavailableException extends \RuntimeException
{
    public static function because(string $provider, \Throwable $previous): self
    {
        return new self(sprintf('Pickup point provider "%s" is unavailable: %s', $provider, $previous->getMessage()), 0, $previous);
    }
}
