<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OptionUiTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return (string)ob_get_clean();
    }

    private function prices(): array
    {
        return ['classic' => ['adult' => 110.0], 'premium' => ['adult' => 139.0]]; // classic-lunch missing
    }

    public function testSelectorChecksSelectedAndDisablesUnpriced(): void
    {
        $html = $this->capture(fn() => render_option_selector(maipo_options(), $this->prices(), 'premium', 'tour_option', 'Choose your tour'));
        $this->assertStringContainsString('data-tour-options', $html);
        $this->assertMatchesRegularExpression('/value="premium"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="classic"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/value="classic-lunch"[^>]*disabled/', $html);
        $this->assertStringContainsString('Contact us', $html);
        $this->assertStringContainsString('$139', $html);
        $this->assertSame(3, substr_count($html, 'data-booking-lock'));
    }

    public function testItineraryHiddenUnlessSelected(): void
    {
        $opts = maipo_options();
        $visible = $this->capture(fn() => render_option_itinerary('premium', $opts['premium'], 'premium'));
        $hidden = $this->capture(fn() => render_option_itinerary('classic', $opts['classic'], 'premium'));
        $this->assertStringContainsString('<div data-option="premium">', $visible);
        $this->assertStringContainsString('Viña Undurraga', $visible);
        $this->assertStringContainsString('<div data-option="classic" hidden>', $hidden);
        $this->assertStringContainsString('Viña Ballek', $hidden);
    }

    public function testCompareTableListsAllOptions(): void
    {
        $html = $this->capture(fn() => render_option_compare_table(maipo_options(), $this->prices()));
        foreach (['Classic Route', 'Classic Route + Lunch', 'Premium Route', 'Included', 'Own cost', '$110', '$139'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function testOutputIsEscaped(): void
    {
        $opts = ['x' => array_merge(maipo_options()['classic'], ['label' => '<b>x</b>'])];
        $html = $this->capture(fn() => render_option_selector($opts, [], 'x', 'n', 'L'));
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }
}
