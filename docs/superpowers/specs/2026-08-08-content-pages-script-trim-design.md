# Content pages: remove unused script includes

## Context

While investigating the tour-gallery script-loading problem, the user asked whether `contact-us.php` — a simple page with no gallery, no sidebar, no interactive widgets — could be optimized while keeping identical visual output. `contact-us.php` shares `includes/content-scripts.php` with 5 other pages (privacy.php, refunds-cancellations.php, blog.php, blog-post.php, gallery.php). That include loads jQuery-UI (from a CDN), Slider Pro (96 KB), and theia-sticky-sidebar (16 KB) on every one of those 6 pages.

A site-wide grep for every call site of these libraries (`.sliderPro(`, `.theiaStickySidebar(`, and every common jQuery-UI widget method — `.autocomplete(`, `.datepicker(`, `.dialog(`, `.draggable(`, `.sortable(`, `.tabs(`, `.tooltip(`, `.accordion(`) confirms none of the 6 pages ever invoke any of them. `.sliderPro(` is only ever called from `js/tours.js` and `cruise-transfer.php` (neither uses `content-scripts.php`). `.theiaStickySidebar(` is only ever called from `includes/tour-scripts.php` and `cruise-transfer.php` (same). jQuery-UI widget methods are only used in `return.php`, `js/transfer.js`, `admin/preferentials.php`, and a legacy Revolution Slider vendor file — none of which load `content-scripts.php`.

This is pure dead code being shipped to 6 pages, not a judgment call — removing it cannot change page appearance or behavior, since nothing on those pages ever calls it.

## Goals

- Remove the 3 confirmed-unused `<script>` tags from `includes/content-scripts.php`.
- Zero visual or functional change on any of the 6 affected pages.

## Non-goals

- `common_scripts_min.js` (also loaded by this include, ~208 KB) is not addressed here — it's a larger, multi-purpose bundle likely containing code some of these pages *do* use (WOW animations, etc.), and confirming what's safe to remove from it needs its own investigation. Flagged as a possible future follow-up, not bundled into this low-risk change.
- `bootstrap.bundle.min.js` appears to also be partially duplicated inside `common_scripts_min.js` (noted during the tour-gallery final review) — also out of scope here for the same reason; needs its own careful investigation before touching.
- No changes to `includes/tour-scripts.php` or `cruise-transfer.php` — those pages genuinely use all three libraries being removed here; this change is scoped entirely to `includes/content-scripts.php`.

## Design

Delete these 3 lines from `includes/content-scripts.php`:

```html
<script src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js"></script>
<script src="/js/jquery.sliderPro.min.js"></script>
<script src="/js/theia-sticky-sidebar.js"></script>
```

Resulting file:

```html
<?php /* includes/content-scripts.php
 * Shared trailing <script> block for the 3 content pages
 * (contact-us, privacy, refunds-cancellations). No parameters.
 */
?>
<!-- Scripts (jQuery, Bootstrap, plugins) -->
<script src="/js/jquery-3.7.1.min.js"></script>
<script src="/js/bootstrap.bundle.min.js"></script>
<script src="/js/common_scripts_min.js"></script>
<script src="/js/functions.js"></script>
```

(The file's header comment says "3 content pages" but 6 actually include it today — blog.php, blog-post.php, and gallery.php were added later without updating the comment. Update the comment to list all 6 while making this edit, since it's directly adjacent and already inaccurate.)

No other files change. No `defer`/timing changes here — this is pure removal, not a loading-strategy change, so it carries none of the ordering risk the tour-gallery defer work has.

## Testing

Load all 6 affected pages (contact-us, privacy, refunds-cancellations, blog, blog-post, gallery) before and after the change and confirm:
- No JS console errors introduced.
- No visual difference (the pages don't render anything from the removed libraries today, so there should be literally nothing to compare beyond "still looks the same").
- Page weight measurably drops (verify via network tab or `curl` content-length sum of the 3 removed files + the jQuery-UI CDN response).

## Risks

- Effectively none — this removes code with zero confirmed call sites across the entire repo. The only way this could break something is if a call site exists that the grep patterns didn't catch (e.g., a jQuery-UI widget method not in the checked list, or a dynamically-constructed method name) — the testing step's "load all 6 pages, check for console errors" is the real safety net for that residual risk.
