<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Twig;

use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Tells the checkout templates where the address autocomplete asks for suggestions.
 */
final class AddressExtension extends AbstractExtension
{
    public const ROUTE = 'universal_shipping_address_suggest';

    public function __construct(
        private readonly bool $autocomplete,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('universal_shipping_address_suggest_url', $this->suggestUrl(...)),
        ];
    }

    /**
     * Null when autocomplete is off, or when the shop hasn't imported the route yet:
     * the checkout then works as without the module, instead of failing on a missing route.
     */
    public function suggestUrl(): ?string
    {
        if (!$this->autocomplete) {
            return null;
        }

        try {
            return $this->urlGenerator->generate(self::ROUTE);
        } catch (RoutingException) {
            return null;
        }
    }
}
