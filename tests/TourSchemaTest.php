<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TourSchemaTest extends TestCase
{
    private function render(array $data): array
    {
        ob_start();
        render_tour_product_schema($data);
        $html = ob_get_clean();
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m));
        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function base(): array
    {
        return ['name' => 'Tour', 'description' => 'D', 'image' => 'img/x.webp', 'url' => 'https://stampstour.com/t', 'price' => 110.0];
    }

    public function testSinglePriceKeepsOfferShape(): void
    {
        $s = $this->render($this->base());
        $this->assertSame('Offer', $s['offers']['@type']);
        $this->assertSame('110.00', $s['offers']['price']);
    }

    public function testOffersListBecomesAggregateOffer(): void
    {
        $s = $this->render($this->base() + ['offers' => [
            ['name' => 'Classic Route', 'price' => 110.0, 'url' => 'https://stampstour.com/t?option=classic'],
            ['name' => 'Classic Route + Lunch', 'price' => 129.0, 'url' => 'https://stampstour.com/t?option=classic-lunch'],
            ['name' => 'Premium Route', 'price' => 139.0, 'url' => 'https://stampstour.com/t?option=premium'],
        ]]);

        $agg = $s['offers'];
        $this->assertSame('AggregateOffer', $agg['@type']);
        $this->assertSame('USD', $agg['priceCurrency']);
        $this->assertSame('110.00', $agg['lowPrice']);
        $this->assertSame('139.00', $agg['highPrice']);
        $this->assertSame(3, $agg['offerCount']);
        $this->assertSame('Premium Route', $agg['offers'][2]['name']);
        $this->assertSame('139.00', $agg['offers'][2]['price']);
        $this->assertSame('https://stampstour.com/t?option=premium', $agg['offers'][2]['url']);
        $this->assertSame('https://schema.org/InStock', $agg['offers'][0]['availability']);
    }

    public function testEmptyOffersFallsBackToPrice(): void
    {
        $s = $this->render($this->base() + ['offers' => []]);
        $this->assertSame('Offer', $s['offers']['@type']);
    }
}
