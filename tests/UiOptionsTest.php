<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UiOptionsTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return (string)ob_get_clean();
    }

    private function prices(): array
    {
        return ['classic' => ['adult' => 110.0], 'premium' => ['adult' => 139.0]]; // classic-lunch unpriced
    }

    private function labels(): array
    {
        return ['classic' => 'Classic Route', 'classic-lunch' => 'Classic Route + Lunch', 'premium' => 'Premium Route'];
    }

    public function testCardsKeepTheSelectorContract(): void
    {
        $html = $this->capture(fn() => ui_option_cards(maipo_options(), $this->prices(), 'premium', 'tour_option', 'Choose your route'));
        $this->assertMatchesRegularExpression('/<fieldset [^>]*data-tour-options>/', $html);
        $this->assertStringContainsString('<legend class="tw:sr-only">Choose your route</legend>', $html);
        $this->assertStringContainsString('<label for="tour_option-premium"', $html);
        $this->assertStringContainsString('name="tour_option" id="tour_option-premium" value="premium" data-booking-lock checked>', $html);
        $this->assertStringContainsString('name="tour_option" id="tour_option-classic" value="classic" data-booking-lock>', $html);
        $this->assertStringContainsString('value="classic-lunch" data-booking-lock disabled>', $html);
        $this->assertStringContainsString('Contact us', $html);
        $this->assertStringContainsString('$139', $html);
        $this->assertStringContainsString('/ adult', $html);
        $this->assertSame(3, substr_count($html, 'data-booking-lock'));
        $this->assertSame(3, substr_count($html, '<label '));
    }

    public function testSegmentedVariant(): void
    {
        $html = $this->capture(fn() => ui_option_cards(maipo_options(), $this->prices(), 'classic', 'tour_option_itin', 'Show itinerary for', 'segmented'));
        $this->assertStringContainsString('<legend class="lab-label">Show itinerary for</legend>', $html);
        $this->assertStringContainsString('tw:grid-cols-3', $html);
        $this->assertSame(3, substr_count($html, 'name="tour_option_itin"'));
        $this->assertStringContainsString('id="tour_option_itin-classic" value="classic" data-booking-lock checked>', $html);
        $this->assertStringNotContainsString('/ adult', $html);
        $this->assertStringContainsString('Contact us', $html);
    }

    public function testOptionCardsEscape(): void
    {
        $opts = ['x' => array_merge(maipo_options()['classic'], ['label' => '<b>x</b>', 'tagline' => 'a&b'])];
        $html = $this->capture(fn() => ui_option_cards($opts, [], 'x', 'n', 'L'));
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('a&amp;b', $html);
    }

    public function testItineraryBlocksAndTimeline(): void
    {
        $html = $this->capture(fn() => ui_itinerary(maipo_options(), $this->prices(), 'premium'));
        $this->assertSame(3, substr_count($html, 'name="tour_option_itin"'));
        $this->assertStringContainsString('<div data-option="premium">', $html);
        $this->assertStringContainsString('<div data-option="classic" hidden>', $html);
        $this->assertStringContainsString('<div data-option="classic-lunch" hidden>', $html);
        $this->assertSame(3, substr_count($html, '<ol class="lab-timeline'));
        $this->assertSame(18, substr_count($html, '<li class="lab-stop'));
        $this->assertStringContainsString('1 hour 30 minutes · Isla de Maipo', $html);
        $this->assertMatchesRegularExpression('#<h3 [^>]*>Viña Undurraga</h3>#', $html);
        $this->assertMatchesRegularExpression('#<h3 [^>]*>Viña Ballek</h3>#', $html);
        $this->assertMatchesRegularExpression('#<p [^>]*>Santiago</p>\s*<h3 [^>]*>Hotel pickup</h3>#', $html);
    }

    public function testStickyBarShowsSelectedOption(): void
    {
        $html = $this->capture(fn() => ui_sticky_bar($this->labels(), $this->prices(), 'premium'));
        $this->assertStringContainsString('class="lab-sticky" data-sticky-cta data-target="#book"', $html);
        $this->assertStringContainsString('<span data-sticky-label>Premium Route</span>', $html);
        $this->assertMatchesRegularExpression('#<strong [^>]*data-sticky-price>\$139</strong>#', $html);
        $this->assertStringContainsString('href="#book"', $html);
        preg_match('/data-labels="([^"]*)"/', $html, $l);
        preg_match('/data-prices="([^"]*)"/', $html, $p);
        $this->assertSame($this->labels(), json_decode(html_entity_decode($l[1]), true));
        $this->assertEquals(['classic' => 110, 'premium' => 139], json_decode(html_entity_decode($p[1]), true));
    }

    public function testStickyBarFallbacks(): void
    {
        $unpriced = $this->capture(fn() => ui_sticky_bar($this->labels(), $this->prices(), 'classic-lunch'));
        $this->assertStringContainsString('<span data-sticky-label>From</span>', $unpriced);
        $this->assertMatchesRegularExpression('#data-sticky-price>\$110</strong>#', $unpriced);

        $none = $this->capture(fn() => ui_sticky_bar($this->labels(), [], 'classic'));
        $this->assertStringNotContainsString('data-sticky-price', $none);
        $this->assertStringContainsString('Check availability', $none);
    }
}
