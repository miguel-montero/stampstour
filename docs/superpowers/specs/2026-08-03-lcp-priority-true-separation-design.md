# True priority separation for deferred stylesheets (media="print" swap)

## Context

The LCP priority-contention fix shipped yesterday (`docs/superpowers/specs/2026-08-02-tour-pages-lcp-priority-fix-design.md`) converted 6 shared stylesheets (plus `timeline.css` on the 5 tour pages) from `fetchpriority="low"` on `<link rel="preload">` to fix a real priority *inversion* — before that fix, these stylesheets sat at Blink's `VeryHigh` priority bucket while the LCP image sat at `High`, meaning the image was strictly outranked by every stylesheet. The fix brought the stylesheets down to `High`, tying them with the image.

That fix produced a large, directly-measured, three-times-independently-reproduced improvement in the LCP image's real download time (Maipo: −62 to −65%, homepage: −26 to −30%, measured via real Chrome + CDP network timing under throttled conditions). But following deployment, a PageSpeed Insights recheck on Maipo showed something unexpected: across 4 consecutive runs, the reported LCP metric was **bimodal** — it landed on either ~2.6s or ~6.5s, split evenly, with Cumulative Layout Shift swinging oppositely in step (0.024-0.025 when LCP was ~6.5s; 0.281 when LCP was ~2.6s). This isn't scattered noise — it's a clean two-outcome split.

The most likely explanation, consistent with everything measured so far: `fetchpriority="low"` only demotes a stylesheet preload from `VeryHigh` to `High` in Blink — it does not reach Blink's actual `Low` tier. Since the fix ties the stylesheets and the LCP image at the same `High` tier, Lighthouse's Lantern dependency-graph simulator has an ambiguous tie to resolve between the image and the stylesheets, and it's plausible the simulator breaks that tie differently between runs — sometimes resolving in the image's favor (fast LCP, but a more disruptive layout settle as CSS lands later relative to it, hence worse CLS) and sometimes not (slow LCP, but CSS and image settle in a more stable order, hence better CLS).

The standard technique for genuinely deprioritizing a stylesheet below normal fetch priority in Chrome is the `media="print"` onload-swap idiom (popularized as "loadCSS," in wide use for years): a stylesheet is loaded via `<link rel="stylesheet" media="print" onload="this.media='all'">`. Because `media="print"` doesn't apply to on-screen rendering, the browser doesn't need to block first paint on it — but critically, Blink also assigns it a genuinely lower internal fetch priority than a `rel="preload" as="style"` tag, reaching the `Low` bucket rather than stopping at `High`. This spec was identified and specifically recommended as a follow-up in the final review of yesterday's fix (see the comment already left in `includes/head.php` referencing it).

## Goals

- Give the deferred stylesheets a genuinely lower fetch priority than the LCP image — not just parity with it — resolving the ambiguous tie identified above.
- Preserve every existing behavior: critical CSS still covers first paint, JS-disabled visitors still get correctly styled pages via `<noscript>`, no visual regression.

## Non-goals

- Changing the `$critical_css_file` or `$lcp_preload_image` mechanisms themselves — this spec only changes *how* the deferred stylesheets are loaded, not the critical-CSS inlining or the image-preload logic.
- Applying this to pages without critical CSS (the `<?php else: ?>` branch in `includes/head.php`, used by pages like `contact-us.php`, `privacy.php`, `blog.php`) — those pages have no critical CSS to cover first paint, so their stylesheets must remain render-blocking, exactly as today.
- Fully guaranteeing PSI's reported LCP metric stabilizes on a single number. The bimodality is a plausible, evidence-grounded hypothesis about *why* the ambiguity exists, not a confirmed mechanism inside Lighthouse's own simulator — the verification step below tests this directly, and a partial improvement (less bimodal, but not perfectly single-valued) is still useful data, not a failure.

## Design

In `includes/head.php`'s `$critical_css_file`-gated branch, replace each `<link rel="preload" href="..." as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">` with the `media="print"` swap pattern:

```html
<link rel="stylesheet" href="/fonts/fonts.css" media="print" onload="this.media='all';this.onload=null;">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
```

Applied identically to all 6 shared stylesheets (`fonts.css`, `bootstrap.min.css`, `style.css`, `vendors.css`, `bootstrap-icons.min.css`, `custom.css`) in `includes/head.php`, and to `timeline.css`'s preload on each of the 5 tour pages.

The `<noscript>` fallback tags are completely unchanged — they already correctly load a normal blocking stylesheet for JS-disabled visitors, and that logic is orthogonal to how JS-enabled visitors load it.

The existing code comment above the block (added in yesterday's follow-up fix, already correctly describing the `VeryHigh`→`High` tie mechanism) needs updating to describe the new mechanism: `media="print"` reaches Blink's actual `Low` tier, achieving real separation from the LCP image's `High`/`fetchpriority="high"` priority, rather than tying with it.

## Verification

1. `php -l` on `includes/head.php` and all 5 tour pages; confirm the `<noscript>` line count and swap-tag count both remain exactly as many as before (6 in `head.php`, 1 per tour page for `timeline.css`).
2. Confirm via direct CDP priority inspection (matching the methodology the final reviewer used to diagnose the current `High`/`High` tie) that, post-change, the stylesheets report a strictly lower priority bucket than the LCP image's own preload — not just a different-but-still-tied value.
3. Confirm no regression in the real, directly-measured download-time improvement already achieved — re-run the throttled-network `responseEnd` measurement for the LCP image on Maipo and the homepage (the same methodology used yesterday) and confirm the image's completion time is at least as good as yesterday's post-fix numbers (Maipo ~2.5s, homepage ~8.6s under the established throttling profile), ideally better now that it isn't sharing a tier.
4. Visual regression check under throttling: confirm no flash of unstyled content below the fold, same as prior verification — `media="print"` swap has the identical "invisible until swap" behavior as the current preload approach, so this should be unaffected, but confirm directly rather than assume.
5. **The real test for the bimodality hypothesis**: once deployed and cache-purged, run PageSpeed Insights (mobile) against Maipo at least 4 times in a row (matching the run count that first revealed the bimodal pattern) and check whether the LCP/CLS numbers now cluster around a single outcome instead of splitting evenly between two. Also check the homepage. If the split persists, that's a real, informative result — it would mean the bimodality has a different cause than the tie hypothesis, worth a fresh investigation rather than assuming this spec's mechanism was wrong.

## Risks

- **The bimodality's root cause is a hypothesis, not a proven mechanism inside Lighthouse's Lantern simulator.** It's well-grounded (the tie exists, is newly introduced by yesterday's fix, and ties are a documented source of non-determinism in dependency-graph schedulers generally) but not something directly inspectable inside PSI's black-box simulation. Verification step 5 is the actual test; a null result is possible and would be informative, not a sign this spec's mechanism is broken.
- **`media="print"` is an old, very well-supported technique**, but worth confirming it doesn't interact unexpectedly with this codebase's specific combination of inlined critical CSS + the `onload` swap already in use elsewhere (e.g., `timeline.css`, which uses the same swap idiom already, just via `rel="preload"` rather than `media="print"` — the risk profile of switching its mechanism is the same as the 6 shared stylesheets).
- **Real users on very slow connections might see below-the-fold CSS complete slightly later** than under the `fetchpriority="low"` approach, since it's now genuinely lower priority, not just tied. This is the intended effect (freeing more bandwidth for the LCP image) but is worth reconfirming visually per Verification step 4, consistent with the same risk already accepted and verified in yesterday's fix.
