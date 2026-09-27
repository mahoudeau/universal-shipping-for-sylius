<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Tests\Unit\Twig;

use Mahoudeau\UniversalShipping\DependencyInjection\Configuration;
use Mahoudeau\UniversalShipping\Twig\MapExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(MapExtension::class)]
#[CoversClass(Configuration::class)]
final class MapExtensionTest extends TestCase
{
    public function testTheMapIsOffByDefaultUsesOpenFreeMapAndKeepsTheStyleColours(): void
    {
        $map = $this->process([])['map'];

        self::assertFalse($map['enabled']);
        self::assertSame(Configuration::DEFAULT_MAP_STYLE, $map['style']);
        self::assertStringStartsWith('https://tiles.openfreemap.org/', $map['style']);
        self::assertNull($map['theme']['accent']);
        self::assertSame(
            ['background' => null, 'water' => null, 'parks' => null, 'roads' => null, 'buildings' => null, 'labels' => null],
            $map['theme']['colors'],
        );
    }

    public function testThemeColoursAreKeptAsGiven(): void
    {
        $theme = $this->process(['map' => ['theme' => [
            'accent' => '#9c4a2a',
            'pin_text' => '#fff',
            'colors' => ['background' => '#F5F0E8'],
        ]]])['map']['theme'];

        self::assertSame('#9c4a2a', $theme['accent']);
        self::assertSame('#fff', $theme['pin_text']);
        self::assertSame('#F5F0E8', $theme['colors']['background']);
        self::assertNull($theme['colors']['water']);
    }

    #[DataProvider('notColours')]
    public function testAnythingButAHexColourIsRefused(string $value): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('is not a colour');

        $this->process(['map' => ['theme' => ['colors' => ['water' => $value]]]]);
    }

    /** @return iterable<string, array{string}> */
    public static function notColours(): iterable
    {
        yield 'a name' => ['blue'];
        yield 'no hash' => ['9c4a2a'];
        yield 'wrong length' => ['#9c4a2'];
        yield 'css injection' => ['#fff; background: url(x)'];
    }

    public function testTemplatesReadTheMapSettingsFromOneGlobal(): void
    {
        $map = $this->process(['map' => ['enabled' => true]])['map'];

        self::assertSame(['universal_shipping_map' => $map], (new MapExtension($map))->getGlobals());
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{map: array{enabled: bool, style: string, theme: array{accent: ?string, pin_text: ?string, colors: array<string, ?string>}}}
     */
    private function process(array $config): array
    {
        /** @var array{map: array{enabled: bool, style: string, theme: array{accent: ?string, pin_text: ?string, colors: array<string, ?string>}}} $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(), [$config]);

        return $processed;
    }
}
