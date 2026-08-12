# Fix desktop CLS from a duplicated `.row>*` reset overriding page-specific column widths

## Context

Following the icon-font fallback CLS fix (which shipped correctly but did not resolve the user's reported desktop CLS issue), a deeper investigation using a real Chrome performance trace (`Tracing.start`/`stop` via CDP, categories `devtools.timeline` + `disabled-by-default-devtools.timeline.stack`) and CDP's `CSS.getMatchedStylesForNode` found the true root cause on `contact-us.php` (desktop, 1920×1080, Lighthouse-equivalent throttling): a single `LayoutShift` event at t≈1346ms, score 0.2124 (accounting for essentially the entire measured 0.1879–0.1882 CLS — the second-largest event on the page scores 0.0000046, negligible).

The shift's `impacted_nodes` show the support box's grid column going from `x=300 w=1320` (full row width, i.e. `.col-lg-6`/`.col-md-8`'s width restriction not applied) to `x=630 w=660` (correctly centered at 50% width) — plus matching movement in the footer's own grid columns beneath it.

**Root cause, confirmed by freezing the page** (via CDP request interception blocking `bootstrap.min.css`/`style.css`/`vendors*.css` entirely, then inspecting `CSS.getMatchedStylesForNode` on the affected element in that frozen state): `includes/critical/content.css` is built from 10 near-identical duplicated blocks — one per page that uses this critical-CSS variant (contact-us.php, privacy.php, refunds-cancellations.php, gallery.php, blog.php, and others). 66 selectors appear exactly 10 times in the file, including Bootstrap's `.row>*{width:100%}` reset rule — a page-agnostic rule that doesn't need to vary per page but got duplicated anyway during whatever process extracted/built these blocks.

`.row>*{width:100%}` and page-specific column classes like `.col-lg-6{width:50%}`/`.col-md-8{width:66.67%}` have **identical CSS specificity** (0-1-0 — one class selector each). At equal specificity, the last matching rule in source order wins. `.col-lg-6`/`.col-md-8` (used only by `contact-us.php`'s support box) exist in the file just once, positioned between the 6th and 7th `.row>*` duplicates — meaning 4 of the 10 `.row>*` copies (each logically belonging to a *different, unrelated* page's block) sit **after** it in the file and win the cascade, overriding the column's width back to 100% for the entire page, for every visitor, on every load — not a network race, a deterministic ordering bug. The correct width only appears once `bootstrap.min.css` finishes loading (confirmed via the trace: response finishes at 1220ms, the `onload` handler flips `media` to `all`, and the resulting style recalc/layout produces the LayoutShift at 1346ms) and re-establishes the correct rule order, because Bootstrap's own real stylesheet has `.row>*` correctly positioned before its breakpoint-specific `.col-*` overrides.

Verified this pattern is unique to `content.css` — `includes/critical/home.css` and `includes/critical/tour.css` each have `.row>*` only **once** (not duplicated), so they are not exposed to this specific failure mode.

Every `.col-*` rule in `content.css` is affected by this exact hazard except the ones in the file's very last duplicated block (the only block positioned after all 10 `.row>*` copies) — meaning this is a de facto sitewide bug across the "content" page family, not limited to `contact-us.php`.

A separate, much smaller CLS was also measured on `discover-santiago-city-tour.php` (0.0037–0.0221, shift sources `row`/`form-group`) using the `tour.css` critical variant. Since `tour.css` doesn't have the duplicated-`.row>*` pattern, this is a different, smaller-magnitude issue with an undetermined root cause — explicitly out of scope for this fix (see Non-goals).

## Goals

- Eliminate the confirmed, traced CLS root cause on `contact-us.php` (and every other page using `content.css`'s critical CSS): the duplicate `.row>*` rule silently overriding page-specific `.col-*` widths.
- Fix it in a way that removes the hazard at its source rather than reshuffling the symptom — de-duplicating a rule that never needed to vary per page in the first place, rather than reordering the one-off `.col-*` overrides to "win by position" (which would leave the same trap for the next person who adds a new `.col-*` rule to this file).

## Non-goals

- The smaller, separately-measured CLS on `discover-santiago-city-tour.php` (`tour.css` variant, `row`/`form-group` sources) — different file, different (not yet root-caused) mechanism, much smaller magnitude (0.0037–0.0221, mostly within the "Good" ≤0.1 threshold). A future investigation, not bundled here.
- The broader architectural pattern of `content.css` being built from 10 duplicated boilerplate blocks (66 selectors total duplicated 10×) — this fix removes only the one duplicated rule proven to cause a real, measured cascade conflict (`.row>*`). The other 65 duplicated selectors were checked and don't have a same-specificity competing override elsewhere in the file, so they're not causing this class of bug today. Restructuring `content.css`'s whole block-duplication approach is a larger, separate refactor not warranted by the evidence gathered here.
- `home.css`/`tour.css` — confirmed not exposed to this specific failure mode (each has `.row>*` only once already).
- The icon-font fallback CLS fix from the prior investigation — already shipped, correctly implemented, and left in place; unrelated to this bug and not touched here.

## Design

### The fix

`includes/critical/content.css` currently contains this exact rule, byte-identical, at 10 positions (confirmed via direct text search):

```css
.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)*.5);padding-left:calc(var(--bs-gutter-x)*.5);margin-top:var(--bs-gutter-y)}
```

Each occurrence is immediately preceded by its own `.row{...}` rule (the row's own flex-container styling, e.g. `display:flex;flex-wrap:wrap;margin-right:calc(-.5*var(--bs-gutter-x));margin-left:calc(-.5*var(--bs-gutter-x))`) — the two always appear as an adjacent pair, once per duplicated block. `.row` itself is **not** part of this fix: it's a pure page-agnostic reset with identical values in all 10 copies and no competing same-specificity override elsewhere in the file, so duplicate copies of `.row` don't cause any cascade conflict (whichever copy "wins" computes the same result).

The fix: delete 9 of the 10 `.row>*` occurrences, keeping only the **first** one (verified to already sit before every `.col-*` rule in the file — the earliest `.col-*` rule starts at a later position than the first `.row>*`, so no repositioning is needed, only deletion of the 9 redundant later copies). Each corresponding `.row{...}` rule that used to sit next to a now-deleted `.row>*` stays exactly where it is — only the `.row>*{...}` text itself is removed from those 9 spots.

### Why deletion-only, no repositioning

Since `.row>*`'s value is identical everywhere and doesn't depend on which page's block it came from, there's no page-specific content to preserve in the 9 deleted copies — removing them is lossless. This is simpler and more robust than the alternative (moving each page's `.col-*` overrides to always sit after the last duplicate) because it removes the redundant rule entirely rather than requiring future edits to `content.css` to remember a positioning rule that isn't enforced by anything.

## Testing

- **Local**: confirm `content.css` still parses (brace-balance check), confirm exactly 1 occurrence of `.row>*{flex-shrink:0...}` remains (down from 10), confirm all 10 `.row{...}` rules are still present and unchanged (only `.row>*` was touched).
- **Production CLS re-measurement (the actual regression test)** — same real desktop methodology used to find and trace this bug (Puppeteer, 1920×1080, Lighthouse-equivalent throttling, cold `browser.createBrowserContext()` per run): re-measure `contact-us.php` (confirmed baseline: 0.1878–0.1882) plus the other pages using `content.css`'s critical variant (`privacy.php`, `refunds-cancellations.php`, `gallery.php`, `blog.php`) before/after. Expect `contact-us.php` to drop into "Good" range (≤0.1, ideally near-zero, matching the homepage's already-clean 0.0003). A real measurement is required — this is exactly the kind of bug (CSS cascade behavior) where "the fix looks obviously correct" isn't sufficient on its own, given the previous fix in this same investigation looked correct and didn't move the number at all.
- **Regression check on already-good pages**: re-measure the homepage and `discover-santiago-city-tour.php` (already 0.0003 and 0.0037–0.0221, using `home.css`/`tour.css` — untouched by this fix) to confirm no unrelated regression.
- **`gallery.php` and `blog.php` production measurement (2026-08-12, post-fix)**: these two pages also use `content.css`'s critical variant (confirmed via `grep -n "critical_css_file" *.php`) but were missing from the original page inventory above and had never been measured, before or after the fix. Measured with the exact same methodology (Puppeteer, 1920×1080, Lighthouse-equivalent throttling, cold `browser.createBrowserContext()` per run), two runs each: `gallery.php` CLS=0.0003 (both runs), `blog.php` CLS=0.0141 (both runs) — both comfortably in the "Good" (≤0.1) range, confirming the `.row>*` dedup fix covers them too.
- **Visual sanity check**: load `contact-us.php` and at least one other affected page in a real browser, confirm the grid layout renders identically to before (support box still centered at the correct width, footer columns unchanged) — this fix should be visually invisible; it only changes *when* the correct layout first appears, not what the final layout looks like.

## Risks

- **Verifying no other rule in `content.css`'s 66 ten-times-duplicated selectors has the same hazard** was done by checking for same-specificity competing overrides against the current file contents — this is a snapshot-in-time check, not a structural guarantee. If a future edit adds a new page-specific override to `content.css` with the same specificity as an existing duplicated rule, the same class of bug could recur. Not fixed structurally here (see Non-goals) — worth a code comment at the fix site flagging the hazard for future editors, matching this codebase's established convention of leaving hazard comments at drift-risk spots (e.g. `vendors.unminified.css`'s regeneration-source warning).
- **Low risk of visual regression**: the fix is a pure deletion of redundant, byte-identical rules — no new values introduced, no rule's final computed effect changes on any page, only the cascade's intermediate/pre-bootstrap-load state improves. The Testing section's visual sanity check directly covers this.
