<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaipoLabContentTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function testGalleryPhotosExistWithMatchingDimensionsAndBudgets(): void
    {
        $photos = maipo_lab_photos();
        $this->assertCount(7, $photos);
        foreach ($photos as $p) {
            foreach ([480, 960, 1600] as $w) {
                $this->assertFileExists(self::ROOT . "/{$p['base']}-$w.webp");
            }
            $jpg = self::ROOT . "/{$p['base']}-960.jpg";
            $this->assertFileExists($jpg);
            [$w, $h] = getimagesize($jpg);
            $this->assertSame([$p['w'], $p['h']], [$w, $h], $p['base']);
            $this->assertLessThanOrEqual(120 * 1024, filesize(self::ROOT . "/{$p['base']}-960.webp"), $p['base']);
        }
    }

    public function testHeroImageFilesAndBudget(): void
    {
        $img = maipo_lab_hero(110.0)['image'];
        foreach (['mobile', 'desktop', 'fallback'] as $k) {
            $this->assertFileExists(self::ROOT . '/' . $img[$k], $k);
        }
        [$w, $h] = getimagesize(self::ROOT . '/' . $img['fallback']);
        $this->assertSame([$img['w'], $img['h']], [$w, $h]);
        $this->assertLessThanOrEqual(250 * 1024, filesize(self::ROOT . '/' . $img['desktop']));
    }

    public function testAltTextIsDescriptiveAndUnique(): void
    {
        $alts = array_column(maipo_lab_photos(), 'alt');
        $alts[] = maipo_lab_hero(null)['image']['alt'];
        foreach ($alts as $alt) {
            $this->assertGreaterThanOrEqual(15, strlen($alt), $alt);
        }
        $this->assertSame(count($alts), count(array_unique($alts)));
    }

    public function testCaptionsNeverImplyLunchIsIncludedOnEveryOption(): void
    {
        foreach (maipo_lab_photos() as $p) {
            $text = $p['alt'] . ' ' . $p['caption'];
            if (stripos($text, 'lunch') !== false) {
                $this->assertStringContainsString('Classic + Lunch', $p['caption'], $p['base']);
            }
            $this->assertStringNotContainsStringIgnoringCase('terramater', $text);
        }
    }

    public function testHeroCopy(): void
    {
        $hero = maipo_lab_hero(110.0);
        $this->assertSame('Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries', $hero['title']);
        $this->assertStringContainsString($hero['highlight'], $hero['title']);
        $this->assertSame('Four wineries, pisco tasting and a lunch stop at a winery restaurant — with hotel pickup and an English-speaking guide.', $hero['lead']);
        $this->assertSame(['10 hours', 'Hotel pickup', 'Max 15 travelers', 'English guide', 'Free cancellation 24 h'], array_column($hero['facts'], 'text'));
        $this->assertSame(110.0, $hero['price_from']);
        $this->assertSame('#book', $hero['cta_href']);
    }

    public function testBenefitsIntroAndPracticalInfo(): void
    {
        $benefits = maipo_lab_benefits();
        $this->assertCount(4, $benefits);
        foreach ($benefits as $b) {
            $this->assertSame(['icon', 'title', 'text'], array_keys($b));
        }
        $this->assertStringStartsWith('Spend a day in Chilean wine country around Isla de Maipo', maipo_lab_intro());
        $practical = maipo_lab_practical();
        $this->assertSame(['Pickup & return', 'Additional information', 'Cancellation policy'], array_column($practical, 'q'));
        $this->assertStringContainsString('at least 24 hours before the experience start time', $practical[2]['html']);
        $this->assertStringContainsString('maximum of 15 travelers', $practical[1]['html']);
    }
}
