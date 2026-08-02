# Medium-size variant for the Andes and Santiago tour card images

## Context

The responsive-tour-card-images work (shipped earlier today) added a `600w` "mobile" WebP variant to all 5 homepage tour card images and wired it up via `srcset`/`sizes`. The final review of that plan found a real, non-blocking gap: PageSpeed Insights' mobile audit runs Lighthouse's default **"Moto G Power"** device emulation — confirmed directly from a saved PSI response's `environment.networkUserAgent` field (`"...moto g power (2022)..."`) — which is Lighthouse's well-documented default mobile profile: a 412×823 CSS-pixel viewport at a **1.75x device pixel ratio**.

The single-column `sizes` value for all 5 cards is a flat `600px` (deliberately chosen during the previous plan's Task 2 fix, after discovering Bootstrap's container width plateaus at ~619px across the 576-767px range — see that plan's ledger). The browser's srcset-selection algorithm computes the pixel need it must satisfy as **declared `sizes` value × device pixel ratio**, not the true rendered CSS width. At the confirmed DPR of 1.75, that's `600 × 1.75 = 1050px`. Since the only two candidates today are `600w` and each tour's native full-size file, and `600w < 1050px`, the browser has no choice but to fall through to the full-size file — on the exact device profile PSI actually tests. The `600w` variant is real and correct for DPR-1 visitors (the actual majority of traffic), but it does nothing to reduce byte weight for PSI's own mobile audit, which is what originally motivated this investigation (the homepage's reported mobile LCP, ~10.0s, was unchanged by the previous fix).

The final reviewer also pointed out this gap isn't uniform across the 5 images. Native full-size widths vary a lot by tour:

| Tour | Native full width | Headroom above 1050px need |
|---|---|---|
| Valparaíso | 955px | none — already below the 1050px threshold |
| Maipo | 720px | none — already below the 1050px threshold |
| Andes | 1400px | ~350px of real headroom |
| Santiago | 1440px | ~390px of real headroom |
| Cruise | 900px | none — already below the 1050px threshold |

Only Andes and Santiago have a native file meaningfully larger than what DPR-1.75 actually needs — for the other three, the full-size file *is* close to the real need already, so adding a third, smaller-than-full candidate for them would save little or nothing. This narrows the fix to exactly those two images.

## Goals

- On PSI's actual real mobile test profile (Moto G Power, DPR 1.75, `sizes`-computed need of 1050px for the single-column layout), Andes and Santiago should load a file meaningfully smaller than their current full-size fallback (1400px / 1440px), with no visible quality loss at real display size.
- Preserve every behavior the previous plan's Task 2 fix established: the flat `600px` single-column `sizes` value stays exactly as-is (do not touch it — re-widening it was explicitly warned against by the final reviewer, since it would re-break DPR-1 selection across the 576-767px range).
- Re-run PageSpeed Insights (mobile) against the homepage once this ships, to properly test the original bandwidth-contention LCP hypothesis under conditions that actually reduce PSI's real observed byte weight for the first time.

## Non-goals

- Touching Valparaíso, Maipo, or Cruise. Confirmed above: their native full-size files are already close to or below the real DPR-1.75 need, so a third variant would save little and isn't worth the added complexity.
- Re-deriving or changing the `600px` single-column `sizes` value. It's correct for its purpose (DPR-1 selection) and out of scope here.
- Guessing the exact medium-variant pixel width in this design. Per the same pattern used in the original responsive-images spec: the file will be generated and visually verified during planning, sized with comfortable margin above the calculated 1050px threshold (not exactly at it, to avoid a Task-2-style boundary bug where a small measurement error flips selection).
- A general-purpose multi-tier responsive-image system. This is a targeted fix for a specific, measured gap in 2 images — not a redesign of the responsive-image mechanism.

## Design

### New image variant

Generate one additional "medium" WebP variant for Andes and Santiago only, sized comfortably above the 1050px threshold (target ~1100px, confirmed and adjusted if needed during planning after visual inspection). Store alongside the existing files: `img/Tours/Andes/portada-medium.webp`, `img/Tours/Stgo/portada-medium.webp`.

### Markup change

Add the medium variant as a middle `srcset` candidate on just these two cards' existing `<source>` tag. Example for Andes:

```html
<source srcset="img/Tours/Andes/portada-mobile.webp 600w, img/Tours/Andes/portada-medium.webp 1100w, img/Tours/Andes/portada.webp 1400w" sizes="(max-width: 767px) 600px, 60vw" type="image/webp">
```

Two changes from the current markup: the new `1100w` candidate (exact width confirmed during planning), and the two-column `sizes` branch changes from `50vw` to `60vw`.

The `60vw` change is necessary, not cosmetic: the final review flagged that `50vw` already under-declares real 2-column need by 4-9% (because of the sitewide `.img_container img { transform: scale(1.2) }` CSS rule inflating real rendered size beyond the raw layout box), calling it "harmless today, but would become load-bearing if a medium variant is ever added" — because with three candidates instead of two, an under-declared `sizes` value can now cause the browser to under-select the medium tier instead of correctly falling through to the full file. Since only Andes and Santiago gain a third candidate, only their two-column branch needs this correction; Valparaíso/Maipo/Cruise keep their existing `50vw` unchanged (no third candidate, no new risk).

The single-column branch (`600px`) is unchanged, per the Goals section above.

### Why not touch the other three images

Reiterating from Context: Valparaíso (955w), Maipo (720w), and Cruise (900w) native files are all already below or close to the 1050px real need. Adding a medium tier there would either produce a file barely smaller than what already exists (not worth the added markup complexity and extra HTTP request risk) or, in Maipo's case, a variant *larger* than makes sense given its native file is already under the threshold. Leaving these three as two-candidate `srcset`s (as shipped) is correct and final.

## Verification

1. Generate both medium-variant files, convert to PNG, visually inspect at real display size — same process as every image resize this session.
2. Local `php -S` server, confirm via `currentSrc` (not just markup) that:
   - Single-column widths (375-650px) at DPR 1 still select `portada-mobile.webp` (600w) — confirming the previous fix's DPR-1 behavior is unaffected by adding a third candidate.
   - **The specific PSI test condition**: 412px viewport width, `deviceScaleFactor: 1.75` (matching Moto G Power exactly) — confirm Andes and Santiago now select `portada-medium.webp`, not the full-size file. This is the one check that directly validates this spec's purpose.
   - At DPR 2+ and/or very wide single-column renders, confirm the browser still correctly falls through to the full-size file when the medium tier isn't enough (expected — the medium tier is a floor, not a hard ceiling, same principle as the mobile tier).
   - Two-column widths (992px, 1470px) at DPR 1 and DPR 2: confirm Andes/Stgo selection behavior with the new `60vw` value, and confirm Valparaíso/Maipo/Cruise are completely unaffected (still `50vw`, same behavior as before).
3. Visual regression check on the homepage tour grid at standard breakpoints — no layout shift, no visible quality loss.
4. Once deployed and the Cloudflare cache purged, re-run PageSpeed Insights (mobile) against the homepage and compare LCP and total byte weight against the most recent baseline (LCP ~10.0s, weight ~2.10MB). As with the original spec: if LCP doesn't move, that's a valid, informative result about the bandwidth-contention hypothesis, not a failure of this fix — this change now actually reduces PSI's *measured* byte weight for the first time, which is the more direct test of that theory than the previous fix achieved.

## Risks

- **The core LCP hypothesis remains unconfirmed even after this fix.** This closes the specific, measured gap the final reviewer identified (byte weight unchanged under PSI's real test conditions), but whether that byte-weight reduction moves the *reported* LCP metric is still an open question the same way it was for the original fix.
- **Boundary-selection bugs are the proven failure mode in this exact mechanism** (Task 2 of the previous plan found and fixed one). The 1050px threshold is a calculated value based on a documented Lighthouse default profile, not something scraped from a live PSI response — Verification step 2's explicit test at 412px/DPR-1.75 exists specifically to catch a miscalculation here before shipping, not to assume the arithmetic is correct.
