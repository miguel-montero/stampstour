# Marketing Benchmark #1 — stampstour.com

**Filed:** 31 July 2026
**GA4 window:** 2–31 Jul 2026 (30 days) · property `495857409` (www.stampstour.com)
**GSC window:** 3–31 Jul 2026 (28 days) · property `sc-domain:stampstour.com`

This is the first benchmark. Compare future reports against this one.

> GA4 checkout-funnel tracking (`begin_checkout` → `add_payment_info` → `purchase`) went live the same day this report was filed. All funnel numbers below are zero because there's no data yet, not because nothing sold — the next report is the first one that can show real conversion numbers.

## Headline numbers

| Metric | Value |
|---|---|
| Sessions (30d) | 880 |
| Engaged sessions | 59.2% (bounce 40.8%) |
| Search clicks (28d) | 276 |
| Search impressions (28d) | 1,657 (16.7% blended CTR) |
| Checkout funnel events | 0 (tracking launched today) |

## Site traffic (GA4, 30 days)

**Sessions by channel:** Organic Search 406 · Direct 405 · Referral 32 · Organic Social 17 · AI Assistant 16 · Unassigned 4

**Top pages by pageviews:** Home 652 · Valparaíso tour 253 · Maipo tour 177 · Andes tour 118 · Cruise transfer 105 · Santiago tour 95

**Other GA4 metrics:** 560 active users, 534 new users, 1,506 pageviews, avg. session duration 3:24. Event volume: `page_view` 1506, `user_engagement` 1067, `session_start` 887, `first_visit` 534, `scroll` 486, `form_start` 120, `click` 43, `form_submit` 41, `generate_lead` 10 (source of these 10 not yet attributed — no newsletter/contact form event code has been added this project; likely GA4 Enhanced Measurement inferring from `form_submit`, or the OpenWidget chat widget's own GA4 integration).

## Search visibility (GSC, 28 days)

**Top queries:**

| Query | Clicks | Impr. | CTR | Position |
|---|---|---|---|---|
| stampstour | 34 | 63 | 54.0% | 1.1 |
| stamps tour | 28 | 67 | 41.8% | 1.3 |
| stamp's tour | 20 | 119 | 16.8% | 2.5 |
| stamps tour chile | 18 | 26 | 69.2% | 1.0 |
| stamps tour santiago | 17 | 30 | 56.7% | 1.0 |
| stamp's tour santiago | 4 | 96 | 4.2% | 6.5 |
| valparaíso port & viña del mar... | 2 | 15 | 13.3% | 2.5 |

**By device:**

| Device | Clicks | Impr. | CTR | Position |
|---|---|---|---|---|
| Mobile | 132 | 649 | 20.3% | 6.3 |
| Desktop | 138 | 987 | 14.0% | 9.9 |
| Tablet | 6 | 21 | 28.6% | 6.4 |

**Landing pages:**

| Page | Clicks | Position |
|---|---|---|
| / (non-www) | 187 | 3.1 |
| / (www) | 64 | 7.2 |
| /valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca | 22 | 7.4 |
| /maipo-valley-wine-tour-santiago | 15 | 7.7 |
| /cruise-transfer.php | 6 | 7.2 |
| /portillo-inca-lagoon-andes-mountains-vineyard | 5 | 4.6 |
| /Santiago.html (legacy, redirects) | 3 | 10.6 |
| /discover-santiago-city-tour | 3 | 4.5 |

## Findings

**Fix — two of three submitted sitemaps are broken.** `/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca` and `/cruise-transfer.php` are registered in Search Console as *sitemaps* — they're actual page URLs, not sitemap XML, so both show "Has errors." Only `/sitemap.txt` (10 URLs) is valid. Remove the two bad entries; consider a real XML sitemap covering the blog and admin-managed pages.

**Watch — almost all search traffic is branded.** The top 6 queries are all "stamps tour" / "stampstour" variants — strong brand recognition, but near-zero visibility on generic terms a first-time visitor would type ("valparaiso wine tour", "santiago day trips", etc.). This is the real growth lever: non-branded SEO, not brand defense.

**Watch — www/non-www still splitting search performance.** `www.stampstour.com/` still holds 767 impressions at position 7.2, versus 802 at position 3.1 for the canonical non-www home page. Google's URL Inspection API already confirms the www version correctly resolves as "Page with redirect" (verified this session) — this should consolidate onto the canonical URL over the coming weeks.

**Confirmed — legacy tour URLs redirect correctly.** `Valparaiso.php`, `Santiago.html`, `Andes.html` all confirm as "Page with redirect" via URL Inspection. Their lingering impression counts (up to 269) are old rankings still working through Google's re-crawl; expect these to fade toward the new clean-slug URLs.

**Next report — checkout funnel tracking starts counting from today.** `begin_checkout`, `add_payment_info`, `purchase` just went live on `shopping.php`/`return.php` (see `docs/superpowers/plans/2026-07-31-ga4-checkout-funnel-tracking.md`). The next benchmark can show real conversion-funnel numbers for the first time.

## Also published

Visual version (charts, stat tiles): https://claude.ai/code/artifact/354ada2f-8da6-4bdd-bf0d-dd83954febf4
