<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UiBookingPanelTest extends TestCase
{
    private function render(string $selected, string $action = 'book_tour.php'): string
    {
        $prices = ['classic' => ['adult' => 110.0], 'classic-lunch' => ['adult' => 129.0], 'premium' => ['adult' => 139.0]];
        ob_start();
        ui_booking_panel(maipo_options(), $prices, $selected, $action);
        return (string)ob_get_clean();
    }

    public function testKeepsEveryIdTheBookingScriptsUse(): void
    {
        $html = $this->render('premium');
        foreach (['booking', 'activity_name', 'option_2', 'name_booking', 'last_name_booking', 'email_booking', 'phone_booking', 'date_booking', 'adults', 'children', 'infants', 'coupon_code', 'apply_coupon_valpo', 'total_price', 'new_total_container', 'new_total_price'] as $id) {
            $this->assertSame(1, substr_count($html, 'id="' . $id . '"'), $id);
        }
        $this->assertStringContainsString('<form action="book_tour.php" method="POST" id="booking"', $html);
        $this->assertStringContainsString('<input id="activity_name" name="activity_name" type="hidden" value="Maipo">', $html);
        $this->assertStringContainsString('<input id="option_2" name="option_2" type="checkbox" value="Yes" hidden>', $html);
        $this->assertSame(3, substr_count($html, '<div class="numbers-row">'));
        $this->assertSame(3, substr_count($html, 'class="qty2"'));
        $this->assertStringContainsString('class="lab-input date-pick required" id="date_booking" name="date_booking"', $html);
        $this->assertStringContainsString('id="new_total_container" style="display:none;"', $html);
        $this->assertMatchesRegularExpression('#<button class="lab-btn[^"]*" type="submit">Book now</button>#', $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
    }

    public function testHasRouteSelectorAndTrustBadge(): void
    {
        $html = $this->render('premium');
        $this->assertSame(3, substr_count($html, 'name="tour_option_book"'));
        $this->assertStringContainsString('id="tour_option_book-premium" value="premium" data-booking-lock checked>', $html);
        $this->assertStringContainsString('tripadvisor-circle-2026', $html);
        $this->assertStringContainsString('Free cancellation up to 24 h before · Secure payment', $html);
    }

    public function testActivityNameFollowsSelectedOptionAndActionIsEscaped(): void
    {
        $html = $this->render('classic', 'book"x.php');
        $this->assertStringContainsString('name="activity_name" type="hidden" value="MAIPO_CLASSIC"', $html);
        $this->assertStringContainsString('<form action="book&quot;x.php"', $html);
    }
}
