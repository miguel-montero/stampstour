<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UiPrimitivesTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return (string)ob_get_clean();
    }

    public function testHelpersEscapeAndFormat(): void
    {
        $this->assertSame('&lt;a href=&quot;x&quot;&gt;', ui_h('<a href="x">'));
        $this->assertSame('$1,100', ui_price(1100));
        $this->assertSame('$110', ui_price('110.00'));
        $this->assertSame('img/a-480.webp 480w, img/a-960.webp 960w', ui_srcset('img/a', [480, 960]));
    }

    public function testIconRendersKnownNamesAndSkipsUnknown(): void
    {
        foreach (['clock', 'map-pin', 'users', 'message-circle', 'rotate-ccw', 'wine', 'check', 'x', 'chevron-down', 'chevron-left', 'chevron-right', 'shield-check'] as $name) {
            $this->assertStringStartsWith('<svg class="tw:size-5"', ui_icon($name), $name);
        }
        $svg = ui_icon('check', 'tw:size-4');
        $this->assertStringStartsWith('<svg class="tw:size-4"', $svg);
        $this->assertStringContainsString('aria-hidden="true"', $svg);
        $this->assertSame('', ui_icon('nope'));
    }

    public function testOptionBlockWrappersMatchTheJsContract(): void
    {
        $this->assertSame('<div data-option="premium">', ui_option_block_open('premium', 'premium'));
        $this->assertSame('<div data-option="classic" hidden>', ui_option_block_open('classic', 'premium'));
        $this->assertSame('</div>', ui_option_block_close());
    }

    public function testAwardVariants(): void
    {
        $hero = $this->capture(fn() => ui_award('hero'));
        $this->assertStringContainsString('srcset="img/badges/tripadvisor-2026-white.webp"', $hero);
        $this->assertStringContainsString('src="img/badges/tripadvisor-2026-white.png" width="260" height="50"', $hero);
        $this->assertStringContainsString("alt=\"Tripadvisor Travelers&#039; Choice Best of the Best 2026\"", $hero);
        $this->assertStringContainsString('Top 1% of experiences on Tripadvisor &amp; Viator', $hero);

        $compact = $this->capture(fn() => ui_award('compact', 'Free <b>cancel</b>'));
        $this->assertStringContainsString('srcset="img/badges/tripadvisor-circle-2026.webp"', $compact);
        $this->assertStringContainsString('src="img/badges/tripadvisor-circle-2026.png" width="100" height="127"', $compact);
        $this->assertStringContainsString('Free &lt;b&gt;cancel&lt;/b&gt;', $compact);
    }

    public function testSectionWrapper(): void
    {
        $html = $this->capture(function () {
            ui_section_open('book', 'Book <now>', 'Reserve', 'Intro & more');
            echo 'X';
            ui_section_close();
        });
        $this->assertStringStartsWith('<section id="book" ', $html);
        $this->assertStringContainsString('Book &lt;now&gt;', $html);
        $this->assertMatchesRegularExpression('#<h2 [^>]*>Reserve</h2>#', $html);
        $this->assertStringContainsString('>Intro &amp; more</p>', $html);
        $this->assertStringEndsWith('X</div></section>', $html);

        $plain = $this->capture(fn() => ui_section_open('', 'E', 'T'));
        $this->assertStringStartsWith('<section class=', $plain);
        $this->assertSame(1, substr_count($plain, '<p'));
    }

    public function testAccordionEscapesTextButKeepsTrustedHtml(): void
    {
        $html = $this->capture(fn() => ui_accordion([
            ['q' => 'Is <lunch> included?', 'a' => 'Only on <b>Classic + Lunch</b>.'],
            ['q' => 'Pickup', 'html' => '<ul><li>Las Condes</li></ul>'],
        ], 'faq-list'));
        $this->assertStringContainsString('id="faq-list"', $html);
        $this->assertSame(2, substr_count($html, '<details class="lab-acc'));
        $this->assertSame(2, substr_count($html, '<summary'));
        $this->assertStringContainsString('Is &lt;lunch&gt; included?', $html);
        $this->assertStringContainsString('<p>Only on &lt;b&gt;Classic + Lunch&lt;/b&gt;.</p>', $html);
        $this->assertStringContainsString('<ul><li>Las Condes</li></ul>', $html);
        $this->assertStringNotContainsString(' open', $html);
    }

    public function testChecklist(): void
    {
        $html = $this->capture(fn() => ui_checklist(['Guide', '<x>'], ['Lunch']));
        $this->assertSame(3, substr_count($html, '<li'));
        $this->assertStringContainsString('&lt;x&gt;', $html);
        $this->assertMatchesRegularExpression('#<h3 class="lab-eyebrow">Included</h3>#', $html);
        $this->assertMatchesRegularExpression('#<h3 class="lab-eyebrow">Not included</h3>#', $html);

        $yesOnly = $this->capture(fn() => ui_checklist(['Guide'], []));
        $this->assertStringNotContainsString('Not included', $yesOnly);
    }

    public function testBenefits(): void
    {
        $html = $this->capture(fn() => ui_benefits([
            ['icon' => 'wine', 'title' => '4 wineries', 'text' => 'T&C'],
            ['icon' => 'users', 'title' => 'Small group', 'text' => 'Max 15'],
        ]));
        $this->assertSame(2, substr_count($html, '<li'));
        $this->assertSame(2, substr_count($html, '<svg'));
        $this->assertMatchesRegularExpression('#<h3 [^>]*>4 wineries</h3>#', $html);
        $this->assertStringContainsString('T&amp;C', $html);
    }

    public function testContentIconsAllExist(): void
    {
        foreach (array_merge(maipo_lab_benefits(), maipo_lab_hero(null)['facts']) as $item) {
            $this->assertNotSame('', ui_icon($item['icon']), $item['icon']);
        }
    }
}
