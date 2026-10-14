<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TourFaqTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return (string)ob_get_clean();
    }

    private function faqs(): array
    {
        return [
            ['q' => 'Is lunch included?', 'a' => 'Only on Classic + Lunch.'],
            ['q' => 'Q2', 'a' => 'Bad </script><script>alert(1)</script>'],
        ];
    }

    public function testSchemaOnlyHelper(): void
    {
        $html = trim($this->capture(fn() => render_tour_faq_schema($this->faqs())));
        $this->assertStringStartsWith('<script type="application/ld+json">', $html);
        $this->assertStringNotContainsString('accordion', $html);
        $this->assertSame(1, substr_count($html, '</script>'), 'answer text must not close the script tag');
        preg_match('#<script type="application/ld\+json">(.*)</script>#s', $html, $m);
        $data = json_decode($m[1], true);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Is lunch included?', $data['mainEntity'][0]['name']);
        $this->assertSame('Bad </script><script>alert(1)</script>', $data['mainEntity'][1]['acceptedAnswer']['text']);
    }

    public function testFullFaqStillRendersAccordionAndSchemaOnce(): void
    {
        $html = $this->capture(fn() => render_tour_faq($this->faqs()));
        $this->assertStringContainsString('id="tourFaqAccordion"', $html);
        $this->assertSame(1, substr_count($html, 'application/ld+json'));
    }

    public function testEmptyFaqsRenderNothing(): void
    {
        $this->assertSame('', $this->capture(fn() => render_tour_faq_schema([])));
        $this->assertSame('', $this->capture(fn() => render_tour_faq([])));
    }
}
