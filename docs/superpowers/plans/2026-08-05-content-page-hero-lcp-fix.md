# Content-Page Hero Image LCP Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the ~1.6s "element render delay" a live Lighthouse audit measured on `gallery.php`'s hero image (and 6 other pages sharing the identical mechanism) by replacing a JS-driven CSS background with a real `<img fetchpriority="high">`, discoverable by the browser from the initial HTML instead of only after CSS parses and jQuery runs.

**Architecture:** All 7 pages currently render `<section id="hero_2" class="background-image" data-background="url(...)">`, and `js/functions.js` applies the image as an inline `background-image` style on page load. This plan replaces that per-page markup with a real `<img class="hero-bg-img" fetchpriority="high">` as the first child of `#hero_2`, styled via one new absolutely-positioned CSS rule to visually behave exactly like the background it replaces — reusing the *technique* already proven on this site's tour pages (`.tour-banner-bg`), not their specific classes (tour pages have a different visual layout). The shared hero image itself is also re-encoded as a properly quality-tuned WebP.

**Tech Stack:** Plain PHP/HTML markup, CSS, `sharp` (Node, already a dependency in `gallery-pipeline/`) for the one-off image re-encode. No build step.

## Global Constraints

- Hero image: re-encode `img/Tours/Stgo/big.jpg` (1400×1050, currently 320KB JPEG) as WebP at **quality 75**, same 1400×1050 dimensions (no resize/recrop — see the spec's Non-goals for why: on wide desktop viewports, `object-fit: cover` makes width the constraining dimension, so narrowing the source would cause more upscaling, not less). Overwrite the existing, currently-unused `img/Tours/Stgo/big.webp`. Measured directly against the real source: quality 75 → ~193KB (vs. quality 70 → ~182KB, visibly softer; quality 80 → ~230KB). `big.jpg` itself stays untouched.
- New CSS rule, exact name and values:
  ```css
  .hero-bg-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 1;
  }
  ```
  Added to `css/custom.css`, near the existing `.tour-banner-bg` rule it's modeled on. `z-index: 1` is required — it must stay beneath `.opacity-mask` (existing rule elsewhere in the CSS, `z-index: 2`) so the dark overlay still renders on top of the image, unchanged from today.
- New `<img>` markup, exact attributes on every page: `src="/img/Tours/Stgo/big.webp"`, `width="1400"`, `height="1050"`, `fetchpriority="high"`, `alt=""` (empty — decorative background art; each page's own `<h1>` conveys the actual content), `class="hero-bg-img"`.
- Scope is exactly these 7 files: `gallery.php`, `return.php`, `refunds-cancellations.php`, `shopping.php`, `contact-us.php`, `privacy.php`, `blog.php`. **`admin/_hero.php` is explicitly out of scope** — it uses the identical mechanism but is behind login and not performance-critical. Do not modify it, and do not remove or modify `js/functions.js`'s `.background-image` handler (lines ~402-403) — it must keep working for that one remaining page.
- `shopping.php`'s hero section has an extra `d-none d-md-block` class on top of `background-image` (hides the hero on mobile, shows it ≥768px) — this must be preserved on the new markup; only `background-image` (and its `data-background` attribute) get removed, not `d-none d-md-block`.
- Do not touch `#hero_2`'s existing CSS rule (its `background-color`/`background-size`/`background-position`/`background-repeat` declarations become inert but harmless — cleaning them up is explicitly out of scope per the spec).
- Do not touch the tour pages (`discover-santiago-city-tour.php` and siblings) — already fixed in prior work, not part of this change.

---

### Task 1: Re-encode the hero image and add the CSS rule

**Files:**
- Modify (overwrite): `img/Tours/Stgo/big.webp`
- Modify: `css/custom.css`

**Interfaces:**
- Consumes: `img/Tours/Stgo/big.jpg` (existing source, untouched).
- Produces: `img/Tours/Stgo/big.webp` at the new quality/size, and the `.hero-bg-img` CSS class — both consumed by every page edited in Task 2.

- [ ] **Step 1: Re-encode the image**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP/gallery-pipeline
node -e "
const sharp = require('sharp');
sharp('../img/Tours/Stgo/big.jpg')
  .webp({ quality: 75 })
  .toFile('../img/Tours/Stgo/big.webp')
  .then(info => console.log('wrote', info.width + 'x' + info.height, (info.size/1024).toFixed(1) + ' KB'));
"
```
Expected output: `wrote 1400x1050 <size> KB`, with `<size>` in the ~190-200 KB range (matches the design spec's measured ~193KB at quality 75 — if it comes out wildly different, e.g. under 100KB or over 250KB, stop and check the command ran against the right source file before proceeding).

- [ ] **Step 2: Confirm the file is a valid WebP at the right dimensions**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
file img/Tours/Stgo/big.webp
```
Expected: `... Web/P image, VP8 encoding, 1400x1050 ...`

- [ ] **Step 3: Add the CSS rule**

In `css/custom.css`, find (the existing `.tour-banner-bg` rule, used here purely as an anchor point — do not modify it):
```css
.tour-banner-bg {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center center;
}
```
Replace with (adds the new rule immediately after, changes nothing about `.tour-banner-bg` itself):
```css
.tour-banner-bg {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center center;
}
.hero-bg-img {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
	z-index: 1;
}
```

- [ ] **Step 4: Confirm `git status` shows exactly the expected two files**

```bash
git status --short img/Tours/Stgo/big.webp css/custom.css
```
Expected: `M img/Tours/Stgo/big.webp` and `M css/custom.css`, nothing else.

- [ ] **Step 5: Commit**

```bash
git add img/Tours/Stgo/big.webp css/custom.css
git commit -m "Re-encode hero image as WebP and add .hero-bg-img CSS rule"
```

---

### Task 2: Replace the JS-driven background with a real `<img>` on all 7 pages

**Files:**
- Modify: `gallery.php:46-47`
- Modify: `return.php:294-295`
- Modify: `refunds-cancellations.php:43-44`
- Modify: `shopping.php:253-254`
- Modify: `contact-us.php:22-23`
- Modify: `privacy.php:43-44`
- Modify: `blog.php:31-32`

**Interfaces:**
- Consumes: `img/Tours/Stgo/big.webp` and the `.hero-bg-img` CSS class, both from Task 1.
- Produces: nothing consumed by a later task — this is the last content-editing task before verification.

- [ ] **Step 1: `gallery.php`**

Find:
```html
  <section id="hero_2" class="background-image" data-background="url(/img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```
Replace with:
```html
  <section id="hero_2">
    <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```

- [ ] **Step 2: `return.php`**

Find:
```html
  <section id="hero_2" class="background-image" data-background="url(img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
```
Replace with:
```html
  <section id="hero_2">
    <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
```

- [ ] **Step 3: `refunds-cancellations.php`**

Find:
```html
<section id="hero_2" class="background-image" data-background="url(img/Tours/Stgo/big.jpg)">
  <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```
Replace with:
```html
<section id="hero_2">
  <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
  <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```

- [ ] **Step 4: `shopping.php`** (note the extra `d-none d-md-block` class, which must be preserved — see Global Constraints)

Find:
```html
    <section id="hero_2" class="background-image d-none d-md-block" data-background="url(img/Tours/Stgo/big.jpg)">
        <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
```
Replace with:
```html
    <section id="hero_2" class="d-none d-md-block">
        <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
        <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
```

- [ ] **Step 5: `contact-us.php`**

Find:
```html
  <section id="hero_2" class="background-image" data-background="url(img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```
Replace with:
```html
  <section id="hero_2">
    <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```

- [ ] **Step 6: `privacy.php`**

Find:
```html
<section id="hero_2" class="background-image" data-background="url(img/Tours/Stgo/big.jpg)">
  <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```
Replace with:
```html
<section id="hero_2">
  <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
  <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```

- [ ] **Step 7: `blog.php`**

Find:
```html
  <section id="hero_2" class="background-image" data-background="url(/img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```
Replace with:
```html
  <section id="hero_2">
    <img src="/img/Tours/Stgo/big.webp" width="1400" height="1050" fetchpriority="high" alt="" class="hero-bg-img">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
```

- [ ] **Step 8: Lint all 7 files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
for f in gallery.php return.php refunds-cancellations.php shopping.php contact-us.php privacy.php blog.php; do
  php -l "$f"
done
```
Expected: `No syntax errors detected in <file>` for all 7.

- [ ] **Step 9: Confirm no page still references the old JS-driven mechanism**

```bash
grep -l 'class="background-image"' gallery.php return.php refunds-cancellations.php shopping.php contact-us.php privacy.php blog.php
```
Expected: no output (empty — none of the 7 files contain that string anymore).

- [ ] **Step 10: Confirm `admin/_hero.php` and `js/functions.js` are untouched**

```bash
git status --short admin/_hero.php js/functions.js
```
Expected: no output — neither file appears as modified (per Global Constraints, this task must not touch them).

- [ ] **Step 11: Visual verification with a local server — gallery.php and one other page**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/hero-fix-test.log 2>&1 &
sleep 1
curl -s http://localhost:8899/gallery.php | grep -o '<img src="/img/Tours/Stgo/big.webp"[^>]*>'
curl -s http://localhost:8899/blog.php | grep -o '<img src="/img/Tours/Stgo/big.webp"[^>]*>'
curl -s -o /dev/null -w "gallery.php: %{http_code}\n" http://localhost:8899/gallery.php
curl -s -o /dev/null -w "blog.php: %{http_code}\n" http://localhost:8899/blog.php
pkill -f "php -S localhost:8899"
```
Expected: both `grep` calls print the new `<img>` tag with `fetchpriority="high"`; both pages return `200`.

Then open `http://localhost:8899/gallery.php` and `http://localhost:8899/blog.php` in a real browser (start `php -S localhost:8899` again if you killed it above) and visually confirm: the hero image displays correctly, same crop/framing as before this change, the dark overlay and centered white heading still render on top of it correctly, and there's no layout jump as the page loads.

- [ ] **Step 12: Commit**

```bash
git add gallery.php return.php refunds-cancellations.php shopping.php contact-us.php privacy.php blog.php
git commit -m "Replace JS-driven hero background with fetchpriority=high <img> on 7 pages"
```

---

### Task 3: Deploy

**Files:**
- None modified — this task pushes already-committed changes.

**Interfaces:**
- Consumes: the commits from Tasks 1-2.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push origin main
```

- [ ] **Step 2: Remind the user to deploy, purge cache, and re-audit**

State clearly: pushing to `origin/main` does not deploy automatically — `git pull` on the production server is required, and per this site's confirmed caching behavior, static assets (`img/Tours/Stgo/big.webp`, `css/custom.css`) are cached at Cloudflare's edge independently of dynamic PHP responses, so a Cloudflare cache purge is needed after pulling for the change to actually reach visitors. Once deployed and purged, re-run the same Lighthouse mobile audit against `https://stampstour.com/gallery.php` used to diagnose this issue, and compare against the documented baseline (57/100 performance score, LCP 11.7s, `lcp-breakdown-insight` "element render delay" of 1,597ms) — per the spec's Verification section, also spot-check at least one other of the 7 pages, not just gallery.php, to confirm the fix generalized correctly across all of them.
