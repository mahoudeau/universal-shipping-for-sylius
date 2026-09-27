<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Twig;

use Mahoudeau\UniversalShipping\Twig\AddressExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(AddressExtension::class)]
final class AddressExtensionTest extends TestCase
{
    public function testTheScriptGetsTheSuggestUrlWhenAutocompleteIsOn(): void
    {
        self::assertSame('/universal-shipping/address/suggest', (new AddressExtension(true, $this->urls(withRoute: true)))->suggestUrl());
    }

    public function testNoUrlWhenAutocompleteIsOff(): void
    {
        self::assertNull((new AddressExtension(false, $this->urls(withRoute: true)))->suggestUrl());
    }

    public function testAMissingRouteImportLeavesTheCheckoutAsItWas(): void
    {
        self::assertNull((new AddressExtension(true, $this->urls(withRoute: false)))->suggestUrl());
    }

    public function testTemplatesCallItByName(): void
    {
        $names = array_map(static fn ($function): string => $function->getName(), (new AddressExtension(true, $this->urls(withRoute: true)))->getFunctions());

        self::assertSame(['universal_shipping_address_suggest_url'], $names);
    }

    private function urls(bool $withRoute): UrlGenerator
    {
        $routes = new RouteCollection();
        if ($withRoute) {
            $routes->add(AddressExtension::ROUTE, new Route('/universal-shipping/address/suggest'));
        }

        return new UrlGenerator($routes, new RequestContext());
    }
}
