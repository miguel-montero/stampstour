# Marketing Benchmark #2 — stampstour.com

**Filed:** 1 August 2026
**GA4 window:** 3 Jul – 1 Aug 2026 (30 days) · property `495857409` (www.stampstour.com)
**GSC window:** 4 Jul – 1 Aug 2026 (28 days) · property `sc-domain:stampstour.com`
**Compare against:** [Benchmark #1](2026-07-31-marketing-benchmark.md), filed 31 Jul 2026

> This window overlaps ~97% with Benchmark #1 (only 1 day of new data swapped in) — one day isn't enough to read a real traffic trend from this pair of reports. Treat the deltas below as noise unless flagged otherwise. The real news this time is qualitative: the sitemap fix confirmed live, and the first (partial) look at real checkout-funnel data, which surfaced a tracking gap worth investigating.

## Headline numbers

| Metric | Benchmark #1 | Benchmark #2 | Change |
|---|---|---|---|
| Sessions (30d) | 880 | 823 | -6.5% |
| Engaged sessions | 59.2% | 60.1% | +0.9pp |
| Bounce rate | 40.8% | 39.9% | -0.9pp |
| Search clicks (28d) | 276 | 268 | -2.9% |
| Search impressions (28d) | 1,657 | 1,622 | -2.1% |
| Search CTR (28d) | 16.7% | 16.5% | -0.2pp |
| Checkout funnel events | 0 (just launched) | `purchase`: 1, `begin_checkout`: 0, `add_payment_info`: 0 | see finding below |

## Site traffic (GA4, 30 days)

**Sessions by channel:** Direct 380 · Organic Search 379 · Referral 31 · AI Assistant 16 · Organic Social 14 · Unassigned 4
(Benchmark #1 had these nearly tied in the opposite order: Organic Search 406 · Direct 405 — still essentially a dead heat, not a real shift.)

**Top pages by pageviews:** Home 654 · Valparaíso tour 236 · Maipo tour 175 · Andes tour 109 · Cruise transfer 97 · Santiago tour 90 — all flat to slightly down, tracking the overall session dip.

**Other GA4 metrics:** 518 active users (was 560), 489 new users (was 534), 1,413 pageviews (was 1,506), avg. session duration 3:29 (was 3:24, up slightly). Event volume: `page_view` 1414, `user_engagement` 994, `session_start` 830, `first_visit` 489, `scroll` 463, `form_start` 112, `click` 40, `form_submit` 39, `generate_lead` 10, `purchase` 1.

## Search visibility (GSC, 28 days)

**Top queries:** Still entirely branded — `stampstour`, `stamps tour`, `stamp's tour`, `stamps tour chile`, `stamps tour santiago` — same pattern as Benchmark #1, no material change yet.

**By device:**

| Device | Clicks | Impr. | CTR | Position |
|---|---|---|---|---|
| Desktop | 133 | 962 | 13.8% | 10.1 |
| Mobile | 129 | 639 | 20.2% | 6.3 |
| Tablet | 6 | 21 | 28.6% | 6.4 |

Essentially unchanged from Benchmark #1 (Mobile 132/649/20.3%/6.3, Desktop 138/987/14.0%/9.9).

**Landing pages:** `/` (non-www) 180 clicks @ position 3.1, `/` (www) 64 clicks @ position 7.1 — the www/non-www split flagged in Benchmark #1 hasn't moved yet.

## Findings

**Confirmed fixed — sitemap is clean.** Search Console now shows exactly 1 submitted sitemap (`sitemap.xml`), status "Valid," 10 indexed URLs, 0 errors, 0 warnings, last downloaded today. This confirms the dynamic XML sitemap shipped after Benchmark #1 replaced the two broken sitemap entries cleanly.

**Investigate — checkout funnel shows `purchase` with no `begin_checkout` or `add_payment_info` ever recorded.** One `purchase` event fired on 31 Jul, the same day tracking went live. But querying `begin_checkout`/`add_payment_info` directly for the full 30-day window returns zero rows for both — not a display artifact, confirmed via a direct filtered query. Since `purchase` only fires from `return.php` after a completed payment, and `begin_checkout` fires unconditionally on `shopping.php` page load, this booking's `begin_checkout` should have fired first unless: (a) the booking came from a source that reaches `return.php` without passing through the tracked `shopping.php` flow, or (b) there's a real bug suppressing `begin_checkout`/`add_payment_info` on `shopping.php`. Worth a closer look before drawing any funnel-drop-off conclusions from future reports.

**Unchanged — branded-query dependency and www/non-www split.** Both items flagged in Benchmark #1 persist as-is; too early to expect movement from a single day.

**Not yet reflected — Revolution Slider trim.** The JS/request-weight reduction on the homepage hero shipped today (1 Aug), after this report's window closed. Next benchmark is the first one that could show any CWV/traffic effect from it.

## Also published

Visual version (charts, stat tiles): https://claude.ai/code/artifact/9d85360c-2d6e-4c16-aba2-44d91697d595
