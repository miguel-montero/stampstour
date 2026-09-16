<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ViatorCategorizationTest extends TestCase
{
    /**
     * Real rows from two live Viator booking export CSVs (2026-09-16),
     * covering every Maipo product/grade combination seen in that data.
     */
    public static function maipoRowsProvider(): array
    {
        return [
            'P25 TG1 (French title) -> plain'   => ['20268P25', 'TG1~07:30', 'Journée complète Maipo : 4 vignobles 07:30', 'MAIPO_PLAIN'],
            'P25 TG1 (English title) -> plain'  => ['20268P25', 'TG1~07:30', 'Full-Day Maipo: 4 Wineries 07:30', 'MAIPO_PLAIN'],
            'P25 TG1 (Portuguese title) -> plain'=> ['20268P25', 'TG1~07:30', 'Maipo de dia inteiro: 4 vinícolas 07:30', 'MAIPO_PLAIN'],
            'P25 TG4 -> lunch (the confirmed bug)' => ['20268P25', 'TG4~07:30', 'Lunch included 07:30', 'MAIPO_LUNCH'],
            'P15 TG1 -> lunch'                  => ['20268P15', 'TG1~07:30', 'Full wine trail  07:30', 'MAIPO_LUNCH'],
        ];
    }

    /** @dataProvider maipoRowsProvider */
    public function testRealMaipoBookingRows(string $productCode, string $tourGrade, string $tourGradeTitle, string $expected): void
    {
        $this->assertSame($expected, classify_viator_maipo_grade($productCode, $tourGrade, $tourGradeTitle));
    }

    public function testP25Tg2IsPrivatePlain(): void
    {
        // TG2 on P25 means private/PVT plain Maipo - existing rule, not present
        // in the sample data but must not regress.
        $this->assertSame('MAIPO_PLAIN_PRIVATE', classify_viator_maipo_grade('20268P25', 'TG2~07:30', ''));
    }

    public function testP25Tg3StillMapsToLunch(): void
    {
        // TG3 was the original lunch-grade code before TG4 appeared in real
        // data - must keep working, not just TG4.
        $this->assertSame('MAIPO_LUNCH', classify_viator_maipo_grade('20268P25', 'TG3~07:30', ''));
    }

    public function testP25TitleFallbackWhenGradeCodeMissing(): void
    {
        // Safety fallback: if Viator ever omits/changes the grade code but
        // keeps a recognizable title, still classify as lunch.
        $this->assertSame('MAIPO_LUNCH', classify_viator_maipo_grade('20268P25', 'DEFAULT~07:30', 'Classic Maipo Valley Tour'));
    }

    public function testP15Tg2IsPlainNotLunch(): void
    {
        // Confirmed rule: P15/TG2 = no lunch, despite P15 being the "lunch
        // trail" product overall. Not present in the sample data - grade
        // determines this, not the product code alone.
        $this->assertSame('MAIPO_PLAIN', classify_viator_maipo_grade('20268P15', 'TG2~07:30', ''));
    }

    public function testP15HasNoPrivateVariant(): void
    {
        // Confirmed: P15 never returns MAIPO_PLAIN_PRIVATE, even for a TG2
        // (which for P25 would mean private) - P15's TG2 means plain, not private.
        $this->assertNotSame('MAIPO_PLAIN_PRIVATE', classify_viator_maipo_grade('20268P15', 'TG2~07:30', ''));
    }

    public function testOtherProductCodesReturnNull(): void
    {
        // Every other product code (Valparaíso, Portillo, City Tour, etc.)
        // must return null so callers keep their own existing logic.
        $this->assertNull(classify_viator_maipo_grade('20268P5', 'TG1~07:30', ''));
        $this->assertNull(classify_viator_maipo_grade('20268P12', 'TG1~07:30', ''));
        $this->assertNull(classify_viator_maipo_grade('20268P8', 'TG1~07:30', ''));
        $this->assertNull(classify_viator_maipo_grade('', '', ''));
    }
}
