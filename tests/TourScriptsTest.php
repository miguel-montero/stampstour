<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TourScriptsTest extends TestCase
{
    private function render(?bool $skip): string
    {
        $exp_name = 'Maipo';
        if ($skip !== null) {
            $tour_scripts_skip_gallery = $skip;
        }
        ob_start();
        include __DIR__ . '/../includes/tour-scripts.php';
        return (string)ob_get_clean();
    }

    public function testDefaultStillLoadsGalleryAndStickySidebar(): void
    {
        $html = $this->render(null);
        $this->assertStringContainsString('js/jquery.sliderPro.min.js', $html);
        $this->assertStringContainsString('css/slider-pro.min.css', $html);
        $this->assertStringContainsString('js/theia-sticky-sidebar.js', $html);
        $this->assertStringContainsString('js/tours.js?v=20261014', $html);
    }

    public function testSkipFlagDropsGalleryAndStickySidebarOnly(): void
    {
        $html = $this->render(true);
        $this->assertStringNotContainsString('sliderPro', $html);
        $this->assertStringNotContainsString('slider-pro', $html);
        $this->assertStringNotContainsString('theia', $html);
        $this->assertStringContainsString('js/jquery-3.7.1.min.js', $html);
        $this->assertStringContainsString('js/vendors-tour.min.js', $html);
        $this->assertStringContainsString("window.EXP_NAME = 'Maipo'", $html);
        $this->assertStringContainsString('js/tours.js?v=20261014', $html);
    }

    public function testBookingManualDoesNotLoadToursJs(): void
    {
        $src = (string)file_get_contents(__DIR__ . '/../booking_manual.php');
        $this->assertStringNotContainsString('js/tours.js', $src);
    }
}
