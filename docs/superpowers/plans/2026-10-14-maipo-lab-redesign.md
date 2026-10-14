# Maipo Page Visual Redesign ("Lab") Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the body of `maipo-valley-wine-tour-santiago.php` as a dark landing page (charcoal / teal / coral, landing-page flow, sticky CTA bar, new photo set). Use Tailwind v4 and reusable PHP components. Booking, pricing, SEO and every other page must behave exactly as they do today.

**Architecture:**
- **Styles:** the Tailwind v4 standalone CLI (pinned, downloaded by `bin/build-css`) compiles `css/src/lab.css` into a committed, minified `css/lab.css`. All classes use the `tw:` prefix. There is no preflight and no `@layer`. A small hand-written base, scoped under `.lab`, neutralises the site's global element rules.
- **Markup:** pure-render PHP components in `includes/ui/` (one file per component, auto-loaded by `includes/ui/ui.php`). Page copy and photo data live in `includes/tours/maipo_lab_content.php`.
- **Behaviour:** the existing booking scripts (`js/tours.js`, `js/maipo-options.js`) keep working on the same ids and attributes. A new `js/lab.js` adds the gallery lightbox and the sticky bar.

**Tech Stack:** PHP 8.3 (server; 8.5 locally), Tailwind CSS v4.1.11 standalone CLI, vanilla JS + the existing jQuery stack, PHPUnit (`tests/tools/phpunit.phar`), Playwright 1.47.2 (scratch dir), Lighthouse 12.6.0 via npx, macOS `sips` + `cwebp`.

**Spec:** `docs/superpowers/specs/2026-10-14-maipo-lab-redesign-design.md`

## Global Constraints

- Work on branch `feature/maipo-lab-redesign`. Never `git push`; the owner pushes.
- Stage files only by explicit path. **Never stage `portillo-inca-lagoon-andes-mountains-vineyard.php`.** It holds an unrelated uncommitted edit by the owner. Never use `git add -A` or `git add .`.
- **PHP version:** code must run on PHP 8.3. No 8.4+ features (property hooks, `array_find`, asymmetric visibility, `new` without parentheses in chains).
- **Tailwind classes:**
  - Every class uses the `tw:` prefix, with variants after it: `tw:md:grid-cols-3`, `tw:hover:bg-card`, `tw:has-checked:border-teal`.
  - Class strings must be **literal** in the PHP source, because `bin/build-css` scans the files for them. Never build a class name by concatenation or interpolation.
- **Rebuild CSS:** after any change to `includes/ui/*.php`, `css/src/*.css` or `maipo-valley-wine-tour-santiago.php`, run `bin/build-css` and commit `css/lab.css` in the same commit. `tests/LabCssTest.php` fails if it is stale.
- **No `@layer` anywhere in `css/src/`.** The site's Bootstrap and `style.css` are unlayered, and unlayered rules beat every layered rule.
- **`css/src/lab-base.css` selector rules:**
  - Element resets use `.lab :where(...)`.
  - Component classes use `:where(.lab) .lab-x`.
  - Both are specificity (0,1,0) and are emitted before the utilities, so a `tw:` utility always wins a conflict.
- **Design tokens** (from the spec):

  | Token | Value |
  |---|---|
  | `bg` | `#1c1f21` |
  | `card` | `#262a2d` |
  | `line` | `rgba(255,255,255,.09)` |
  | `text` | `#e9ecee` |
  | `muted` | `#a8b0b5` |
  | `teal` | `#008489` |
  | `teal-soft` | `#5fd0d4` |
  | `coral` | `#e04f67` (large or bold text only) |

  Font: Montserrat. Radius: 8px for controls, 12px for cards, 16px for the booking panel.
- **Booking DOM contract (must survive byte-for-byte in meaning):**
  - `form#booking[action="book_tour.php"]`
  - Hidden fields: `#activity_name`, `#option_2` (checkbox, always unchecked)
  - Contact and date fields: `#name_booking`, `#last_name_booking`, `#email_booking`, `#phone_booking`, `input.date-pick#date_booking`
  - Passenger counts: `#adults`, `#children`, `#infants` (each `input.qty2` inside `.numbers-row`)
  - Coupon: `#coupon_code`, `#apply_coupon_valpo`
  - Totals: `#total_price`, `#new_total_container` (inline `style="display:none;"`), `#new_total_price`
  - `#booking button[type="submit"]`
  - Option radios inside `fieldset[data-tour-options]`, each with `data-booking-lock`, ids `<name>-<slug>`, each wrapped in its `<label for=…>`
  - Per-option blocks `<div data-option="<slug>">` / `<div data-option="<slug>" hidden>`
- **Copy rules:**
  - Lunch is always "at a winery restaurant". Never name the lunch winery, and never say TerraMater serves lunch.
  - Lunch is included **only** on Classic + Lunch. Any photo caption that mentions lunch must say "Classic + Lunch".
  - No "Most popular" tag.
  - Keep the substitution note: "If a winery is unavailable on your date, it may be replaced with another of equal quality."
  - Keep the H1 text exactly: "Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries".
- **Asset version** for files new or changed in this plan: `?v=20261014`.
- **Tests:**
  - PHPUnit: `php tests/tools/phpunit.phar` (from the repo root). There is 1 pre-existing deprecation in `ViatorCategorizationTest`; ignore it.
  - Local server: `php -S 127.0.0.1:8099` from the repo root. It uses `../db_config.php`, the local dev DB.
  - Never let a browser test load `shopping.php` (it holds live payment-gateway credentials). Always `page.route(/shopping\.php/, r => r.abort())`.
  - The only DB writes allowed are e2e test bookings with emails `e2e-<slug>@example.test` in the **local** DB, deleted afterwards.
- **Scratch tooling (outside the repo):**
  - Playwright lives in `/private/tmp/claude-501/-Users-miguelmontero-Documents-superpowers/fc68a621-696e-4e18-b43f-5506afb46d53/scratchpad/pw/` (has `node_modules/playwright`, `check.js`, `e2e.js`).
  - Chromium: `/Users/miguelmontero/Library/Caches/ms-playwright/chromium_headless_shell-1243/chrome-headless-shell-mac-arm64/chrome-headless-shell`.
  - Metrics and screenshots go in `STAMP/.superpowers/lab-metrics/` (git-ignored).
- Commit trailer: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Rulings (decisions this plan makes where the spec is silent or wrong)

1. **No `layer(...)` on the Tailwind imports, and no `@layer components`.** The spec text shows them, but layered rules lose to unlayered Bootstrap and `style.css` whatever their specificity. A spike confirmed that unlayered prefixed output works and is deterministic. Base CSS lives in `css/src/lab-base.css`, imported between the theme and the utilities. Cost if wrong: none functionally.
2. **No image is upscaled.**
   - The hero source is only 1280×960, so its sizes are 480/960/1280 (not up to 2400). Preloads use 960 (mobile) and 1280 (desktop) WebP.
   - The hero `<picture>` uses media-query sources that mirror `head.php`'s `(max-width: 767px)` preload split, not `srcset`/`sizes`. `head.php` documents a real double-download bug with the DPR-aware variant.
   - Gallery photos get 480/960/1600 WebP plus one 960 JPG fallback.
3. **The gallery shows 7 photos.** The hero photo is already full-width above, and 7 tiles fill the approved mosaic exactly (desktop: one tall + 4 + one + one wide; mobile: one full-width + 6). The spec said 8. Cost if wrong: add one tile.
4. **Three option radio groups, not two:**
   - `tour_option`: route cards.
   - `tour_option_itin`: itinerary switch.
   - `tour_option_book`: a compact selector inside the booking panel, as in the mockup's "Route" field.

   `js/maipo-options.js` already syncs every `[data-tour-options]` radio by value, and the coupon lock disables every `[data-booking-lock]`. So no JS changes are needed.
5. **The old header "Book now" button in `js/tours.js` would appear on the new page.** `outerHeight()` of an empty jQuery set is `undefined`, which falls back to 470, so it is not hidden as the spec assumed. `tours.js` gets a guard: skip it when the page has `[data-sticky-cta]`. Other pages are unchanged, including the admin `booking_manual.php`.
6. **Smooth scrolling is CSS only.** `includes/critical/tour.css` already sets `html{scroll-behavior:smooth}`, and `lab.css` turns it off under `prefers-reduced-motion`. `lab.js` does no scroll handling.
7. **The intro prose stays.** The existing "Spend a day in Chilean wine country…" paragraph becomes the intro of the "Why this tour" section, so the page doesn't lose its main descriptive copy for SEO. The rest of the old prose is covered by the itinerary stops.
8. **The "Save 10%" banner badge is dropped** together with the old banner (the spec removes the banner; the mockup has no such badge).

## Review Focus

1. **Coupon applied, then the traveler clicks another route.** All 9 radios (3 groups) must be disabled and the total must not change. The Task 9 browser check asserts all `[data-booking-lock]` are disabled after the coupon (`check.js` check 5, tightened to `=== 9`).
2. **An option has no DB price** (row missing or renamed).
   - Its card and segment show "Contact us" and are disabled.
   - The sticky bar falls back to "From $<lowest>".
   - With no prices at all, the bar shows only the CTA.

   Covered by PHPUnit in `UiOptionsTest`.
3. **Narrow phones (390px).** No horizontal scroll, the booking grid collapses to one column, and the 3-up passenger counters still fit. The Task 9 browser check asserts `scrollWidth <= clientWidth` at 390×844.
4. **JavaScript disabled or slow.** The server-selected option's itinerary and checklist are visible, the others are `hidden`, and gallery links still open the image file. Covered by PHPUnit (`hidden` attributes) and a JS-disabled browser context in Task 9.
5. **A malformed `?option=`** (array, unknown, mixed case). The page renders with no PHP warnings and opens on Classic. Task 8 runs curl smoke checks with `?option[]=x` and `?option=PREMIUM` and checks the server log for warnings.

---

### Task 1: Tailwind build pipeline, lab base CSS, and performance baseline

**Files:**
- Create: `bin/build-css`
- Create: `css/src/lab.css`
- Create: `css/src/lab-base.css`
- Create (generated): `css/lab.css`
- Test: `tests/LabCssTest.php`

**Interfaces:**
- Produces:
  - `bin/build-css` (build) and `bin/build-css --check` (exit 1 if stale).
  - Theme variables `--tw-color-{bg,card,line,text,muted,teal,teal-soft,coral}` and `--tw-font-sans`.
  - Utilities such as `tw:bg-card`, `tw:text-coral`, `tw:border-line`.
  - Component classes for later tasks: `.lab`, `.lab-eyebrow`, `.lab-card`, `.lab-btn`, `.lab-label`, `.lab-input`, `.lab-timeline`, `.lab-stop`, `.lab-mosaic`, `.lab-lightbox`, `.lab-acc`, `.lab-acc-icon`, `.lab-prose`, `.lab-sticky`, plus `.numbers-row` overrides inside `.lab`.

- [ ] **Step 1: Capture the "before" performance baseline (current page, before any change)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
mkdir -p .superpowers/lab-metrics
php -S 127.0.0.1:8099 > .superpowers/lab-metrics/server.log 2>&1 &
SERVER_PID=$!
sleep 1
for i in 1 2 3; do
  CHROME_PATH="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  npx --yes lighthouse@12.6.0 "http://127.0.0.1:8099/maipo-valley-wine-tour-santiago.php" \
    --only-categories=performance --output=json \
    --output-path=".superpowers/lab-metrics/before-$i.json" \
    --chrome-flags="--headless=new" --quiet
done
node -e 'for (const f of process.argv.slice(1)) { const r = require(f), a = r.audits; console.log(f.split("/").pop(), "perf", Math.round(r.categories.performance.score*100), "LCP", Math.round(a["largest-contentful-paint"].numericValue), "CLS", a["cumulative-layout-shift"].numericValue.toFixed(3), "TBT", Math.round(a["total-blocking-time"].numericValue)); }' \
  "$PWD"/.superpowers/lab-metrics/before-*.json | tee .superpowers/lab-metrics/before.txt
kill $SERVER_PID
```

Expected: three lines in `before.txt`, each with a perf score, LCP, CLS and TBT. If Lighthouse cannot run, record the error in `before.txt` and continue. Task 9 then compares with the same fallback.

- [ ] **Step 2: Write the failing test**

Create `tests/LabCssTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LabCssTest extends TestCase
{
    private const CSS = __DIR__ . '/../css/lab.css';

    public function testCompiledCssIsUpToDate(): void
    {
        exec(escapeshellarg(__DIR__ . '/../bin/build-css') . ' --check 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function testNoPreflightNoLayersAndTokensPresent(): void
    {
        $css = (string)file_get_contents(self::CSS);
        $this->assertStringNotContainsString('@layer', $css);
        $this->assertStringNotContainsString('::file-selector-button', $css); // preflight marker
        $this->assertStringContainsString('--tw-color-teal:#008489', $css);
        $this->assertStringContainsString('--tw-color-coral:#e04f67', $css);
        $this->assertStringContainsString('.lab{', $css);
    }

    public function testSizeBudget(): void
    {
        $this->assertLessThanOrEqual(30 * 1024, filesize(self::CSS));
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter LabCssTest`
Expected: FAIL (`bin/build-css` not found / `css/lab.css` missing).

- [ ] **Step 4: Create `bin/build-css`**

```bash
#!/usr/bin/env bash
# bin/build-css - compile css/src/lab.css -> css/lab.css with the pinned
# Tailwind v4 standalone CLI (downloaded once into bin/.tailwind/, which is
# git-ignored). css/lab.css is committed because the host has no Node.
#   bin/build-css          build css/lab.css
#   bin/build-css --check  exit 1 if css/lab.css is stale vs its sources
# See docs/superpowers/specs/2026-10-14-maipo-lab-redesign-design.md
set -euo pipefail

TW_VERSION="4.1.11"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

case "$(uname -s)-$(uname -m)" in
  Darwin-arm64)  asset=tailwindcss-macos-arm64 ;;
  Darwin-x86_64) asset=tailwindcss-macos-x64 ;;
  Linux-x86_64)  asset=tailwindcss-linux-x64 ;;
  Linux-aarch64) asset=tailwindcss-linux-arm64 ;;
  *) echo "bin/build-css: unsupported platform $(uname -s)-$(uname -m)" >&2; exit 1 ;;
esac

BIN="$ROOT/bin/.tailwind/tailwindcss-$TW_VERSION"
if [ ! -x "$BIN" ]; then
  mkdir -p "$ROOT/bin/.tailwind"
  curl -fsSL -o "$BIN.tmp" "https://github.com/tailwindlabs/tailwindcss/releases/download/v$TW_VERSION/$asset"
  chmod +x "$BIN.tmp"
  mv "$BIN.tmp" "$BIN"
fi

cd "$ROOT"
if [ "${1:-}" = "--check" ]; then
  tmp="$(mktemp)"
  trap 'rm -f "$tmp"' EXIT
  "$BIN" -i css/src/lab.css -o "$tmp" --minify >/dev/null 2>&1
  if cmp -s "$tmp" css/lab.css; then
    echo "css/lab.css is up to date"
  else
    echo "css/lab.css is stale - run bin/build-css" >&2
    exit 1
  fi
else
  "$BIN" -i css/src/lab.css -o css/lab.css --minify
  echo "css/lab.css: $(wc -c < css/lab.css | tr -d ' ') bytes"
fi
```

Then run `chmod +x bin/build-css`.

- [ ] **Step 5: Create `css/src/lab.css`**

```css
/* css/src/lab.css - Tailwind v4 entry for css/lab.css (build: bin/build-css).
 * Loaded only on pages that opted into the "lab" design (Maipo first).
 * - No preflight: the site's Bootstrap reboot stays the only global reset.
 * - Every utility is prefixed tw: so it can't collide with Bootstrap/CityTours
 *   class names (container, hidden, table, btn...).
 * - No @layer anywhere: the site's CSS is unlayered, and unlayered rules beat
 *   every layered rule regardless of specificity.
 * - lab-base.css is imported BEFORE the utilities so utilities win ties.
 * - source(none) + explicit @source: only these files are scanned for classes.
 */
@import "tailwindcss/theme.css" prefix(tw);
@import "./lab-base.css";
@import "tailwindcss/utilities.css" prefix(tw) source(none);

@source "../../maipo-valley-wine-tour-santiago.php";
@source "../../includes/ui";

@theme static {
  --color-bg: #1c1f21;
  --color-card: #262a2d;
  --color-line: rgba(255, 255, 255, .09);
  --color-text: #e9ecee;
  --color-muted: #a8b0b5;
  --color-teal: #008489;
  --color-teal-soft: #5fd0d4;
  --color-coral: #e04f67;
  --font-sans: Montserrat, "Montserrat-fallback", system-ui, sans-serif;
}
```

- [ ] **Step 6: Create `css/src/lab-base.css`**

```css
/* css/src/lab-base.css
 * Hand-written base for the "lab" design system. Everything is scoped under
 * .lab and kept at specificity (0,1,0):
 *   .lab :where(el)        element resets - beat the site's global element
 *                          rules (style.css h1-h6{color:#2a2a2a},
 *                          h1,h2,h3{margin...}, p{margin...}, a{color...})
 *   :where(.lab) .lab-x    component classes
 * The file is emitted before the Tailwind utilities, so a tw: utility always
 * wins a conflict. Pseudo-class rules (:hover, :focus) go one step higher on
 * purpose so they beat style.css's a:hover{color:#111}. */

.lab {
  overflow-x: clip; /* clip, not hidden: hidden would break position:sticky */
  background: var(--tw-color-bg);
  color: var(--tw-color-text);
  font-family: var(--tw-font-sans);
  font-size: 15px;
  line-height: 1.6;
}

/* ---- element resets ---- */
.lab :where(h1, h2, h3, h4, p, ul, ol, figure, fieldset, legend) { margin: 0; padding: 0; }
.lab :where(h1, h2, h3, h4) { color: #fff; font-family: inherit; }
.lab :where(ul, ol) { list-style: none; }
.lab :where(fieldset) { border: 0; min-width: 0; }
.lab :where(legend) { float: none; width: auto; font-size: inherit; line-height: inherit; }
.lab :where(label) { margin: 0; }
.lab :where(img) { max-width: 100%; height: auto; }
.lab :where(a) { color: var(--tw-color-teal-soft); text-decoration: none; }
.lab :where(a):hover, .lab :where(a):focus { color: #fff; }
.lab :where(:focus-visible) { outline: 2px solid var(--tw-color-teal-soft); outline-offset: 2px; }

/* ---- component classes ---- */
:where(.lab) .lab-eyebrow { color: var(--tw-color-coral); font-size: 12px; font-weight: 700; letter-spacing: 1.5px; line-height: 1.4; text-transform: uppercase; }
:where(.lab) .lab-card { background: var(--tw-color-card); border: 1px solid var(--tw-color-line); border-radius: 12px; }

:where(.lab) .lab-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 26px; background: var(--tw-color-teal); color: #fff; border: 0; border-radius: 8px; font-family: inherit; font-size: 13px; font-weight: 800; letter-spacing: .5px; line-height: 1.2; text-transform: uppercase; text-decoration: none; cursor: pointer; transition: background-color .15s ease; }
:where(.lab) .lab-btn:hover, :where(.lab) .lab-btn:focus { background: #006f73; color: #fff; }
:where(.lab) .lab-btn:disabled { opacity: .5; cursor: not-allowed; }

:where(.lab) .lab-label { display: block; margin-bottom: 4px; color: var(--tw-color-muted); font-size: 12px; font-weight: 600; }
:where(.lab) .lab-input { display: block; width: 100%; height: 44px; padding: 10px 12px; background: var(--tw-color-bg); border: 1px solid var(--tw-color-line); border-radius: 8px; color: var(--tw-color-text); font-family: inherit; font-size: 15px; }
:where(.lab) .lab-input::placeholder { color: var(--tw-color-muted); }
:where(.lab) .lab-input:focus { border-color: var(--tw-color-teal); outline: none; box-shadow: 0 0 0 2px rgba(0, 132, 137, .45); }
:where(.lab) .lab-input:disabled { opacity: .6; }

/* passenger counters: js/functions.js appends .inc/.dec.button_inc divs */
:where(.lab) .numbers-row { display: flex; align-items: stretch; width: 100%; height: 44px; background: var(--tw-color-bg); border: 1px solid var(--tw-color-line); border-radius: 8px; overflow: hidden; }
:where(.lab) .numbers-row .qty2 { order: 2; position: static; left: auto; flex: 1 1 auto; width: auto; min-width: 0; height: 100%; padding: 0; background: transparent; border: 0; border-radius: 0; color: var(--tw-color-text); font-size: 15px; font-weight: 700; text-align: center; }
:where(.lab) .numbers-row .qty2:focus { outline: none; box-shadow: none; border: 0; }
:where(.lab) .numbers-row .button_inc { order: 1; position: static; flex: 0 0 40px; display: flex; align-items: center; justify-content: center; width: 40px; height: 100%; background: none; border: 0; border-radius: 0; color: var(--tw-color-teal-soft); font-size: 18px; font-weight: 800; text-indent: 0; user-select: none; }
:where(.lab) .numbers-row .inc { order: 3; }

/* itinerary timeline */
:where(.lab) .lab-timeline { position: relative; margin-left: 12px; }
:where(.lab) .lab-timeline::before { content: ""; position: absolute; left: 0; top: 6px; bottom: 6px; width: 2px; background: linear-gradient(var(--tw-color-teal), rgba(0, 132, 137, .2)); }
:where(.lab) .lab-stop { position: relative; padding: 0 0 26px 30px; }
:where(.lab) .lab-stop:last-child { padding-bottom: 0; }
:where(.lab) .lab-stop::before { content: ""; position: absolute; left: -6px; top: 5px; width: 14px; height: 14px; border-radius: 50%; background: var(--tw-color-bg); border: 3px solid var(--tw-color-teal); }

/* gallery mosaic: mobile 2 cols (first tile full width); desktop 2fr 1fr 1fr
   with a tall first tile and a wide last tile (7 photos fill 3 rows) */
:where(.lab) .lab-mosaic { display: grid; grid-template-columns: 1fr 1fr; grid-auto-rows: 120px; gap: 8px; }
:where(.lab) .lab-mosaic > :first-child { grid-column: 1 / -1; grid-row: span 2; }
@media (min-width: 768px) {
  :where(.lab) .lab-mosaic { grid-template-columns: 2fr 1fr 1fr; grid-auto-rows: 180px; gap: 10px; }
  :where(.lab) .lab-mosaic > :first-child { grid-column: auto; }
  :where(.lab) .lab-mosaic > :last-child { grid-column: span 2; }
}

/* lightbox <dialog> */
:where(.lab) .lab-lightbox { width: min(1100px, 100vw); max-width: 100vw; max-height: 100dvh; padding: 0; background: transparent; border: 0; color: #fff; overflow: visible; }
:where(.lab) .lab-lightbox::backdrop { background: rgba(0, 0, 0, .88); }

/* <details> accordion */
:where(.lab) .lab-acc > summary { list-style: none; }
:where(.lab) .lab-acc > summary::-webkit-details-marker { display: none; }
:where(.lab) .lab-acc[open] .lab-acc-icon { transform: rotate(180deg); }

/* trusted rich text (accordion bodies) */
:where(.lab) .lab-prose { color: var(--tw-color-muted); font-size: 14px; }
:where(.lab) .lab-prose > * + * { margin-top: 10px; }
:where(.lab) .lab-prose ul { list-style: disc; padding-left: 20px; }
:where(.lab) .lab-prose li + li { margin-top: 4px; }
:where(.lab) .lab-prose h3 { color: #fff; font-size: 14px; font-weight: 700; margin-top: 14px; }
:where(.lab) .lab-prose h3:first-child { margin-top: 0; }
:where(.lab) .lab-prose strong { color: var(--tw-color-text); }

/* sticky CTA bar (sticks to the viewport bottom while <main> scrolls) */
:where(.lab) .lab-sticky { position: sticky; bottom: 0; z-index: 50; background: rgba(28, 31, 33, .96); border-top: 1px solid var(--tw-color-line); backdrop-filter: blur(6px); }

/* Page-level tweaks outside .lab, active only where lab.css is loaded */
body:has(.lab-sticky:not([hidden])) #toTop { bottom: 84px; }

@media (prefers-reduced-motion: reduce) {
  html { scroll-behavior: auto; }
  .lab *, .lab *::before, .lab *::after { transition: none !important; }
}
```

- [ ] **Step 7: Build and run the tests**

Run: `bin/build-css && php tests/tools/phpunit.phar --filter LabCssTest`
Expected: the build prints `css/lab.css: <N> bytes` (a few KB), and LabCssTest passes all 3 tests. `bin/build-css --check` prints "css/lab.css is up to date".

- [ ] **Step 8: Commit**

```bash
git add bin/build-css css/src/lab.css css/src/lab-base.css css/lab.css tests/LabCssTest.php
git commit -m "build: Tailwind v4 lab CSS pipeline (bin/build-css, tw: prefix, no preflight)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Resized photo set and page content data

**Files:**
- Create: `bin/make-images`
- Create (generated, committed): `img/Tours/Maipo/2026/*.webp`, `img/Tours/Maipo/2026/*.jpg`
- Create: `includes/tours/maipo_lab_content.php`
- Modify: `tests/bootstrap.php` (append one `require_once`)
- Test: `tests/MaipoLabContentTest.php`

**Interfaces:**
- Produces:
  - `maipo_lab_hero(?float $price_from): array` with keys:
    - `image`: `['mobile','desktop','fallback','w','h','alt']`
    - `title`, `highlight`, `lead`
    - `facts`: list of `['icon','text']`
    - `cta_href`, `cta_text`, `price_from`
  - `maipo_lab_photos(): array`: a list of `['base','w','h','alt','caption']`. `base` has no width or extension, e.g. `img/Tours/Maipo/2026/empanadas`, and `w`/`h` are the dimensions of the `-960` image.
  - `maipo_lab_benefits(): array`: a list of `['icon','title','text']`.
  - `maipo_lab_intro(): string`
  - `maipo_lab_practical(): array`: a list of `['q','html']` (trusted HTML).
  - Image files:
    - `img/Tours/Maipo/2026/vineyard-toast-{480,960,1280}.webp` and `vineyard-toast-1280.jpg`
    - For the other 7 slugs: `<slug>-{480,960,1600}.webp` and `<slug>-960.jpg`

- [ ] **Step 1: Create `bin/make-images`**

```bash
#!/usr/bin/env bash
# bin/make-images - one-off: resize the 2026 Maipo photo set into
# img/Tours/Maipo/2026/<slug>-<width>.webp plus one .jpg fallback per photo.
# Originals (multi-MB) stay OUTSIDE the repo; only these outputs are committed.
# Never upscales: list only widths <= the source width.
# Usage: bin/make-images [SRC_DIR]     (default: ../photos next to the repo)
# Needs macOS sips + cwebp (brew install webp).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:-$ROOT/../photos}"
OUT="$ROOT/img/Tours/Maipo/2026"
QUALITY="${WEBP_QUALITY:-72}"

command -v cwebp >/dev/null || { echo "bin/make-images: cwebp not found (brew install webp)" >&2; exit 1; }
mkdir -p "$OUT"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# slug|source file|webp widths|jpg fallback width
while IFS='|' read -r slug file widths jpgw; do
  [ -z "$slug" ] && continue
  src="$SRC/$file"
  [ -f "$src" ] || { echo "bin/make-images: missing $src" >&2; exit 1; }
  for w in $widths; do
    sips -s format jpeg -s formatOptions 92 --resampleWidth "$w" "$src" --out "$tmp/$slug-$w.jpg" >/dev/null
    cwebp -quiet -q "$QUALITY" -metadata none "$tmp/$slug-$w.jpg" -o "$OUT/$slug-$w.webp"
  done
  sips -s format jpeg -s formatOptions 72 --resampleWidth "$jpgw" "$src" --out "$OUT/$slug-$jpgw.jpg" >/dev/null
  echo "$slug: $(sips -g pixelWidth -g pixelHeight "$OUT/$slug-960.webp" 2>/dev/null | awk '/pixel/{printf "%s ", $2}')(960 webp w h)"
done <<'EOF'
vineyard-toast|PHOTO-2026-01-03-11-47-39.jpeg|480 960 1280|1280
asado-grill|Portada.jpeg|480 960 1600|960
tasting-room|_MG_3977.jpeg|480 960 1600|960
pisco-sour|_MG_4039.jpeg|480 960 1600|960
toast-table|_MG_4389.jpeg|480 960 1600|960
empanadas|_MG_4228.jpeg|480 960 1600|960
red-wine-friends|cf93172a-8b95-47d7-945c-f9c61d9fc5d4.jpeg|480 960 1600|960
asado-skewer|_MG_4129.jpeg|480 960 1600|960
EOF
ls -l "$OUT"
```

Run `chmod +x bin/make-images`, then `bin/make-images`.
Expected: 8 summary lines and 32 files in `img/Tours/Maipo/2026/` (8×3 WebP + 8 JPG). If `sips` cannot read WebP for the size line, read the sizes in Step 2 instead.

- [ ] **Step 2: Inspect the outputs**

Run:

```bash
for f in img/Tours/Maipo/2026/*-960.jpg img/Tours/Maipo/2026/vineyard-toast-1280.jpg; do echo "$(sips -g pixelWidth -g pixelHeight "$f" | awk '/pixel/{printf "%s ", $2}') $(stat -f%z "${f%.jpg}.webp" 2>/dev/null) $f"; done
ls -l img/Tours/Maipo/2026/*-960.webp img/Tours/Maipo/2026/vineyard-toast-1280.webp
```

Then open each `*-480.webp` with the Read tool and confirm that it shows the scene described by its alt text in Step 4. If a description is wrong, fix the alt text, keeping the caption lunch rule. Budgets:
- Every `*-960.webp` must be ≤ 120 KB. If one is over, rerun with `WEBP_QUALITY=65 bin/make-images`.
- `vineyard-toast-1280.webp` must be ≤ 250 KB.

- [ ] **Step 3: Write the failing test**

Create `tests/MaipoLabContentTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaipoLabContentTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function testGalleryPhotosExistWithMatchingDimensionsAndBudgets(): void
    {
        $photos = maipo_lab_photos();
        $this->assertCount(7, $photos);
        foreach ($photos as $p) {
            foreach ([480, 960, 1600] as $w) {
                $this->assertFileExists(self::ROOT . "/{$p['base']}-$w.webp");
            }
            $jpg = self::ROOT . "/{$p['base']}-960.jpg";
            $this->assertFileExists($jpg);
            [$w, $h] = getimagesize($jpg);
            $this->assertSame([$p['w'], $p['h']], [$w, $h], $p['base']);
            $this->assertLessThanOrEqual(120 * 1024, filesize(self::ROOT . "/{$p['base']}-960.webp"), $p['base']);
        }
    }

    public function testHeroImageFilesAndBudget(): void
    {
        $img = maipo_lab_hero(110.0)['image'];
        foreach (['mobile', 'desktop', 'fallback'] as $k) {
            $this->assertFileExists(self::ROOT . '/' . $img[$k], $k);
        }
        [$w, $h] = getimagesize(self::ROOT . '/' . $img['fallback']);
        $this->assertSame([$img['w'], $img['h']], [$w, $h]);
        $this->assertLessThanOrEqual(250 * 1024, filesize(self::ROOT . '/' . $img['desktop']));
    }

    public function testAltTextIsDescriptiveAndUnique(): void
    {
        $alts = array_column(maipo_lab_photos(), 'alt');
        $alts[] = maipo_lab_hero(null)['image']['alt'];
        foreach ($alts as $alt) {
            $this->assertGreaterThanOrEqual(15, strlen($alt), $alt);
        }
        $this->assertSame(count($alts), count(array_unique($alts)));
    }

    public function testCaptionsNeverImplyLunchIsIncludedOnEveryOption(): void
    {
        foreach (maipo_lab_photos() as $p) {
            $text = $p['alt'] . ' ' . $p['caption'];
            if (stripos($text, 'lunch') !== false) {
                $this->assertStringContainsString('Classic + Lunch', $p['caption'], $p['base']);
            }
            $this->assertStringNotContainsStringIgnoringCase('terramater', $text);
        }
    }

    public function testHeroCopy(): void
    {
        $hero = maipo_lab_hero(110.0);
        $this->assertSame('Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries', $hero['title']);
        $this->assertStringContainsString($hero['highlight'], $hero['title']);
        $this->assertSame('Four wineries, pisco tasting and a lunch stop at a winery restaurant — with hotel pickup and an English-speaking guide.', $hero['lead']);
        $this->assertSame(['10 hours', 'Hotel pickup', 'Max 15 travelers', 'English guide', 'Free cancellation 24 h'], array_column($hero['facts'], 'text'));
        $this->assertSame(110.0, $hero['price_from']);
        $this->assertSame('#book', $hero['cta_href']);
    }

    public function testBenefitsIntroAndPracticalInfo(): void
    {
        $benefits = maipo_lab_benefits();
        $this->assertCount(4, $benefits);
        foreach ($benefits as $b) {
            $this->assertSame(['icon', 'title', 'text'], array_keys($b));
        }
        $this->assertStringStartsWith('Spend a day in Chilean wine country around Isla de Maipo', maipo_lab_intro());
        $practical = maipo_lab_practical();
        $this->assertSame(['Pickup & return', 'Additional information', 'Cancellation policy'], array_column($practical, 'q'));
        $this->assertStringContainsString('at least 24 hours before the experience start time', $practical[2]['html']);
        $this->assertStringContainsString('maximum of 15 travelers', $practical[1]['html']);
    }
}
```

Append to `tests/bootstrap.php`:

```php

require_once __DIR__ . '/../includes/tours/maipo_lab_content.php';
```

- [ ] **Step 4: Run the test to verify it fails, then write `includes/tours/maipo_lab_content.php`**

Run: `php tests/tools/phpunit.phar --filter MaipoLabContentTest`. Expected: a fatal error, because `includes/tours/maipo_lab_content.php` does not exist yet.

Create the file. Gallery `w`/`h` are the `-960.jpg` dimensions. The values below are computed from the source aspect ratios; replace them with the real numbers printed in Step 2 if they differ. The `toast-table` height is 696 or 697.

```php
<?php
// includes/tours/maipo_lab_content.php
// Copy + photo data for the Maipo "lab" landing page
// (maipo-valley-wine-tour-santiago.php). Pure data - rendering lives in
// includes/ui/. Option content (stops, included lists) stays in
// includes/tours/maipo_options.php.
// Copy rule: lunch is always "at a winery restaurant" and is included on
// Classic + Lunch ONLY - never imply it is included on every option.
// Images: generated by bin/make-images into img/Tours/Maipo/2026/.

function maipo_lab_hero(?float $price_from): array
{
    return [
        'image' => [
            'mobile' => 'img/Tours/Maipo/2026/vineyard-toast-960.webp',
            'desktop' => 'img/Tours/Maipo/2026/vineyard-toast-1280.webp',
            'fallback' => 'img/Tours/Maipo/2026/vineyard-toast-1280.jpg',
            'w' => 1280,
            'h' => 960,
            'alt' => 'Small group raising glasses of wine in a toast among Maipo Valley vineyards',
        ],
        'title' => 'Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries',
        'highlight' => 'Maipo Valley',
        'lead' => 'Four wineries, pisco tasting and a lunch stop at a winery restaurant — with hotel pickup and an English-speaking guide.',
        'facts' => [
            ['icon' => 'clock', 'text' => '10 hours'],
            ['icon' => 'map-pin', 'text' => 'Hotel pickup'],
            ['icon' => 'users', 'text' => 'Max 15 travelers'],
            ['icon' => 'message-circle', 'text' => 'English guide'],
            ['icon' => 'rotate-ccw', 'text' => 'Free cancellation 24 h'],
        ],
        'cta_href' => '#book',
        'cta_text' => 'Check availability',
        'price_from' => $price_from,
    ];
}

/** Gallery order matters: first = tall tile, last = wide tile (desktop). */
function maipo_lab_photos(): array
{
    $dir = 'img/Tours/Maipo/2026/';
    return [
        ['base' => $dir . 'asado-grill', 'w' => 960, 'h' => 664, 'alt' => 'Travelers gathered around an asado grill at a countryside restaurant', 'caption' => 'Asado at the winery-restaurant lunch stop (lunch included on Classic + Lunch only)'],
        ['base' => $dir . 'tasting-room', 'w' => 960, 'h' => 640, 'alt' => 'Traveler tasting a glass of red wine in a winery tasting room', 'caption' => 'Guided tasting in the cellar'],
        ['base' => $dir . 'pisco-sour', 'w' => 960, 'h' => 640, 'alt' => 'Pisco sours served in glasses on a wooden table', 'caption' => "Pisco sours, Chile's signature drink"],
        ['base' => $dir . 'toast-table', 'w' => 960, 'h' => 696, 'alt' => 'Travelers raising wine glasses in a toast around a table', 'caption' => 'Cheers to the Maipo Valley'],
        ['base' => $dir . 'empanadas', 'w' => 960, 'h' => 640, 'alt' => 'Freshly baked Chilean empanadas on a plate', 'caption' => 'Empanadas at the lunch stop (lunch included on Classic + Lunch only)'],
        ['base' => $dir . 'red-wine-friends', 'w' => 960, 'h' => 640, 'alt' => 'Two friends smiling over glasses of red wine at a winery', 'caption' => 'Red wine with friends'],
        ['base' => $dir . 'asado-skewer', 'w' => 960, 'h' => 640, 'alt' => 'Close-up of grilled meat skewers from a Chilean asado', 'caption' => 'Chilean asado (lunch included on Classic + Lunch only)'],
    ];
}

function maipo_lab_benefits(): array
{
    return [
        ['icon' => 'wine', 'title' => '4 wineries + pisco', 'text' => 'From a family farm with alpacas to established Maipo wineries, with guided tastings and a Chilean pisco tasting.'],
        ['icon' => 'users', 'title' => 'Small group', 'text' => "A maximum of 15 travelers, so there's time for questions and photos."],
        ['icon' => 'map-pin', 'title' => 'Hotel pickup', 'text' => 'Door-to-door from Santiago Centro, Providencia, Las Condes, Vitacura and Recoleta.'],
        ['icon' => 'rotate-ccw', 'title' => 'Free cancellation', 'text' => 'Cancel up to 24 hours before the tour for a full refund.'],
    ];
}

function maipo_lab_intro(): string
{
    return 'Spend a day in Chilean wine country around Isla de Maipo, a farming town named for the tributaries of the Maipo River that surround it and make for perfect grape-growing conditions. Every option visits four stops, from a small family farm to established wineries, with guided tastings, hotel pickup and an English-speaking guide.';
}

/** Practical info accordion. 'html' is trusted, author-written markup
 *  (text copied unchanged from the previous page version). */
function maipo_lab_practical(): array
{
    return [
        [
            'q' => 'Pickup & return',
            'html' => '<h3>Start</h3><p>Multiple pickup locations offered.</p>'
                . '<h3>Pickup details</h3><p>Pick ups are available from central locations in the following districts: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Airport Area.</p>'
                . '<p>If your hotel is not in the available areas please send us the location and we will evaluate if it is possible to pick up; otherwise we will provide a meeting point at the nearest place from your location.</p>'
                . '<h3>Hotel pickup offered</h3><p>During checkout you will be able to select from the list of included hotels.</p>'
                . '<h3>End</h3><p>This activity ends back at the meeting point.</p>',
        ],
        [
            'q' => 'Additional information',
            'html' => '<ul>'
                . '<li>Confirmation will be received at time of booking</li>'
                . '<li>Wheelchair accessible</li>'
                . '<li>Stroller accessible</li>'
                . '<li>Minimum numbers apply. There is a possibility of cancellation after confirmation if there are not enough passengers (4) to meet requirements. In the event of this occurring, you will be offered an alternative or full refund.</li>'
                . '<li>This experience requires good weather. If it’s canceled due to poor weather, you’ll be offered a different date or a full refund.</li>'
                . '<li>This tour/activity will have a maximum of 15 travelers</li>'
                . '<li>On national holidays some locations may close; in such cases the itinerary may be adjusted or replaced with a vineyard of equal or higher quality.</li>'
                . '</ul>',
        ],
        [
            'q' => 'Cancellation policy',
            'html' => '<p>For a full refund, you must cancel at least 24 hours before the experience start time.</p><ul>'
                . '<li>If you cancel less than 24 hours before the experience’s start time, the amount you paid will not be refunded.</li>'
                . '<li>Any changes made less than 24 hours before the experience’s start time will not be accepted.</li>'
                . '<li>Cut-off times are based on the experience’s local time.</li>'
                . '<li>This experience requires a minimum number of travelers. If it’s canceled because the minimum isn’t met, you’ll be offered a different date/experience or a full refund.</li>'
                . '</ul>',
        ],
    ];
}
```

- [ ] **Step 5: Run the tests**

Run: `php tests/tools/phpunit.phar --filter MaipoLabContentTest`
Expected: PASS (6 tests). If the dimension assertion fails, copy the actual `[w, h]` from the failure message into `maipo_lab_photos()` and rerun.

- [ ] **Step 6: Commit**

```bash
git add bin/make-images img/Tours/Maipo/2026 includes/tours/maipo_lab_content.php tests/MaipoLabContentTest.php tests/bootstrap.php
git commit -m "content: resized 2026 Maipo photo set and lab page copy data

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: UI helpers and basic components (award, section, accordion, checklist, benefits)

**Files:**
- Create: `includes/ui/ui.php`
- Create: `includes/ui/_helpers.php`
- Create: `includes/ui/award.php`
- Create: `includes/ui/section.php`
- Create: `includes/ui/accordion.php`
- Create: `includes/ui/checklist.php`
- Create: `includes/ui/benefits.php`
- Modify: `tests/bootstrap.php` (append one `require_once`)
- Modify (generated): `css/lab.css`
- Test: `tests/UiPrimitivesTest.php`

**Interfaces:**
- Consumes: the CSS classes from Task 1. The content arrays from Task 2 have the same shapes as the ones used here.
- Produces:
  - `ui_h($v): string`, `ui_price($amount): string` (`"$1,100"`), `ui_srcset(string $base, array $widths, string $ext = 'webp'): string`
  - `ui_icon(string $name, string $class = 'tw:size-5'): string`. Names: `clock`, `map-pin`, `users`, `message-circle`, `rotate-ccw`, `wine`, `check`, `x`, `chevron-down`, `chevron-left`, `chevron-right`, `shield-check`.
  - `ui_option_block_open(string $slug, string $selected): string` and `ui_option_block_close(): string`
  - `ui_award(string $variant = 'hero', string $note = ''): void`
  - `ui_section_open(string $id, string $eyebrow, string $title, string $intro = ''): void` and `ui_section_close(): void`
  - `ui_accordion(array $items, string $id): void`. Each item is `['q','a']` (plain text) or `['q','html']` (trusted HTML).
  - `ui_checklist(array $yes, array $no): void`
  - `ui_benefits(array $items): void`
  - `includes/ui/ui.php` loads `_helpers.php` and then every `includes/ui/[a-z]*.php`, so later tasks only add files.

- [ ] **Step 1: Write the failing test**

Create `tests/UiPrimitivesTest.php`:

```php
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
```

Append to `tests/bootstrap.php`:

```php

require_once __DIR__ . '/../includes/ui/ui.php';
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter UiPrimitivesTest`
Expected: a fatal error, because `includes/ui/ui.php` does not exist yet.

- [ ] **Step 3: Write `includes/ui/ui.php` and `includes/ui/_helpers.php`**

`includes/ui/ui.php`:

```php
<?php
// includes/ui/ui.php
// Loads the "lab" UI components: _helpers.php first, then every
// includes/ui/[a-z]*.php (one component per file). Pages using these must
// also load css/lab.css (built by bin/build-css) and wrap the content in
// <main class="lab">. See docs/superpowers/specs/2026-10-14-maipo-lab-redesign-design.md

require_once __DIR__ . '/_helpers.php';
foreach (glob(__DIR__ . '/[a-z]*.php') as $ui_component_file) {
    if (basename($ui_component_file) !== 'ui.php') {
        require_once $ui_component_file;
    }
}
unset($ui_component_file);
```

`includes/ui/_helpers.php`:

```php
<?php
// includes/ui/_helpers.php
// Shared helpers for the lab UI components in includes/ui/. Components are
// pure renderers: they echo HTML built from the arrays they get, escape every
// value, and never touch the DB. Tailwind classes must stay LITERAL strings
// (bin/build-css scans these files for them) - never build a class name by
// concatenation.

function ui_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ui_price($amount): string
{
    return '$' . number_format((float)$amount, 0);
}

/** "img/x-480.webp 480w, img/x-960.webp 960w" for a base path without width/extension. */
function ui_srcset(string $base, array $widths, string $ext = 'webp'): string
{
    return implode(', ', array_map(
        static fn($w) => $base . '-' . (int)$w . '.' . $ext . ' ' . (int)$w . 'w',
        $widths
    ));
}

/** Inline Lucide icon (https://lucide.dev, ISC licence). Unknown names render ''. */
function ui_icon(string $name, string $class = 'tw:size-5'): string
{
    $paths = [
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'rotate-ccw' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'wine' => '<path d="M8 22h8"/><path d="M7 10h10"/><path d="M12 15v7"/><path d="M12 15a5 5 0 0 0 5-5c0-2-.5-4-2-8H9c-1.5 4-2 6-2 8a5 5 0 0 0 5 5Z"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="' . ui_h($class) . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/** Per-option block wrapper; js/maipo-options.js toggles [data-option] blocks. */
function ui_option_block_open(string $slug, string $selected): string
{
    return '<div data-option="' . ui_h($slug) . '"' . ($slug === $selected ? '' : ' hidden') . '>';
}

function ui_option_block_close(): string
{
    return '</div>';
}
```

- [ ] **Step 4: Write the five component files**

`includes/ui/award.php`:

```php
<?php
// includes/ui/award.php
// Official Tripadvisor Travelers' Choice "Best of the Best" 2026 badge.
//   'hero'    white wordmark + caption, for use on photos
//   'compact' circle badge + caption ($note), for the booking panel

function ui_award(string $variant = 'hero', string $note = ''): void
{
    $alt = "Tripadvisor Travelers' Choice Best of the Best 2026";
    if ($variant === 'compact') {
        ?>
        <div class="tw:flex tw:items-center tw:gap-3">
         <picture class="tw:shrink-0"><source srcset="img/badges/tripadvisor-circle-2026.webp" type="image/webp"><img src="img/badges/tripadvisor-circle-2026.png" width="100" height="127" alt="<?= ui_h($alt) ?>" loading="lazy" class="tw:h-12 tw:w-auto"></picture>
         <p class="tw:text-xs tw:leading-snug tw:text-muted">Travelers' Choice Best of the Best 2026<?php if ($note !== ''): ?><br><?= ui_h($note) ?><?php endif; ?></p>
        </div>
        <?php
        return;
    }
    ?>
    <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-3.5">
     <picture class="tw:shrink-0"><source srcset="img/badges/tripadvisor-2026-white.webp" type="image/webp"><img src="img/badges/tripadvisor-2026-white.png" width="260" height="50" alt="<?= ui_h($alt) ?>" class="tw:h-11 tw:w-auto tw:md:h-14"></picture>
     <p class="tw:text-[13px] tw:leading-snug tw:text-white/85"><strong class="tw:text-white">Travelers' Choice · Best of the Best 2026</strong><br>Top 1% of experiences on Tripadvisor &amp; Viator</p>
    </div>
    <?php
}
```

`includes/ui/section.php`:

```php
<?php
// includes/ui/section.php
// Full-width landing-page section: eyebrow + H2 (+ optional intro paragraph).
// Always pair ui_section_open() with ui_section_close().

function ui_section_open(string $id, string $eyebrow, string $title, string $intro = ''): void
{
    echo '<section' . ($id !== '' ? ' id="' . ui_h($id) . '"' : '') . ' class="tw:scroll-mt-20 tw:border-t tw:border-line tw:py-11 tw:md:py-16">'
        . '<div class="tw:mx-auto tw:max-w-[1100px] tw:px-4 tw:md:px-7">'
        . '<p class="lab-eyebrow">' . ui_h($eyebrow) . '</p>'
        . '<h2 class="tw:mt-1.5 tw:mb-6 tw:text-2xl tw:leading-tight tw:font-extrabold tw:uppercase tw:text-white tw:md:text-[32px]">' . ui_h($title) . '</h2>';
    if ($intro !== '') {
        echo '<p class="tw:-mt-2 tw:mb-6 tw:max-w-[760px] tw:text-muted">' . ui_h($intro) . '</p>';
    }
}

function ui_section_close(): void
{
    echo '</div></section>';
}
```

`includes/ui/accordion.php`:

```php
<?php
// includes/ui/accordion.php
// <details>/<summary> accordion - works without JS.
// $items: list of ['q' => summary, 'a' => plain-text answer]  (escaped, wrapped in <p>)
//              or ['q' => summary, 'html' => trusted author-written HTML]  (output as-is)

function ui_accordion(array $items, string $id): void
{
    ?>
    <div id="<?= ui_h($id) ?>" class="tw:border-t tw:border-line">
     <?php foreach ($items as $item): ?>
      <details class="lab-acc tw:border-b tw:border-line">
       <summary class="tw:flex tw:cursor-pointer tw:items-center tw:justify-between tw:gap-4 tw:py-4 tw:font-semibold tw:text-white">
        <span><?= ui_h($item['q']) ?></span>
        <?= ui_icon('chevron-down', 'lab-acc-icon tw:size-5 tw:shrink-0 tw:text-teal-soft tw:transition-transform') ?>
       </summary>
       <div class="lab-prose tw:pb-5"><?= isset($item['html']) ? $item['html'] : '<p>' . ui_h($item['a'] ?? '') . '</p>' ?></div>
      </details>
     <?php endforeach; ?>
    </div>
    <?php
}
```

`includes/ui/checklist.php`:

```php
<?php
// includes/ui/checklist.php
// "Included / Not included" cards. Wrap in ui_option_block_open/close for
// per-option lists. The "Not included" card is omitted when $no is empty.

function ui_checklist(array $yes, array $no): void
{
    ?>
    <div class="tw:grid tw:gap-4 tw:md:grid-cols-2">
     <div class="lab-card tw:p-5">
      <h3 class="lab-eyebrow">Included</h3>
      <ul class="tw:mt-2.5">
       <?php foreach ($yes as $item): ?>
        <li class="tw:flex tw:gap-2.5 tw:py-1.5 tw:text-sm"><?= ui_icon('check', 'tw:mt-0.5 tw:size-4 tw:shrink-0 tw:text-teal-soft') ?><span><?= ui_h($item) ?></span></li>
       <?php endforeach; ?>
      </ul>
     </div>
     <?php if ($no): ?>
      <div class="lab-card tw:p-5">
       <h3 class="lab-eyebrow">Not included</h3>
       <ul class="tw:mt-2.5">
        <?php foreach ($no as $item): ?>
         <li class="tw:flex tw:gap-2.5 tw:py-1.5 tw:text-sm"><?= ui_icon('x', 'tw:mt-0.5 tw:size-4 tw:shrink-0 tw:text-coral') ?><span><?= ui_h($item) ?></span></li>
        <?php endforeach; ?>
       </ul>
      </div>
     <?php endif; ?>
    </div>
    <?php
}
```

`includes/ui/benefits.php`:

```php
<?php
// includes/ui/benefits.php
// Benefit cards (icon, title, text): 2 columns on mobile, 4 on desktop.
// $items: list of ['icon' => ui_icon name, 'title' => ..., 'text' => ...]

function ui_benefits(array $items): void
{
    ?>
    <ul class="tw:grid tw:grid-cols-2 tw:gap-3 tw:md:gap-4 tw:lg:grid-cols-4">
     <?php foreach ($items as $item): ?>
      <li class="lab-card tw:p-4 tw:md:p-5">
       <span class="tw:flex tw:size-10 tw:items-center tw:justify-center tw:rounded-[10px] tw:bg-teal/20 tw:text-teal-soft"><?= ui_icon($item['icon']) ?></span>
       <h3 class="tw:mt-2.5 tw:mb-1.5 tw:text-base tw:leading-snug tw:font-bold tw:text-white"><?= ui_h($item['title']) ?></h3>
       <p class="tw:text-sm tw:text-muted"><?= ui_h($item['text']) ?></p>
      </li>
     <?php endforeach; ?>
    </ul>
    <?php
}
```

- [ ] **Step 5: Run the tests**

Run: `php tests/tools/phpunit.phar --filter UiPrimitivesTest`
Expected: PASS (9 tests).

- [ ] **Step 6: Rebuild the CSS and run the full suite**

Run: `bin/build-css && php tests/tools/phpunit.phar`
Expected: all tests pass (only the pre-existing deprecation). `css/lab.css` now contains, for example, `.tw\:bg-teal\/20`.

- [ ] **Step 7: Commit**

```bash
git add includes/ui tests/UiPrimitivesTest.php tests/bootstrap.php css/lab.css
git commit -m "feat(ui): lab helpers + award, section, accordion, checklist, benefits components

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Hero and gallery components

**Files:**
- Create: `includes/ui/hero.php`
- Create: `includes/ui/gallery.php`
- Modify (generated): `css/lab.css`
- Test: `tests/UiHeroGalleryTest.php`

**Interfaces:**
- Consumes:
  - From Task 3: `ui_h`, `ui_price`, `ui_srcset`, `ui_icon`, `ui_award('hero')`.
  - From Task 2: data shaped like `maipo_lab_hero()` and `maipo_lab_photos()`.
- Produces:
  - `ui_hero(array $d): void`. Keys as in `maipo_lab_hero()`.
  - `ui_gallery(array $photos): void`. Renders the mosaic of `<a data-lab-gallery-item href="<base>-1600.webp" data-caption="…">` tiles and one `<dialog data-lab-lightbox>` with `[data-lab-lightbox-img]`, `[data-lab-lightbox-caption]`, `[data-lab-lightbox-prev]`, `[data-lab-lightbox-next]` and `[data-lab-lightbox-close]`. `js/lab.js` (Task 7) relies on these hooks.

- [ ] **Step 1: Write the failing test**

Create `tests/UiHeroGalleryTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter UiHeroGalleryTest`
Expected: FAIL, "Call to undefined function ui_hero()".

- [ ] **Step 3: Write `includes/ui/hero.php`**

The header is `position:fixed` and overlays the hero, so the hero gets top padding. The `<picture>` sources mirror the `$lcp_preload_image` / `$lcp_preload_image_mobile` split in `includes/head.php`.

```php
<?php
// includes/ui/hero.php
// Full-bleed photo hero: award badge, H1 (with one highlighted phrase), lead,
// fact chips, CTA and "From $X per adult". The <picture> media split mirrors
// head.php's $lcp_preload_image_mobile / $lcp_preload_image preloads
// ((max-width: 767px)) - keep them in sync or the LCP image downloads twice.
// $d: image{mobile,desktop,fallback,w,h,alt}, title, highlight, lead,
//     facts[{icon,text}], cta_href, cta_text, price_from (float|null)

function ui_hero(array $d): void
{
    $img = $d['image'];
    $title = ui_h($d['title']);
    $highlight = ui_h($d['highlight'] ?? '');
    if ($highlight !== '' && ($pos = strpos($title, $highlight)) !== false) {
        $title = substr_replace($title, '<em class="tw:not-italic tw:text-coral">' . $highlight . '</em>', $pos, strlen($highlight));
    }
    ?>
    <section class="tw:relative tw:flex tw:min-h-[560px] tw:items-end tw:overflow-hidden tw:pt-28 tw:md:min-h-[640px]">
     <picture>
      <source media="(max-width: 767px)" srcset="<?= ui_h($img['mobile']) ?>" type="image/webp">
      <source media="(min-width: 768px)" srcset="<?= ui_h($img['desktop']) ?>" type="image/webp">
      <img src="<?= ui_h($img['fallback']) ?>" width="<?= (int)$img['w'] ?>" height="<?= (int)$img['h'] ?>" alt="<?= ui_h($img['alt']) ?>" fetchpriority="high" class="tw:absolute tw:inset-0 tw:h-full tw:w-full tw:object-cover tw:object-[center_40%]">
     </picture>
     <div class="tw:absolute tw:inset-0 tw:bg-[linear-gradient(to_top,#1c1f21_2%,rgba(28,31,33,.55)_45%,rgba(28,31,33,.45)_100%)]" aria-hidden="true"></div>
     <div class="tw:relative tw:mx-auto tw:w-full tw:max-w-[1100px] tw:px-4 tw:pb-10 tw:md:px-7 tw:md:pb-12">
      <?php ui_award('hero'); ?>
      <h1 class="tw:mt-4 tw:mb-2.5 tw:max-w-[780px] tw:text-[30px] tw:leading-[1.05] tw:font-extrabold tw:tracking-[.3px] tw:text-white tw:uppercase tw:md:text-[46px]"><?= $title ?></h1>
      <p class="tw:mb-4 tw:max-w-[620px] tw:text-[15px] tw:text-white/85 tw:md:text-base"><?= ui_h($d['lead']) ?></p>
      <ul class="tw:mb-5 tw:flex tw:flex-wrap tw:gap-2">
       <?php foreach ($d['facts'] as $fact): ?>
        <li data-hero-fact class="tw:inline-flex tw:items-center tw:gap-1.5 tw:rounded-full tw:border tw:border-line tw:bg-white/10 tw:px-3 tw:py-1.5 tw:text-[13px] tw:font-semibold tw:text-white"><?= ui_icon($fact['icon'], 'tw:size-4 tw:text-teal-soft') ?><?= ui_h($fact['text']) ?></li>
       <?php endforeach; ?>
      </ul>
      <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-4">
       <a class="lab-btn" href="<?= ui_h($d['cta_href']) ?>"><?= ui_h($d['cta_text']) ?></a>
       <?php if ($d['price_from'] !== null): ?>
        <p class="tw:text-sm tw:text-muted">From <strong class="tw:text-[26px] tw:font-extrabold tw:text-white"><?= ui_h(ui_price($d['price_from'])) ?></strong> per adult</p>
       <?php endif; ?>
      </div>
     </div>
    </section>
    <?php
}
```

- [ ] **Step 4: Write `includes/ui/gallery.php`**

```php
<?php
// includes/ui/gallery.php
// Photo mosaic + native <dialog> lightbox (behaviour in js/lab.js). Each tile
// is a real link to the 1600px image, so it still works without JS or
// <dialog> support. Layout (.lab-mosaic in css/src/lab-base.css) expects 7
// photos: first = tall tile, last = wide tile on desktop.
// $photos: list of ['base' => path without -width.ext, 'w', 'h' (of the 960
//          image), 'alt', 'caption']; files from bin/make-images.

function ui_gallery(array $photos): void
{
    if (!$photos) {
        return;
    }
    ?>
    <div class="lab-mosaic" data-lab-gallery>
     <?php foreach (array_values($photos) as $i => $p): ?>
      <a class="tw:group tw:block tw:overflow-hidden tw:rounded-[10px] tw:bg-card" href="<?= ui_h($p['base'] . '-1600.webp') ?>" data-lab-gallery-item data-caption="<?= ui_h($p['caption']) ?>">
       <picture class="tw:block tw:h-full">
        <source type="image/webp" srcset="<?= ui_h(ui_srcset($p['base'], [480, 960])) ?>" sizes="<?= $i === 0 ? '(min-width: 768px) 50vw, 100vw' : '(min-width: 768px) 33vw, 50vw' ?>">
        <img src="<?= ui_h($p['base'] . '-960.jpg') ?>" width="<?= (int)$p['w'] ?>" height="<?= (int)$p['h'] ?>" alt="<?= ui_h($p['alt']) ?>" loading="lazy" decoding="async" class="tw:h-full tw:w-full tw:object-cover tw:transition-transform tw:duration-300 tw:group-hover:scale-105">
       </picture>
      </a>
     <?php endforeach; ?>
    </div>
    <dialog class="lab-lightbox" data-lab-lightbox aria-label="Photo gallery">
     <figure class="tw:flex tw:flex-col tw:items-center tw:gap-3 tw:px-14 tw:py-4">
      <img data-lab-lightbox-img alt="" class="tw:max-h-[80dvh] tw:w-auto tw:rounded-lg tw:object-contain">
      <figcaption data-lab-lightbox-caption class="tw:text-center tw:text-sm tw:text-white/85"></figcaption>
     </figure>
     <button type="button" data-lab-lightbox-prev aria-label="Previous photo" class="tw:absolute tw:top-1/2 tw:left-1 tw:flex tw:size-11 tw:-translate-y-1/2 tw:cursor-pointer tw:items-center tw:justify-center tw:rounded-full tw:border-0 tw:bg-black/50 tw:text-white"><?= ui_icon('chevron-left', 'tw:size-6') ?></button>
     <button type="button" data-lab-lightbox-next aria-label="Next photo" class="tw:absolute tw:top-1/2 tw:right-1 tw:flex tw:size-11 tw:-translate-y-1/2 tw:cursor-pointer tw:items-center tw:justify-center tw:rounded-full tw:border-0 tw:bg-black/50 tw:text-white"><?= ui_icon('chevron-right', 'tw:size-6') ?></button>
     <button type="button" data-lab-lightbox-close aria-label="Close" class="tw:absolute tw:top-1 tw:right-1 tw:flex tw:size-11 tw:cursor-pointer tw:items-center tw:justify-center tw:rounded-full tw:border-0 tw:bg-black/50 tw:text-white"><?= ui_icon('x', 'tw:size-6') ?></button>
    </dialog>
    <?php
}
```

- [ ] **Step 5: Run the tests, rebuild the CSS, run the full suite**

Run: `php tests/tools/phpunit.phar --filter UiHeroGalleryTest && bin/build-css && php tests/tools/phpunit.phar`
Expected: UiHeroGalleryTest passes (5 tests), and the full suite is green.

- [ ] **Step 6: Commit**

```bash
git add includes/ui/hero.php includes/ui/gallery.php tests/UiHeroGalleryTest.php css/lab.css
git commit -m "feat(ui): hero (LCP picture) and gallery mosaic with dialog lightbox

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Option cards, itinerary, and sticky bar components

**Files:**
- Create: `includes/ui/option_cards.php`
- Create: `includes/ui/itinerary.php`
- Create: `includes/ui/sticky_bar.php`
- Modify (generated): `css/lab.css`
- Test: `tests/UiOptionsTest.php`

**Interfaces:**
- Consumes:
  - From Task 3: `ui_h`, `ui_price`, `ui_icon`, `ui_option_block_open/close`.
  - `maipo_options()` shape (`label`, `tagline`, `stops[{place,title,duration,icon,text}]`).
  - The price map shape `slug => ['adult' => float, …]`, containing only priced options.
- Produces:
  - `ui_option_cards(array $options, array $prices, string $selected, string $name, string $legend, string $variant = 'cards'): void`
    - Variants: `'cards'` (big route cards) or `'segmented'` (compact 3-up switch).
    - Renders `<fieldset data-tour-options>` with `<label for="<name>-<slug>">` wrapping `<input class="tw:sr-only" type="radio" name="<name>" id="<name>-<slug>" value="<slug>" data-booking-lock[ checked][ disabled]>`.
  - `ui_itinerary(array $options, array $prices, string $selected): void`. Renders a segmented `tour_option_itin` group, then one `[data-option]` block per option holding an `<ol class="lab-timeline">` of `<li class="lab-stop">` items with an `<h3>` each.
  - `ui_sticky_bar(array $labels, array $prices, string $selected, string $target = '#book'): void`
    - Root: `<div class="lab-sticky" data-sticky-cta data-target data-labels data-prices>`
    - Children: `[data-sticky-label]`, `[data-sticky-price]`, and the CTA `a[href=$target]`.
    - `js/lab.js` (Task 7) uses these hooks, and the `js/tours.js` guard uses `[data-sticky-cta]`.

- [ ] **Step 1: Write the failing test**

Create `tests/UiOptionsTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UiOptionsTest extends TestCase
{
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return (string)ob_get_clean();
    }

    private function prices(): array
    {
        return ['classic' => ['adult' => 110.0], 'premium' => ['adult' => 139.0]]; // classic-lunch unpriced
    }

    private function labels(): array
    {
        return ['classic' => 'Classic Route', 'classic-lunch' => 'Classic Route + Lunch', 'premium' => 'Premium Route'];
    }

    public function testCardsKeepTheSelectorContract(): void
    {
        $html = $this->capture(fn() => ui_option_cards(maipo_options(), $this->prices(), 'premium', 'tour_option', 'Choose your route'));
        $this->assertMatchesRegularExpression('/<fieldset [^>]*data-tour-options>/', $html);
        $this->assertStringContainsString('<legend class="tw:sr-only">Choose your route</legend>', $html);
        $this->assertStringContainsString('<label for="tour_option-premium"', $html);
        $this->assertStringContainsString('name="tour_option" id="tour_option-premium" value="premium" data-booking-lock checked>', $html);
        $this->assertStringContainsString('name="tour_option" id="tour_option-classic" value="classic" data-booking-lock>', $html);
        $this->assertStringContainsString('value="classic-lunch" data-booking-lock disabled>', $html);
        $this->assertStringContainsString('Contact us', $html);
        $this->assertStringContainsString('$139', $html);
        $this->assertStringContainsString('/ adult', $html);
        $this->assertSame(3, substr_count($html, 'data-booking-lock'));
        $this->assertSame(3, substr_count($html, '<label '));
    }

    public function testSegmentedVariant(): void
    {
        $html = $this->capture(fn() => ui_option_cards(maipo_options(), $this->prices(), 'classic', 'tour_option_itin', 'Show itinerary for', 'segmented'));
        $this->assertStringContainsString('<legend class="lab-label">Show itinerary for</legend>', $html);
        $this->assertStringContainsString('tw:grid-cols-3', $html);
        $this->assertSame(3, substr_count($html, 'name="tour_option_itin"'));
        $this->assertStringContainsString('id="tour_option_itin-classic" value="classic" data-booking-lock checked>', $html);
        $this->assertStringNotContainsString('/ adult', $html);
        $this->assertStringContainsString('Contact us', $html);
    }

    public function testOptionCardsEscape(): void
    {
        $opts = ['x' => array_merge(maipo_options()['classic'], ['label' => '<b>x</b>', 'tagline' => 'a&b'])];
        $html = $this->capture(fn() => ui_option_cards($opts, [], 'x', 'n', 'L'));
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('a&amp;b', $html);
    }

    public function testItineraryBlocksAndTimeline(): void
    {
        $html = $this->capture(fn() => ui_itinerary(maipo_options(), $this->prices(), 'premium'));
        $this->assertSame(3, substr_count($html, 'name="tour_option_itin"'));
        $this->assertStringContainsString('<div data-option="premium">', $html);
        $this->assertStringContainsString('<div data-option="classic" hidden>', $html);
        $this->assertStringContainsString('<div data-option="classic-lunch" hidden>', $html);
        $this->assertSame(3, substr_count($html, '<ol class="lab-timeline'));
        $this->assertSame(18, substr_count($html, '<li class="lab-stop'));
        $this->assertStringContainsString('1 hour 30 minutes · Isla de Maipo', $html);
        $this->assertMatchesRegularExpression('#<h3 [^>]*>Viña Undurraga</h3>#', $html);
        $this->assertMatchesRegularExpression('#<h3 [^>]*>Viña Ballek</h3>#', $html);
        $this->assertMatchesRegularExpression('#<p [^>]*>Santiago</p>\s*<h3 [^>]*>Hotel pickup</h3>#', $html);
    }

    public function testStickyBarShowsSelectedOption(): void
    {
        $html = $this->capture(fn() => ui_sticky_bar($this->labels(), $this->prices(), 'premium'));
        $this->assertStringContainsString('class="lab-sticky" data-sticky-cta data-target="#book"', $html);
        $this->assertStringContainsString('<span data-sticky-label>Premium Route</span>', $html);
        $this->assertMatchesRegularExpression('#<strong [^>]*data-sticky-price>\$139</strong>#', $html);
        $this->assertStringContainsString('href="#book"', $html);
        preg_match('/data-labels="([^"]*)"/', $html, $l);
        preg_match('/data-prices="([^"]*)"/', $html, $p);
        $this->assertSame($this->labels(), json_decode(html_entity_decode($l[1]), true));
        $this->assertEquals(['classic' => 110, 'premium' => 139], json_decode(html_entity_decode($p[1]), true));
    }

    public function testStickyBarFallbacks(): void
    {
        $unpriced = $this->capture(fn() => ui_sticky_bar($this->labels(), $this->prices(), 'classic-lunch'));
        $this->assertStringContainsString('<span data-sticky-label>From</span>', $unpriced);
        $this->assertMatchesRegularExpression('#data-sticky-price>\$110</strong>#', $unpriced);

        $none = $this->capture(fn() => ui_sticky_bar($this->labels(), [], 'classic'));
        $this->assertStringNotContainsString('data-sticky-price', $none);
        $this->assertStringContainsString('Check availability', $none);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter UiOptionsTest`
Expected: FAIL, "Call to undefined function ui_option_cards()".

- [ ] **Step 3: Write `includes/ui/option_cards.php`**

```php
<?php
// includes/ui/option_cards.php
// Option selector for tours sold in several options (Maipo first). Same DOM
// contract as includes/tours/option_ui.php so js/maipo-options.js (syncs every
// [data-tour-options] radio by value) and js/tours.js (coupon lock disables
// every [data-booking-lock]) work unchanged. Unpriced options are disabled
// and show "Contact us".
//   'cards'     big route cards (price, label, tagline) - legend visually hidden
//   'segmented' compact 3-up switch (label + price) with a visible legend

function ui_option_cards(array $options, array $prices, string $selected, string $name, string $legend, string $variant = 'cards'): void
{
    $segmented = $variant === 'segmented';
    ?>
    <fieldset class="<?= $segmented ? 'tw:min-w-0' : 'tw:grid tw:min-w-0 tw:gap-4 tw:md:grid-cols-3' ?>" data-tour-options>
     <legend class="<?= $segmented ? 'lab-label' : 'tw:sr-only' ?>"><?= ui_h($legend) ?></legend>
     <?php if ($segmented): ?><div class="tw:grid tw:grid-cols-3 tw:gap-1 tw:rounded-[10px] tw:border tw:border-line tw:bg-card tw:p-1"><?php endif; ?>
     <?php foreach ($options as $slug => $opt): ?>
      <?php $slug = (string)$slug; $price = $prices[$slug]['adult'] ?? null; $id = $name . '-' . $slug; ?>
      <?php if ($segmented): ?>
       <label for="<?= ui_h($id) ?>" class="tw:flex tw:cursor-pointer tw:flex-col tw:items-center tw:justify-center tw:rounded-[7px] tw:px-2 tw:py-2 tw:text-center tw:text-xs tw:leading-tight tw:font-bold tw:text-muted tw:transition-colors tw:hover:text-white tw:has-checked:bg-teal tw:has-checked:text-white tw:has-disabled:cursor-not-allowed tw:has-disabled:opacity-50 tw:has-focus-visible:outline-2 tw:has-focus-visible:outline-teal-soft tw:md:text-[13px]">
      <?php else: ?>
       <label for="<?= ui_h($id) ?>" class="lab-card tw:relative tw:block tw:cursor-pointer tw:p-5 tw:transition-shadow tw:hover:border-teal/60 tw:has-checked:border-teal tw:has-checked:shadow-[0_0_0_2px_rgba(0,132,137,.45)] tw:has-disabled:cursor-not-allowed tw:has-disabled:opacity-55 tw:has-focus-visible:outline-2 tw:has-focus-visible:outline-teal-soft">
      <?php endif; ?>
        <input class="tw:sr-only" type="radio" name="<?= ui_h($name) ?>" id="<?= ui_h($id) ?>" value="<?= ui_h($slug) ?>" data-booking-lock<?= $slug === $selected ? ' checked' : '' ?><?= $price === null ? ' disabled' : '' ?>>
      <?php if ($segmented): ?>
        <span><?= ui_h($opt['label']) ?></span>
        <span class="tw:mt-0.5 tw:font-semibold tw:opacity-80"><?= $price !== null ? ui_h(ui_price($price)) : 'Contact us' ?></span>
      <?php else: ?>
        <span class="tw:block tw:text-[28px] tw:leading-none tw:font-extrabold tw:text-coral"><?php if ($price !== null): ?><?= ui_h(ui_price($price)) ?> <small class="tw:text-xs tw:font-semibold tw:text-muted">/ adult</small><?php else: ?>Contact us<?php endif; ?></span>
        <span class="tw:mt-3 tw:block tw:text-base tw:font-bold tw:text-white"><?= ui_h($opt['label']) ?></span>
        <span class="tw:mt-1 tw:block tw:text-sm tw:text-muted"><?= ui_h($opt['tagline']) ?></span>
      <?php endif; ?>
       </label>
     <?php endforeach; ?>
     <?php if ($segmented): ?></div><?php endif; ?>
    </fieldset>
    <?php
}
```

- [ ] **Step 4: Write `includes/ui/itinerary.php`**

```php
<?php
// includes/ui/itinerary.php
// "Your day": segmented tour_option_itin switch + one timeline per option in
// [data-option] blocks (only the selected one visible; all server-rendered
// for SEO). Stop line: "<duration> · <place>", or just the place when the
// stop has no duration (pickup/return).

function ui_itinerary(array $options, array $prices, string $selected): void
{
    ui_option_cards($options, $prices, $selected, 'tour_option_itin', 'Show itinerary for', 'segmented');
    foreach ($options as $slug => $opt) {
        echo ui_option_block_open((string)$slug, $selected);
        ?>
        <ol class="lab-timeline tw:mt-7">
         <?php foreach ($opt['stops'] as $stop): ?>
          <li class="lab-stop">
           <p class="tw:text-xs tw:font-bold tw:tracking-wider tw:text-coral tw:uppercase"><?= ui_h($stop['duration'] !== '' ? $stop['duration'] . ' · ' . $stop['place'] : $stop['place']) ?></p>
           <h3 class="tw:mt-0.5 tw:mb-1 tw:text-lg tw:font-bold tw:text-white"><?= ui_h($stop['title']) ?></h3>
           <p class="tw:max-w-[640px] tw:text-sm tw:text-muted"><?= ui_h($stop['text']) ?></p>
          </li>
         <?php endforeach; ?>
        </ol>
        <?php
        echo ui_option_block_close();
    }
}
```

- [ ] **Step 5: Write `includes/ui/sticky_bar.php`**

```php
<?php
// includes/ui/sticky_bar.php
// Bottom CTA bar "<option> · $X · Check availability". js/lab.js hides it
// while $target (#book) is on screen and updates label/price on
// 'maipo:optionchange' from data-labels / data-prices. js/tours.js skips its
// old floating header button when [data-sticky-cta] exists.
// Fallbacks: selected option unpriced -> "From $<lowest>"; nothing priced ->
// CTA only.

function ui_sticky_bar(array $labels, array $prices, string $selected, string $target = '#book'): void
{
    $adult = array_map(static fn(array $p) => (float)$p['adult'], $prices);
    if (isset($adult[$selected])) {
        $label = $labels[$selected] ?? '';
        $price = ui_price($adult[$selected]);
    } elseif ($adult) {
        $label = 'From';
        $price = ui_price(min($adult));
    } else {
        $label = '';
        $price = '';
    }
    ?>
    <div class="lab-sticky" data-sticky-cta data-target="<?= ui_h($target) ?>" data-labels="<?= ui_h(json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>" data-prices="<?= ui_h(json_encode($adult)) ?>">
     <div class="tw:mx-auto tw:flex tw:max-w-[1100px] tw:items-center tw:justify-between tw:gap-3 tw:px-4 tw:py-2.5 tw:md:px-7">
      <?php if ($price !== ''): ?>
       <p class="tw:text-sm tw:text-muted"><span data-sticky-label><?= ui_h($label) ?></span> · <strong class="tw:text-xl tw:font-extrabold tw:text-white" data-sticky-price><?= ui_h($price) ?></strong></p>
      <?php else: ?>
       <span></span>
      <?php endif; ?>
      <a class="lab-btn tw:px-5 tw:py-3" href="<?= ui_h($target) ?>">Check availability</a>
     </div>
    </div>
    <?php
}
```

- [ ] **Step 6: Run the tests, rebuild the CSS, run the full suite**

Run: `php tests/tools/phpunit.phar --filter UiOptionsTest && bin/build-css && php tests/tools/phpunit.phar`
Expected: UiOptionsTest passes (6 tests), and the full suite is green.

- [ ] **Step 7: Commit**

```bash
git add includes/ui/option_cards.php includes/ui/itinerary.php includes/ui/sticky_bar.php tests/UiOptionsTest.php css/lab.css
git commit -m "feat(ui): route cards, itinerary timeline and sticky CTA bar

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Booking panel component

**Files:**
- Create: `includes/ui/booking_panel.php`
- Modify (generated): `css/lab.css`
- Test: `tests/UiBookingPanelTest.php`

**Interfaces:**
- Consumes:
  - From Task 5: `ui_option_cards(..., 'segmented')`.
  - From Task 3: `ui_award('compact', $note)` and `ui_h`.
- Produces: `ui_booking_panel(array $options, array $prices, string $selected, string $action = 'book_tour.php'): void`. It renders the full `form#booking` with every id in the Global Constraints DOM contract, plus a `tour_option_book` radio group.

- [ ] **Step 1: Write the failing test**

Create `tests/UiBookingPanelTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UiBookingPanelTest extends TestCase
{
    private function render(string $selected, string $action = 'book_tour.php'): string
    {
        $prices = ['classic' => ['adult' => 110.0], 'classic-lunch' => ['adult' => 129.0], 'premium' => ['adult' => 139.0]];
        ob_start();
        ui_booking_panel(maipo_options(), $prices, $selected, $action);
        return (string)ob_get_clean();
    }

    public function testKeepsEveryIdTheBookingScriptsUse(): void
    {
        $html = $this->render('premium');
        foreach (['booking', 'activity_name', 'option_2', 'name_booking', 'last_name_booking', 'email_booking', 'phone_booking', 'date_booking', 'adults', 'children', 'infants', 'coupon_code', 'apply_coupon_valpo', 'total_price', 'new_total_container', 'new_total_price'] as $id) {
            $this->assertSame(1, substr_count($html, 'id="' . $id . '"'), $id);
        }
        $this->assertStringContainsString('<form action="book_tour.php" method="POST" id="booking"', $html);
        $this->assertStringContainsString('<input id="activity_name" name="activity_name" type="hidden" value="Maipo">', $html);
        $this->assertStringContainsString('<input id="option_2" name="option_2" type="checkbox" value="Yes" hidden>', $html);
        $this->assertSame(3, substr_count($html, '<div class="numbers-row">'));
        $this->assertSame(3, substr_count($html, 'class="qty2"'));
        $this->assertStringContainsString('class="lab-input date-pick required" id="date_booking" name="date_booking"', $html);
        $this->assertStringContainsString('id="new_total_container" style="display:none;"', $html);
        $this->assertMatchesRegularExpression('#<button class="lab-btn[^"]*" type="submit">Book now</button>#', $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
    }

    public function testHasRouteSelectorAndTrustBadge(): void
    {
        $html = $this->render('premium');
        $this->assertSame(3, substr_count($html, 'name="tour_option_book"'));
        $this->assertStringContainsString('id="tour_option_book-premium" value="premium" data-booking-lock checked>', $html);
        $this->assertStringContainsString('tripadvisor-circle-2026', $html);
        $this->assertStringContainsString('Free cancellation up to 24 h before · Secure payment', $html);
    }

    public function testActivityNameFollowsSelectedOptionAndActionIsEscaped(): void
    {
        $html = $this->render('classic', 'book"x.php');
        $this->assertStringContainsString('name="activity_name" type="hidden" value="MAIPO_CLASSIC"', $html);
        $this->assertStringContainsString('<form action="book&quot;x.php"', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/tools/phpunit.phar --filter UiBookingPanelTest`
Expected: FAIL, "Call to undefined function ui_booking_panel()".

- [ ] **Step 3: Write `includes/ui/booking_panel.php`**

```php
<?php
// includes/ui/booking_panel.php
// The booking form, restyled. Every id/name/class below is load-bearing:
//   js/tours.js       #booking (submit + action), #activity_name, #name_booking,
//                     #last_name_booking, #email_booking, #phone_booking,
//                     #date_booking (.date-pick -> daterangepicker), #adults,
//                     #children, #infants, #option_2 (pickup checkbox, kept
//                     hidden + unchecked), #coupon_code, #apply_coupon_valpo,
//                     #total_price, #new_total_container (inline display:none,
//                     shown by tours.js), #new_total_price, button[type=submit]
//   js/functions.js   .numbers-row (appends the +/- buttons), input.qty2
//   js/maipo-options.js  #activity_name + [data-tour-options] radios
// Don't rename any of them without updating those scripts.

function ui_booking_panel(array $options, array $prices, string $selected, string $action = 'book_tour.php'): void
{
    $exp = $options[$selected]['exp_name'] ?? '';
    ?>
    <div class="tw:rounded-2xl tw:border tw:border-teal/50 tw:bg-[linear-gradient(135deg,rgba(0,132,137,.25),rgba(224,79,103,.12))] tw:p-5 tw:md:p-7">
     <form action="<?= ui_h($action) ?>" method="POST" id="booking" class="tw:grid tw:gap-6 tw:lg:grid-cols-[1.2fr_1fr] tw:lg:gap-7">
      <input id="activity_name" name="activity_name" type="hidden" value="<?= ui_h($exp) ?>">
      <input id="option_2" name="option_2" type="checkbox" value="Yes" hidden>
      <div class="tw:space-y-3">
       <?php ui_option_cards($options, $prices, $selected, 'tour_option_book', 'Route', 'segmented'); ?>
       <div class="tw:grid tw:gap-3 tw:sm:grid-cols-2">
        <div><label class="lab-label" for="name_booking">Name</label><input class="lab-input required" id="name_booking" name="name_booking" type="text" autocomplete="given-name"></div>
        <div><label class="lab-label" for="last_name_booking">Last name</label><input class="lab-input required" id="last_name_booking" name="last_name_booking" type="text" autocomplete="family-name"></div>
       </div>
       <div class="tw:grid tw:gap-3 tw:sm:grid-cols-2">
        <div><label class="lab-label" for="email_booking">Email</label><input class="lab-input required" id="email_booking" name="email_booking" type="email" autocomplete="email"></div>
        <div><label class="lab-label" for="phone_booking">Phone (WhatsApp)</label><input class="lab-input required" id="phone_booking" name="phone_booking" type="tel" autocomplete="tel"></div>
       </div>
       <div><label class="lab-label" for="date_booking">Date</label><input class="lab-input date-pick required" id="date_booking" name="date_booking" type="text" autocomplete="off" placeholder="Select a date"></div>
      </div>
      <div class="tw:space-y-3">
       <div class="tw:grid tw:grid-cols-3 tw:gap-2 tw:sm:gap-3">
        <div><label class="lab-label" for="adults">Adults</label><div class="numbers-row"><input class="qty2" id="adults" name="adults" type="text" inputmode="numeric" value="1"></div></div>
        <div><label class="lab-label" for="children">Children</label><div class="numbers-row"><input class="qty2" id="children" name="children" type="text" inputmode="numeric" value="0"></div><span class="tw:mt-1 tw:block tw:text-[11px] tw:text-muted">Under 12</span></div>
        <div><label class="lab-label" for="infants">Infants</label><div class="numbers-row"><input class="qty2" id="infants" name="infants" type="text" inputmode="numeric" value="0"></div><span class="tw:mt-1 tw:block tw:text-[11px] tw:text-muted">Under 2</span></div>
       </div>
       <div>
        <label class="lab-label" for="coupon_code">Coupon</label>
        <div class="tw:flex tw:gap-2">
         <input class="lab-input required" id="coupon_code" name="coupon_code" type="text" placeholder="I have a coupon!">
         <button class="lab-btn tw:shrink-0 tw:bg-white/10 tw:px-4 tw:hover:bg-white/20" id="apply_coupon_valpo" type="button">Apply</button>
        </div>
       </div>
       <div class="tw:flex tw:items-baseline tw:justify-between tw:border-t tw:border-line tw:pt-3.5">
        <span class="tw:text-muted">Total</span>
        <strong class="tw:text-[28px] tw:font-extrabold tw:text-coral">$<span id="total_price">0</span> <span class="tw:text-sm tw:font-semibold tw:text-muted">USD</span></strong>
       </div>
       <p id="new_total_container" style="display:none;" class="tw:text-sm tw:text-white"><strong>New total:</strong> $<span id="new_total_price"></span> USD</p>
       <button class="lab-btn tw:w-full" type="submit">Book now</button>
       <?php ui_award('compact', 'Free cancellation up to 24 h before · Secure payment'); ?>
      </div>
     </form>
    </div>
    <?php
}
```

- [ ] **Step 4: Run the tests, rebuild the CSS, run the full suite**

Run: `php tests/tools/phpunit.phar --filter UiBookingPanelTest && bin/build-css && php tests/tools/phpunit.phar`
Expected: UiBookingPanelTest passes (3 tests), and the full suite is green.

- [ ] **Step 5: Commit**

```bash
git add includes/ui/booking_panel.php tests/UiBookingPanelTest.php css/lab.css
git commit -m "feat(ui): booking panel keeping the full booking DOM contract

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: FAQ schema helper, script includes flag, tours.js guards, and lab.js

**Files:**
- Modify: `includes/tour_faq.php` (extract `render_tour_faq_schema()`)
- Modify: `includes/tour-scripts.php` (`$tour_scripts_skip_gallery` flag; bump `tours.js?v=20261014`)
- Modify: `js/tours.js:1-15` (Slider Pro guard) and `js/tours.js:252` (header-button guard)
- Modify: `booking_manual.php:167` (bump `tours.js?v=20261014`)
- Create: `js/lab.js`
- Modify: `tests/bootstrap.php` (append one `require_once`)
- Test: `tests/TourFaqTest.php`, `tests/TourScriptsTest.php`

**Interfaces:**
- Consumes:
  - The DOM hooks from Task 4 (`data-lab-gallery-item`, `data-lab-lightbox*`).
  - The DOM hooks from Task 5 (`data-sticky-cta`, `data-target`, `data-labels`, `data-prices`, `data-sticky-label`, `data-sticky-price`).
  - The `maipo:optionchange` event (`detail.option`) fired by `js/maipo-options.js`.
- Produces:
  - `render_tour_faq_schema(array $faqs): void`
  - The `$tour_scripts_skip_gallery` flag for `includes/tour-scripts.php`
  - `js/lab.js`

- [ ] **Step 1: Write the failing tests**

Create `tests/TourFaqTest.php`:

```php
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
```

Create `tests/TourScriptsTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TourScriptsTest extends TestCase
{
    private function render(?bool $skip): string
    {
        $exp_name = 'Maipo';
        if ($skip !== null) {
            $tour_scripts_skip_gallery = $skip;
        }
        ob_start();
        include __DIR__ . '/../includes/tour-scripts.php';
        return (string)ob_get_clean();
    }

    public function testDefaultStillLoadsGalleryAndStickySidebar(): void
    {
        $html = $this->render(null);
        $this->assertStringContainsString('js/jquery.sliderPro.min.js', $html);
        $this->assertStringContainsString('css/slider-pro.min.css', $html);
        $this->assertStringContainsString('js/theia-sticky-sidebar.js', $html);
        $this->assertStringContainsString('js/tours.js?v=20261014', $html);
    }

    public function testSkipFlagDropsGalleryAndStickySidebarOnly(): void
    {
        $html = $this->render(true);
        $this->assertStringNotContainsString('sliderPro', $html);
        $this->assertStringNotContainsString('slider-pro', $html);
        $this->assertStringNotContainsString('theia', $html);
        $this->assertStringContainsString('js/jquery-3.7.1.min.js', $html);
        $this->assertStringContainsString('js/vendors-tour.min.js', $html);
        $this->assertStringContainsString("window.EXP_NAME = 'Maipo'", $html);
        $this->assertStringContainsString('js/tours.js?v=20261014', $html);
    }
}
```

Append to `tests/bootstrap.php`:

```php

require_once __DIR__ . '/../includes/tour_faq.php';
```

Run: `php tests/tools/phpunit.phar --filter 'TourFaqTest|TourScriptsTest'`
Expected: FAIL. `render_tour_faq_schema()` is undefined, and the `v=20261014` and skip-flag assertions fail.

- [ ] **Step 2: Refactor `includes/tour_faq.php`**

Replace the code after the accordion markup in `render_tour_faq()` (from `$schema = [` to the closing `<script …>` line) with a call to the new helper, and add that helper. The final shape of the file below the accordion `</div>` block:

```php
        </div>
       </div>
      </div>
    <?php
    render_tour_faq_schema($faqs);
}

/** FAQPage JSON-LD only - for pages that render their own FAQ markup
 *  (e.g. the lab <details> accordion on the Maipo page). */
function render_tour_faq_schema(array $faqs): void
{
    if (empty($faqs)) {
        return;
    }
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static function (array $faq): array {
            return [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }, $faqs),
    ];
    // JSON_HEX_TAG: a "</script>" inside an answer can't end the tag early
    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>' . "\n";
}
```

Also update the header comment (line 3-4) to mention `render_tour_faq_schema()`.

- [ ] **Step 3: Add the flag to `includes/tour-scripts.php`**

Add this paragraph to the header comment, after the `$exp_name` example:

```
 * Optional: $tour_scripts_skip_gallery = true; before including omits the
 * Slider Pro gallery (CSS+JS) and the theia sticky sidebar - for pages
 * without #Img_carousel / #sidebar (the lab Maipo page). js/tours.js guards
 * its Slider Pro init, so it is safe to omit them.
```

Wrap the two gallery/sidebar blocks:

```php
<?php if (empty($tour_scripts_skip_gallery)): ?>
<!-- Gallery Plugin -->
<link rel="stylesheet" href="css/slider-pro.min.css">
<script defer src="js/jquery.sliderPro.min.js"></script>

<!-- Sticky Sidebar -->
<script defer src="js/theia-sticky-sidebar.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  jQuery(function($){
    if ($.fn.theiaStickySidebar) {
      $('#sidebar').theiaStickySidebar({ additionalMarginTop: 80 });
    }
  });
});
</script>
<?php endif; ?>
```

Then change the last line to `<script defer src="js/tours.js?v=20261014"></script>`.

- [ ] **Step 4: Guard the two `js/tours.js` blocks and bump the version in `booking_manual.php`**

Replace `js/tours.js` lines 1-15 (the bare `$('#Img_carousel').sliderPro({...}).css('visibility', 'visible');` block) with:

```js

   // Slider Pro gallery - only on pages that have it (the lab Maipo page
   // and booking_manual.php don't load the plugin / have no #Img_carousel)
   if (window.jQuery && jQuery.fn.sliderPro && jQuery('#Img_carousel').length) {
     jQuery('#Img_carousel').sliderPro({
       width: 960,
       height: 500,
       fade: true,
       arrows: true,
       buttons: false,
       fullScreen: false,
       smallSize: 500,
       startSlide: 0,
       mediumSize: 1000,
       largeSize: 3000,
       thumbnailArrows: true,
       autoplay: false
     }).css('visibility', 'visible');
   }
```

In the "Book Now floating button" block, add a guard as the first statement inside `$(function() {`:

```js
   $(function() {
     // Pages with the lab sticky CTA bar (includes/ui/sticky_bar.php) have
     // their own "Check availability" button - don't add this one.
     if (document.querySelector('[data-sticky-cta]')) return;
     var $win = $(window),
```

In `booking_manual.php` line 167, change `js/tours.js?v=20261013` to `js/tours.js?v=20261014`.

Run: `node --check js/tours.js`
Expected: no output (valid syntax).

- [ ] **Step 5: Create `js/lab.js`**

```js
/* js/lab.js
 * Behaviour for the "lab" UI components (includes/ui/). Vanilla, deferred.
 * - Gallery: [data-lab-gallery-item] links open in <dialog data-lab-lightbox>
 *   with prev/next buttons and arrow keys; Esc closes (native <dialog>).
 *   Without <dialog> support the links just open the image file.
 * - Sticky bar [data-sticky-cta]: hidden while its data-target (#book) is on
 *   screen; label/price follow 'maipo:optionchange' (js/maipo-options.js),
 *   using the bar's data-labels / data-prices JSON.
 * Smooth scrolling for #book links comes from CSS (scroll-behavior), with
 * prefers-reduced-motion handled in css/src/lab-base.css.
 * Load BEFORE js/maipo-options.js so the optionchange listener exists first.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function parse(json) {
    try { return JSON.parse(json || '{}') || {}; } catch (e) { return {}; }
  }

  // ---- gallery lightbox ----
  (function () {
    var dialog = document.querySelector('[data-lab-lightbox]');
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-lab-gallery-item]'));
    if (!dialog || !items.length || typeof dialog.showModal !== 'function') return;
    var img = dialog.querySelector('[data-lab-lightbox-img]');
    var caption = dialog.querySelector('[data-lab-lightbox-caption]');
    var current = 0;

    function show(i) {
      current = (i + items.length) % items.length;
      var item = items[current];
      var thumb = item.querySelector('img');
      img.src = item.getAttribute('href');
      img.alt = thumb ? thumb.alt : '';
      caption.textContent = item.getAttribute('data-caption') || '';
    }

    items.forEach(function (item, i) {
      item.addEventListener('click', function (e) {
        e.preventDefault();
        show(i);
        dialog.showModal();
      });
    });
    dialog.querySelector('[data-lab-lightbox-prev]').addEventListener('click', function () { show(current - 1); });
    dialog.querySelector('[data-lab-lightbox-next]').addEventListener('click', function () { show(current + 1); });
    dialog.querySelector('[data-lab-lightbox-close]').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });
    // a click on the dark backdrop (the dialog box itself, not its content) closes
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
  })();

  // ---- sticky CTA bar ----
  (function () {
    var bar = document.querySelector('[data-sticky-cta]');
    if (!bar) return;
    var labels = parse(bar.getAttribute('data-labels'));
    var prices = parse(bar.getAttribute('data-prices'));
    var labelEl = bar.querySelector('[data-sticky-label]');
    var priceEl = bar.querySelector('[data-sticky-price]');

    document.addEventListener('maipo:optionchange', function (e) {
      var slug = e.detail && e.detail.option;
      if (!slug || prices[slug] == null) return;
      if (labelEl) labelEl.textContent = labels[slug] || '';
      if (priceEl) priceEl.textContent = '$' + Number(prices[slug]).toLocaleString('en-US', { maximumFractionDigits: 0 });
    });

    var target = document.querySelector(bar.getAttribute('data-target') || '#book');
    if (target && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        bar.hidden = entries[0].isIntersecting;
      }).observe(target);
    }
  })();
});
```

Run: `node --check js/lab.js`
Expected: no output.

- [ ] **Step 6: Run the full suite**

Run: `php tests/tools/phpunit.phar`
Expected: all green, including TourFaqTest (3) and TourScriptsTest (2).

- [ ] **Step 7: Commit**

```bash
git add includes/tour_faq.php includes/tour-scripts.php js/tours.js js/lab.js booking_manual.php tests/TourFaqTest.php tests/TourScriptsTest.php tests/bootstrap.php
git commit -m "feat: lab.js (lightbox, sticky bar), FAQ schema helper, gallery-free tour scripts

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Assemble the Maipo page

**Files:**
- Modify: `maipo-valley-wine-tour-santiago.php` (full rewrite of the template part; the PHP data block is kept)
- Modify (generated): `css/lab.css`

**Interfaces:**
- Consumes:
  - Every `ui_*` component (Tasks 3-6).
  - The `maipo_lab_*` content (Task 2).
  - `render_tour_faq_schema` and `$tour_scripts_skip_gallery` (Task 7).
  - `js/lab.js` (Task 7).
  - The existing `maipo_options()`, `maipo_resolve_option()`, `fetch_tour_prices()`, `render_tour_product_schema()`.

- [ ] **Step 1: Rewrite the page**

Keep lines 1-46 (the data block) with these edits:
- Replace line 6 with two lines:
  ```php
  $lcp_preload_image = 'img/Tours/Maipo/2026/vineyard-toast-1280.webp';
  $lcp_preload_image_mobile = 'img/Tours/Maipo/2026/vineyard-toast-960.webp';
  ```
- After `$vendor_css_variant = 'tour';`, add:
  ```php
  $page_og = [
      'title' => $page_title,
      'description' => $page_description,
      'url' => $page_canonical,
      'image' => 'https://stampstour.com/img/Tours/Maipo/2026/vineyard-toast-1280.jpg',
  ];
  ```
- Replace `require __DIR__ . '/includes/tours/option_ui.php';` with:
  ```php
  require __DIR__ . '/includes/tours/maipo_lab_content.php';
  require __DIR__ . '/includes/ui/ui.php';
  ```
- After the `$tour_faqs = [...];` array, add:
  ```php
  $maipo_labels = array_map(static fn(array $o) => $o['label'], $maipo_options);
  ```

Replace everything from `?>` after `$tour_faqs` to the end of the file with:

```php
?>
<!DOCTYPE html>
<html lang="en">
 <head>
<?php include __DIR__ . '/includes/head.php'; ?>
  <link rel="stylesheet" href="css/lab.css?v=20261014">
 </head>
 <body>
  <div id="preloader">
   <div class="sk-spinner sk-spinner-wave">
    <div class="sk-rect1"></div>
    <div class="sk-rect2"></div>
    <div class="sk-rect3"></div>
    <div class="sk-rect4"></div>
    <div class="sk-rect5"></div>
   </div>
  </div>
  <!-- End Preload -->
  <div class="layer"></div>
  <!-- Mobile menu overlay mask -->
  <!-- Header================================================== -->
  <?php include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>
  <?php
  require __DIR__ . '/includes/tour_schema.php';
  $schema_offers = [];
  foreach ($maipo_js_options as $slug => $o) {
      $schema_offers[] = ['name' => $maipo_options[$slug]['label'], 'price' => $o['adult'], 'url' => $page_canonical . '?option=' . $slug];
  }
  render_tour_product_schema([
      'name' => 'Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries',
      'description' => $page_description,
      'image' => 'img/Tours/Maipo/2026/vineyard-toast-1280.jpg',
      'url' => $page_canonical,
      'price' => $dynamic_price_adult,
      'offers' => $schema_offers,
      'sameAs' => ['https://www.tripadvisor.com/AttractionProductReview-g294305-d15040546-Maipo_Valley_Wine_Tour_with_4_vineyards_from_Santiago-Santiago_Santiago_Metropolit.html'],
  ]);
  ?>
  <!-- End Header -->
  <!-- Lab landing page: components in includes/ui/, styles css/lab.css
       (bin/build-css). See docs/superpowers/specs/2026-10-14-maipo-lab-redesign-design.md -->
  <main class="lab">
   <?php ui_hero(maipo_lab_hero($dynamic_price_adult)); ?>

   <?php ui_section_open('why', 'Why this tour', 'A day in Chilean wine country', maipo_lab_intro()); ?>
    <?php ui_benefits(maipo_lab_benefits()); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('routes', 'Choose your route', 'Three ways to spend the day'); ?>
    <?php ui_option_cards($maipo_options, $maipo_js_options, $maipo_selected, 'tour_option', 'Choose your route'); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('gallery', 'Gallery', 'What the day looks like'); ?>
    <?php ui_gallery(maipo_lab_photos()); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('itinerary', 'Your day', 'Itinerary', 'If a winery is unavailable on your date, it may be replaced with another of equal quality.'); ?>
    <?php ui_itinerary($maipo_options, $maipo_js_options, $maipo_selected); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('included', "What's included", 'Included in your route'); ?>
    <?php foreach ($maipo_options as $slug => $opt): ?>
     <?= ui_option_block_open($slug, $maipo_selected) ?>
      <?php ui_checklist($opt['included'], $opt['not_included']); ?>
     <?= ui_option_block_close() ?>
    <?php endforeach; ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('book', 'Book your date', 'Reserve your seats'); ?>
    <?php ui_booking_panel($maipo_options, $maipo_js_options, $maipo_selected); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('good-to-know', 'Good to know', 'Practical info'); ?>
    <?php ui_accordion(maipo_lab_practical(), 'practical-info'); ?>
   <?php ui_section_close(); ?>

   <?php ui_section_open('faq', 'FAQ', 'Questions travelers ask'); ?>
    <?php ui_accordion($tour_faqs, 'faq-list'); ?>
    <?php render_tour_faq_schema($tour_faqs); ?>
   <?php ui_section_close(); ?>

   <?php ui_sticky_bar($maipo_labels, $maipo_js_options, $maipo_selected); ?>
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <!-- End footer -->
  <div id="toTop"></div>
  <!-- JavaScript Files -->
<script>
window.MAIPO_OPTIONS = <?= json_encode((object)$maipo_js_options, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.MAIPO_SELECTED = <?= json_encode($maipo_selected) ?>;
</script>
<?php $tour_scripts_skip_gallery = true; include __DIR__ . '/includes/tour-scripts.php'; ?>
<script defer src="js/lab.js?v=20261014"></script>
<script defer src="js/maipo-options.js?v=20261013"></script>
  </body>
</html>
```

This removes from the page: the `css/timeline.css` and `css/tour-options.css` links, `.tour-banner`, `#collapseMap`, `#single_tour_feat`, `#Img_carousel`, the Bootstrap two-column layout, `#sidebar`, the old itinerary "See more" wrapper, and the `render_tour_faq()` accordion.

- [ ] **Step 2: Rebuild the CSS and run the full suite**

Run: `bin/build-css && php tests/tools/phpunit.phar`
Expected: green. The `css/lab.css` size printed must be ≤ 30720 bytes.

- [ ] **Step 3: Server-side smoke checks**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l maipo-valley-wine-tour-santiago.php
php -S 127.0.0.1:8099 > .superpowers/lab-metrics/server.log 2>&1 &
SERVER_PID=$!; sleep 1
U=http://127.0.0.1:8099/maipo-valley-wine-tour-santiago.php
curl -s "$U" > /tmp/lab-default.html
curl -s "$U?option=premium" > /tmp/lab-premium.html
curl -s "$U?option%5B%5D=x" > /tmp/lab-array.html
curl -s "$U?option=PREMIUM" > /tmp/lab-upper.html
for f in /tmp/lab-*.html; do
  echo "$f h1=$(grep -c '<h1' $f) product=$(grep -c '"@type":"Product"' $f) faq=$(grep -c '"@type":"FAQPage"' $f) book=$(grep -c 'id="booking"' $f) sticky=$(grep -c 'data-sticky-cta' $f) slider=$(grep -c 'sliderPro' $f) warn=$(grep -ci 'warning\|notice\|deprecated' $f)"
done
grep -o '<div data-option="[a-z-]*"[^>]*>' /tmp/lab-premium.html | sort | uniq -c
grep -o '<div data-option="[a-z-]*"[^>]*>' /tmp/lab-array.html | sort | uniq -c
grep -o 'og:image" content="[^"]*"' /tmp/lab-default.html
grep -o 'rel="preload" as="image" href="[^"]*" media="[^"]*"' /tmp/lab-default.html
grep -ci 'warning\|notice\|fatal' .superpowers/lab-metrics/server.log
kill $SERVER_PID
```

Expected:
- Every file reports `h1=1 product=1 faq=1 book=1 sticky=1 slider=0 warn=0`.
- `premium` and `upper`: `<div data-option="premium">` ×2 (itinerary + checklist), the other two slugs `hidden` ×2 each.
- `array`: `classic` visible ×2, the others hidden.
- `og:image` points at `…/2026/vineyard-toast-1280.jpg`.
- Two preload lines: 960.webp with `(max-width: 767px)` and 1280.webp with `(min-width: 768px)`.
- The server-log warning count is 0.

- [ ] **Step 4: Commit**

```bash
git add maipo-valley-wine-tour-santiago.php css/lab.css
git commit -m "feat: Maipo tour page rebuilt as the lab landing page

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Browser verification, performance comparison, and report

**Files:**
- Create (scratch, not committed): `/private/tmp/claude-501/-Users-miguelmontero-Documents-superpowers/fc68a621-696e-4e18-b43f-5506afb46d53/scratchpad/pw/lab-check.js`
- Modify (scratch): `…/scratchpad/pw/check.js` (tighten check 5 to `=== 9`)
- Create: `docs/superpowers/reports/2026-10-14-maipo-lab-redesign-verification.md`
- Modify only if a check fails: the files that own the defect, with a rebuilt `css/lab.css` if any class changed.

**Interfaces:**
- Consumes: the whole page from Task 8 and the Task 1 baseline in `.superpowers/lab-metrics/before.txt`.

- [ ] **Step 1: Write `lab-check.js`**

```js
// lab-check.js - headless checks for the Maipo lab page. Run from the pw/ dir
// with the PHP server on 127.0.0.1:8099. Never loads shopping.php.
const { chromium } = require('playwright');
const fs = require('fs');
const B = 'http://127.0.0.1:8099/maipo-valley-wine-tour-santiago.php';
const SHOTS = '/Users/miguelmontero/Documents/superpowers/STAMP/.superpowers/lab-metrics/shots';
const EXE = '/Users/miguelmontero/Library/Caches/ms-playwright/chromium_headless_shell-1243/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const results = [];
const rec = (n, ok, info = '') => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'} ${n} ${info}`); };

async function scrollThrough(page) {
  const h = await page.evaluate(() => document.documentElement.scrollHeight);
  for (let y = 0; y < h; y += 600) { await page.evaluate(v => window.scrollTo(0, v), y); await page.waitForTimeout(120); }
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
}

(async () => {
  fs.mkdirSync(SHOTS, { recursive: true });
  const browser = await chromium.launch({ executablePath: EXE });

  for (const [name, vp] of [['mobile', { width: 390, height: 844 }], ['desktop', { width: 1440, height: 900 }]]) {
    const page = await browser.newPage({ viewport: vp });
    const errs = [], bad = [], reqs = [];
    page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    page.on('pageerror', e => errs.push('pageerror: ' + e.message));
    page.on('request', r => reqs.push(r.url()));
    page.on('response', r => { if (r.status() >= 400) bad.push(r.status() + ' ' + r.url()); });
    await page.route(/shopping\.php/, r => r.abort());
    await page.goto(B, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    rec(`${name}: no horizontal scroll`, overflow <= 0, `overflow=${overflow}`);

    const h1 = (await page.locator('h1').allTextContents()).map(t => t.replace(/\s+/g, ' ').trim());
    rec(`${name}: exactly one H1 with the SEO title`, h1.length === 1 && h1[0] === 'Small-Group Maipo Valley Wine Tour from Santiago: 4 Wineries', JSON.stringify(h1));

    const lcpImg = await page.locator('main.lab section img[fetchpriority="high"]').evaluate(i => i.currentSrc);
    rec(`${name}: hero loads the ${name === 'mobile' ? '960' : '1280'} WebP`, lcpImg.endsWith(name === 'mobile' ? 'vineyard-toast-960.webp' : 'vineyard-toast-1280.webp'), lcpImg);

    const bar = page.locator('[data-sticky-cta]');
    await page.evaluate(() => window.scrollTo(0, 900));
    await page.waitForTimeout(400);
    const barMid = await bar.isVisible();
    await page.locator('#book').scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    const barAtBook = await bar.isVisible();
    rec(`${name}: sticky bar visible mid-page, hidden at #book`, barMid && !barAtBook, `mid=${barMid} atBook=${barAtBook}`);

    await page.locator('#routes').evaluate(e => e.scrollIntoView({ block: 'center' }));
    await page.locator('label[for="tour_option-premium"]').click();
    await page.waitForTimeout(300);
    const sLabel = (await page.locator('[data-sticky-label]').textContent()).trim();
    const sPrice = (await page.locator('[data-sticky-price]').textContent()).trim();
    rec(`${name}: sticky bar follows the selected route`, sLabel === 'Premium Route' && sPrice === '$139', `${sLabel} ${sPrice}`);
    const booked = await page.locator('input[name="tour_option_book"]:checked').getAttribute('value');
    rec(`${name}: booking-panel route synced`, booked === 'premium', booked);

    await page.locator('[data-lab-gallery-item]').first().click();
    await page.waitForTimeout(300);
    const dlg = page.locator('[data-lab-lightbox]');
    const open1 = await dlg.evaluate(d => d.open);
    const src1 = await page.locator('[data-lab-lightbox-img]').getAttribute('src');
    await page.keyboard.press('ArrowRight');
    const src2 = await page.locator('[data-lab-lightbox-img]').getAttribute('src');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);
    const open2 = await dlg.evaluate(d => d.open);
    rec(`${name}: lightbox opens, navigates, closes on Esc`, open1 && /-1600\.webp$/.test(src1 || '') && src2 !== src1 && !open2, `${src1} -> ${src2}`);

    await page.evaluate(() => window.scrollTo(0, 1500));
    await page.waitForTimeout(400);
    rec(`${name}: old header "Book now" button not added`, (await page.locator('#hdr-booking-wrap').count()) === 0);

    rec(`${name}: Slider Pro / theia not requested`, !reqs.some(u => /sliderPro|slider-pro|theia-sticky/.test(u)));

    await page.locator('#date_booking').click();
    await page.waitForTimeout(300);
    rec(`${name}: date picker opens`, await page.locator('.daterangepicker').first().isVisible());
    await page.evaluate(() => jQuery('#date_booking').data('daterangepicker').hide());

    await scrollThrough(page);
    await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true });
    rec(`${name}: no console errors / failed requests`, errs.length === 0 && bad.length === 0, JSON.stringify({ errs, bad }));
    await page.close();
  }

  // JavaScript disabled: server-selected option visible, others hidden
  const ctx = await browser.newContext({ javaScriptEnabled: false });
  const nojs = await ctx.newPage();
  await nojs.goto(B + '?option=classic-lunch');
  const vis = await nojs.$$eval('[data-option]', els => els.filter(e => !e.hidden).map(e => e.dataset.option));
  rec('no-JS: only classic-lunch blocks visible', vis.length === 2 && vis.every(v => v === 'classic-lunch'), JSON.stringify(vis));
  const href = await nojs.locator('[data-lab-gallery-item]').first().getAttribute('href');
  rec('no-JS: gallery tiles link to the 1600px image', /-1600\.webp$/.test(href), href);
  await ctx.close();

  await browser.close();
  console.log(results.every(Boolean) ? 'ALL PASS' : 'SOME FAIL');
})().catch(e => { console.error(e); process.exit(1); });
```

- [ ] **Step 2: Adapt `check.js` to the new layout**

In `…/scratchpad/pw/check.js`:
- **Check 5:** change `dis.length>=6` to `dis.length===9` (3 groups × 3 radios).
- **Check 3:** the route cards now sit below the fold, so a raw mouse click at the label's top-of-page coordinates misses. Replace this line:
  ```js
  await page.evaluate(()=>window.scrollTo(0,0)); const bb = await page.locator('label[for="tour_option-classic"]').boundingBox(); console.log('booking label box', bb); await page.mouse.click(bb.x+bb.width/2, bb.y+bb.height/2);
  ```
  with:
  ```js
  await page.locator('#routes').evaluate(e => e.scrollIntoView({ block: 'center' })); await page.locator('label[for="tour_option-classic"]').click();
  ```

- [ ] **Step 3: Run all browser checks**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S 127.0.0.1:8099 > .superpowers/lab-metrics/server.log 2>&1 &
SERVER_PID=$!; sleep 1
cd /private/tmp/claude-501/-Users-miguelmontero-Documents-superpowers/fc68a621-696e-4e18-b43f-5506afb46d53/scratchpad/pw
node lab-check.js | tee /Users/miguelmontero/Documents/superpowers/STAMP/.superpowers/lab-metrics/lab-check.txt
node check.js | tee /Users/miguelmontero/Documents/superpowers/STAMP/.superpowers/lab-metrics/check.txt
node e2e.js | tee /Users/miguelmontero/Documents/superpowers/STAMP/.superpowers/lab-metrics/e2e.txt
```

Expected:
- `lab-check.js` and `check.js` each end with `ALL PASS`.
- `e2e.js` prints totals `220` / `258` / `278`, `book_tour status 302` with a `location` containing `shopping.php`, and `shopping.php navigated:` showing the aborted URL.

If anything fails, debug it with superpowers:systematic-debugging, fix it in the owning file, rebuild `css/lab.css` if classes changed, rerun the PHPUnit suite and these three scripts, and commit the fix:

```bash
git add <the fixed files> css/lab.css
git commit -m "fix: <what>

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 4: Remove the e2e bookings from the local DB**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -r 'require "../db_config.php"; $conn->query("DELETE FROM reservas WHERE email LIKE '\''e2e-%@example.test'\''"); echo "deleted ", $conn->affected_rows, "\n";'
```

Expected: `deleted 3`, or the number of e2e rows created.

- [ ] **Step 5: Measure performance after the change (same method as the Task 1 baseline)**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
for i in 1 2 3; do
  CHROME_PATH="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  npx --yes lighthouse@12.6.0 "http://127.0.0.1:8099/maipo-valley-wine-tour-santiago.php" \
    --only-categories=performance --output=json \
    --output-path=".superpowers/lab-metrics/after-$i.json" \
    --chrome-flags="--headless=new" --quiet
done
node -e 'for (const f of process.argv.slice(1)) { const r = require(f), a = r.audits; console.log(f.split("/").pop(), "perf", Math.round(r.categories.performance.score*100), "LCP", Math.round(a["largest-contentful-paint"].numericValue), "CLS", a["cumulative-layout-shift"].numericValue.toFixed(3), "TBT", Math.round(a["total-blocking-time"].numericValue)); }' \
  "$PWD"/.superpowers/lab-metrics/after-*.json | tee .superpowers/lab-metrics/after.txt
kill $SERVER_PID
```

Pass criteria:
- The median perf score is not lower than the baseline median.
- Every run has CLS ≤ 0.1.

If either fails, find the cause (for example a missing width/height or a late-loading font shifting the hero), fix it, and measure again.

- [ ] **Step 6: Look at the screenshots**

Open `.superpowers/lab-metrics/shots/mobile.png` and `desktop.png` with the Read tool and compare them with the approved mockup (`.superpowers/brainstorm/64656-1791949215/content/mockup-full-v3.html`). Check:
- The header is readable over the hero.
- Every section has the charcoal background.
- The route cards show coral prices and a teal selected border.
- The mosaic has no holes.
- The timeline dots line up.
- The booking panel shows two columns on desktop and one on mobile, and the +/- counters fit.
- The accordions are closed.
- The footer is unchanged.
- No white Bootstrap backgrounds or dark-on-dark text leak in.

Fix any defect as in Step 3, then rerun `lab-check.js` and take new screenshots.

- [ ] **Step 7: Write the verification report**

Create `docs/superpowers/reports/2026-10-14-maipo-lab-redesign-verification.md`. It must contain:
- The branch and commit range.
- The PHPUnit summary line.
- `bin/build-css --check` output and `css/lab.css` size.
- The `lab-check.txt`, `check.txt` and `e2e.txt` results (PASS/FAIL lines).
- The e2e DB cleanup count.
- A before/after Lighthouse table (perf, LCP, CLS, TBT per run, plus medians).
- Screenshot paths.
- Any fixes made in this task.
- The manual checks left for the owner after deploy:
  - The Rich Results test on the live URL.
  - One real booking reaching the payment page with the right amount.
  - A visual check on a real phone.
  - Purging the Cloudflare cache.

```bash
git add docs/superpowers/reports/2026-10-14-maipo-lab-redesign-verification.md
git commit -m "docs: Maipo lab redesign verification report

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
