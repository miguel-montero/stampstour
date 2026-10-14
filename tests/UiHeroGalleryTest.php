<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UiHeroGalleryTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return (string)ob_get_clean();
    }

    private function hero(array $over = []): array
    {
        return array_merge([
            'image' => ['mobile' => 'img/h-960.webp', 'desktop' => 'img/h-1280.webp', 'fallback' => 'img/h-1280.jpg', 'w' => 1280, 'h' => 960, 'alt' => 'Group toast'],
            'title' => 'Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries',
            'highlight' => 'Maipo Valley',
            'lead' => 'Four wineries & pisco',
            'facts' => [['icon' => 'clock', 'text' => '10 hours'], ['icon' => 'users', 'text' => 'Max 15 travelers']],
            'cta_href' => '#book',
            'cta_text' => 'Check availability',
            'price_from' => 110.0,
        ], $over);
    }

    private function photos(): array
    {
        return [
            ['base' => 'img/g/a', 'w' => 960, 'h' => 664, 'alt' => 'Asado <grill>', 'caption' => 'Lunch "stop"'],
            ['base' => 'img/g/b', 'w' => 960, 'h' => 640, 'alt' => 'Tasting room', 'caption' => 'Tasting'],
            ['base' => 'img/g/c', 'w' => 960, 'h' => 640, 'alt' => 'Pisco sours', 'caption' => 'Pisco'],
        ];
    }

    public function testHeroImageIsTheLcpCandidate(): void
    {
        $html = $this->capture(fn() => ui_hero($this->hero()));
        $this->assertStringContainsString('<source media="(max-width: 767px)" srcset="img/h-960.webp" type="image/webp">', $html);
        $this->assertStringContainsString('<source media="(min-width: 768px)" srcset="img/h-1280.webp" type="image/webp">', $html);
        $this->assertStringContainsString('<img src="img/h-1280.jpg" width="1280" height="960" alt="Group toast" fetchpriority="high"', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html);
    }

    public function testHeroTitleHighlightAndEscaping(): void
    {
        $html = $this->capture(fn() => ui_hero($this->hero()));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Small-Group <em class="tw:not-italic tw:text-coral">Maipo Valley</em> Wine Tour from Santiago: 4 Wineries</h1>', $html);
        $this->assertStringContainsString('Four wineries &amp; pisco', $html);

        $odd = $this->capture(fn() => ui_hero($this->hero(['title' => 'A <b> & B', 'highlight' => '& B'])));
        $this->assertStringContainsString('A &lt;b&gt; <em class="tw:not-italic tw:text-coral">&amp; B</em></h1>', $odd);
    }

    public function testHeroFactsCtaPriceAndAward(): void
    {
        $html = $this->capture(fn() => ui_hero($this->hero()));
        $this->assertSame(2, substr_count($html, 'data-hero-fact'));
        $this->assertStringContainsString('href="#book"', $html);
        $this->assertStringContainsString('>Check availability</a>', $html);
        $this->assertMatchesRegularExpression('#From <strong [^>]*>\$110</strong> per adult#', $html);
        $this->assertStringContainsString('tripadvisor-2026-white', $html);

        $noPrice = $this->capture(fn() => ui_hero($this->hero(['price_from' => null])));
        $this->assertStringNotContainsString('per adult', $noPrice);
    }

    public function testGalleryTilesAreLazyResponsiveLinks(): void
    {
        $html = $this->capture(fn() => ui_gallery($this->photos()));
        $this->assertSame(3, substr_count($html, 'data-lab-gallery-item'));
        $this->assertStringContainsString('href="img/g/a-1600.webp"', $html);
        $this->assertStringContainsString('srcset="img/g/a-480.webp 480w, img/g/a-960.webp 960w"', $html);
        $this->assertStringContainsString('<img src="img/g/a-960.jpg" width="960" height="664" alt="Asado &lt;grill&gt;" loading="lazy" decoding="async"', $html);
        $this->assertStringContainsString('data-caption="Lunch &quot;stop&quot;"', $html);
        $this->assertSame(3, substr_count($html, 'loading="lazy"'));
        $this->assertSame(1, substr_count($html, 'sizes="(min-width: 768px) 50vw, 100vw"'));
        $this->assertSame(2, substr_count($html, 'sizes="(min-width: 768px) 33vw, 50vw"'));
    }

    public function testGalleryHasOneLightboxDialog(): void
    {
        $html = $this->capture(fn() => ui_gallery($this->photos()));
        $this->assertSame(1, substr_count($html, '<dialog'));
        foreach (['data-lab-lightbox-img', 'data-lab-lightbox-caption', 'data-lab-lightbox-prev', 'data-lab-lightbox-next', 'data-lab-lightbox-close'] as $hook) {
            $this->assertStringContainsString($hook, $html);
        }
        $this->assertStringContainsString('aria-label="Close"', $html);
        $this->assertSame('', $this->capture(fn() => ui_gallery([])));
    }
}
