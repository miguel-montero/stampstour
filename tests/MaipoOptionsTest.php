<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaipoOptionsTest extends TestCase
{
    public function testThreeOptionsInDisplayOrder(): void
    {
        $this->assertSame(['classic', 'classic-lunch', 'premium'], array_keys(maipo_options()));
    }

    public function testExpNamesMatchDatabaseRows(): void
    {
        $this->assertSame(
            ['classic' => 'MAIPO_CLASSIC', 'classic-lunch' => 'MAIPO_CLASSIC_LUNCH', 'premium' => 'Maipo'],
            maipo_option_exp_names()
        );
    }

    #[DataProvider('resolveCases')]
    public function testResolveOption($raw, string $expected): void
    {
        $this->assertSame($expected, maipo_resolve_option($raw));
    }

    public static function resolveCases(): array
    {
        return [
            'exact'          => ['premium', 'premium'],
            'uppercase'      => ['PREMIUM', 'premium'],
            'padded'         => ['  classic-lunch ', 'classic-lunch'],
            'missing'        => [null, 'classic'],
            'empty'          => ['', 'classic'],
            'unknown'        => ['deluxe', 'classic'],
            'array param'    => [['premium'], 'classic'],
            'markup'         => ['<script>alert(1)</script>', 'classic'],
        ];
    }

    public function testPublicNamesRouteToTheRightAdminReportCategory(): void
    {
        $opts = maipo_options();
        $lunch = mb_strtolower($opts['classic-lunch']['public_name']);
        $this->assertStringContainsString('tastings & lunch', $lunch);
        $this->assertStringContainsString('classic maipo valley', $lunch);

        foreach (['classic', 'premium'] as $slug) {
            $name = mb_strtolower($opts[$slug]['public_name']);
            $this->assertStringContainsString('maipo', $name);
            foreach (['tasting', 'lunch', 'almuerzo', 'classic maipo valley', 'pvt', 'private'] as $banned) {
                $this->assertStringNotContainsString($banned, $name, "$slug public name must not contain '$banned'");
            }
        }
    }

    public function testFinalStopAndLunchPerOption(): void
    {
        $opts = maipo_options();
        $finalTitle = fn(array $o) => $o['stops'][count($o['stops']) - 2]['title']; // last stop before return
        $this->assertSame('Viña Ballek', $finalTitle($opts['classic']));
        $this->assertSame('Viña Ballek', $finalTitle($opts['classic-lunch']));
        $this->assertSame('Viña Undurraga', $finalTitle($opts['premium']));
        $this->assertFalse($opts['classic']['lunch_included']);
        $this->assertTrue($opts['classic-lunch']['lunch_included']);
        $this->assertFalse($opts['premium']['lunch_included']);
    }

    public function testWineriesAreQuirincaSantaEmaTerraMaterThenFinal(): void
    {
        foreach (maipo_options() as $slug => $o) {
            $titles = array_column($o['stops'], 'title');
            $this->assertSame(['Campo La Quirinca', 'Viña Santa Ema', 'Viña TerraMater'], array_slice($titles, 1, 3), $slug);
        }
    }

    public function testLunchVenueIsNeverNamed(): void
    {
        $text = mb_strtolower(json_encode(maipo_options(), JSON_UNESCAPED_UNICODE));
        foreach (['zinfandel', 'labranza', 'own food'] as $name) {
            $this->assertStringNotContainsString($name, $text);
        }
        foreach (maipo_options() as $slug => $o) {
            $this->assertStringNotContainsString('lunch', mb_strtolower($o['stops'][3]['text']), "$slug: TerraMater stop must not mention lunch");
            foreach (array_merge($o['included'], $o['not_included'], [$o['tagline']]) as $line) {
                if (stripos($line, 'lunch') !== false) {
                    $this->assertStringContainsString('winery restaurant', $line, "$slug: '$line'");
                }
            }
        }
    }

    public function testEveryOptionHasCompleteContent(): void
    {
        foreach (maipo_options() as $slug => $o) {
            foreach (['exp_name', 'public_name', 'label', 'tagline'] as $key) {
                $this->assertNotSame('', trim($o[$key]), "$slug.$key");
            }
            $this->assertCount(6, $o['stops'], "$slug has pickup + 4 stops + return");
            foreach ($o['stops'] as $stop) {
                $this->assertSame(['place', 'title', 'duration', 'icon', 'text'], array_keys($stop));
                $this->assertNotSame('', $stop['title']);
                $this->assertNotSame('', $stop['text']);
            }
            $this->assertNotEmpty($o['included']);
            $this->assertNotEmpty($o['not_included']);
        }
    }
}
