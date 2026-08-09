# Accessibility Tree Structure Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix two real, `axe-core`-confirmed, sitewide accessibility defects: a duplicate/non-top-level `<footer>` landmark on every page (13 pages wrap the shared footer include in their own redundant `<footer>` tag), and 7 icon-only links with no discernible accessible name (3 in the header, 3 in the footer, 1 sticky-logo edge case).

**Architecture:** Remove the redundant outer `<footer>` wrapper from 13 page files, leaving `includes/footer.php`'s own `<footer>` as the sole landmark. Add `aria-label` to icon-only links in `includes/header.php` and `includes/footer.php`, with `aria-hidden="true"` on the now-redundant decorative icon glyphs.

**Tech Stack:** PHP/HTML markup only, no CSS/JS changes.

## Global Constraints

- `class="revealed"` on 10 of the 13 pages' footer wrappers has zero CSS or JS behavior anywhere in the codebase (confirmed via full-codebase grep during brainstorming) — discard it along with the wrapper, do not migrate it elsewhere.
- `includes/footer.php` and `includes/header.php` themselves are NOT modified in Task 1 — only the 13 page files' wrapper markup changes. `footer.php`'s own `<footer>...</footer>` tags stay exactly as they are.
- Icon-only link `aria-label` values: `"Instagram"`, `"Facebook"`, `"WhatsApp"` — short platform names, not longer descriptive phrases. Preserve each file's own existing icon order (header: Instagram/Facebook/WhatsApp; footer: Instagram/WhatsApp/Facebook — these differ, do not reconcile them).
- `admin.php` is explicitly out of scope — confirmed during brainstorming to have its own standalone, non-duplicated footer (doesn't include `includes/footer.php` at all).
- Preserve each file's existing indentation style exactly — these are hand-formatted PHP/HTML files, not minified.

---

### Task 1: Remove the redundant footer wrapper (13 pages)

**Files:**
- Modify: `index.php`, `blog-post.php`, `gallery.php`, `shopping.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `cruise-transfer.php`, `blog.php`, `maipo-valley-wine-tour-santiago.php`, `discover-santiago-city-tour.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `contact-us.php`, `privacy.php`, `refunds-cancellations.php`

**Interfaces:**
- Consumes: nothing from other tasks — standalone markup edit.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: `index.php`**

Current:
```html
    <footer class="revealed">
        <?php include __DIR__ . '/includes/footer.php'; ?>
    </footer>
```
Change to:
```html
    <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: `blog-post.php`**

Current:
```html
  <footer class="revealed">
    <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 3: `gallery.php`**

Current:
```html
  <footer class="revealed">
    <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 4: `shopping.php`**

Current:
```html
    <footer class="revealed">
        <?php include __DIR__ . '/includes/footer.php'; ?>
    </footer>
```
Change to:
```html
    <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 5: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`**

Current:
```html
  <footer class="revealed">
   <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 6: `cruise-transfer.php`**

Current:
```html
<footer class="revealed">
  <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
</footer>
```
Change to:
```html
<?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
```

- [ ] **Step 7: `blog.php`**

Current:
```html
  <footer class="revealed">
    <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 8: `maipo-valley-wine-tour-santiago.php`**

Current:
```html
  <footer class="revealed">
   <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 9: `discover-santiago-city-tour.php`**

Current:
```html
  <footer class="revealed">
   <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 10: `portillo-inca-lagoon-andes-mountains-vineyard.php`**

Current:
```html
  <footer class="revealed">
   <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 11: `contact-us.php`**

Current:
```html
  <footer>
    <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
  </footer>
```
Change to:
```html
  <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
```

- [ ] **Step 12: `privacy.php`**

Current:
```html
<footer>
  <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
</footer>
```
Change to:
```html
<?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
```

- [ ] **Step 13: `refunds-cancellations.php`**

Current:
```html
<footer>
  <?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
</footer>
```
Change to:
```html
<?php include __DIR__ . '/includes/footer.php'; ?>  <!-- Common footer include -->
```

- [ ] **Step 14: Verify all 13 pages**

```bash
grep -c '<footer' index.php blog-post.php gallery.php shopping.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php blog.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php contact-us.php privacy.php refunds-cancellations.php
```

Expected: `0` for every file — after this fix, none of these 13 page files should contain a literal `<footer` tag in their own source at all (the only `<footer>` comes from `includes/footer.php`'s own content once included/rendered). Also confirm `includes/footer.php` itself is untouched:

```bash
git diff --stat includes/footer.php
```

Expected: no output (file not modified by this task).

```bash
php -S localhost:8765 -t /Users/miguelmontero/Documents/superpowers/STAMP &
sleep 1
curl -s http://localhost:8765/index.php | grep -c "<footer>"
kill %1
```

Expected: `1` (exactly one `<footer>` tag now, from `footer.php` itself).

- [ ] **Step 15: Commit**

```bash
git add index.php blog-post.php gallery.php shopping.php valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php cruise-transfer.php blog.php maipo-valley-wine-tour-santiago.php discover-santiago-city-tour.php portillo-inca-lagoon-andes-mountains-vineyard.php contact-us.php privacy.php refunds-cancellations.php
git commit -m "fix: remove redundant footer wrapper causing duplicate/non-top-level landmark

Every page wrapped its footer.php include in its own <footer> tag,
and footer.php also opens its own <footer> internally - a literal
<footer><footer>...</footer></footer> on all 13 pages, confirmed via
axe-core to trip landmark-no-duplicate-contentinfo,
landmark-contentinfo-is-top-level, and landmark-unique on every page.
class=\"revealed\" on 10 of these had zero CSS/JS behavior anywhere,
discarded along with the wrapper."
```

---

### Task 2: Add accessible names to icon-only links

**Files:**
- Modify: `includes/header.php`
- Modify: `includes/footer.php`

**Interfaces:**
- Consumes: nothing from Task 1 (different files, independent).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: `includes/header.php` — 3 social icon links**

Current:
```html
                            <li><a href="https://www.instagram.com/stampstour/"><i class="bi bi-instagram"></i></a></li>
                            <li><a href="https://www.facebook.com/stampstour"><i class="bi bi-facebook"></i></a></li>
                            <li><a href="https://api.whatsapp.com/send?phone=56923993146"><i class="bi bi-whatsapp"></i></a></li>
```
Change to:
```html
                            <li><a href="https://www.instagram.com/stampstour/" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a></li>
                            <li><a href="https://www.facebook.com/stampstour" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a></li>
                            <li><a href="https://api.whatsapp.com/send?phone=56923993146" aria-label="WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a></li>
```

- [ ] **Step 2: `includes/header.php` — sticky logo link**

Current:
```html
       <a href="/">
        <picture>
         <source srcset="/img/logo_sticky.webp" type="image/webp">
         <img alt="Stamps Tour" class="logo_sticky" height="34" width="147" src="/img/logo_sticky.png"/>
        </picture>
       </a>
```
Change to:
```html
       <a href="/" aria-label="Stamps Tour">
        <picture>
         <source srcset="/img/logo_sticky.webp" type="image/webp">
         <img alt="Stamps Tour" class="logo_sticky" height="34" width="147" src="/img/logo_sticky.png"/>
        </picture>
       </a>
```

(Do not touch the OTHER logo link a few lines above this one — `class="logo_normal"` — it was not flagged by the audit and its `alt` text already works correctly; only this specific `.logo_sticky` link gets the defensive `aria-label`.)

- [ ] **Step 3: `includes/footer.php` — 3 social icon links**

Current:
```html
        <li>
         <a href="https://www.instagram.com/stampstour/">
          <i class="bi bi-instagram"></i>
         </a>
        </li>
        <li>
         <a href="https://api.whatsapp.com/send?phone=56923993146">
          <i class="bi bi-whatsapp"></i>
         </a>
        </li>
        <li>
         <a href="https://www.facebook.com/stampstour">
          <i class="bi bi-facebook"></i>
         </a>
        </li>
```
Change to:
```html
        <li>
         <a href="https://www.instagram.com/stampstour/" aria-label="Instagram">
          <i class="bi bi-instagram" aria-hidden="true"></i>
         </a>
        </li>
        <li>
         <a href="https://api.whatsapp.com/send?phone=56923993146" aria-label="WhatsApp">
          <i class="bi bi-whatsapp" aria-hidden="true"></i>
         </a>
        </li>
        <li>
         <a href="https://www.facebook.com/stampstour" aria-label="Facebook">
          <i class="bi bi-facebook" aria-hidden="true"></i>
         </a>
        </li>
```

- [ ] **Step 4: Verify**

```bash
grep -c 'aria-label' includes/header.php includes/footer.php
```

Expected: `4` for `includes/header.php` (3 social icons + 1 sticky logo), `3` for `includes/footer.php` (3 social icons).

```bash
php -S localhost:8765 -t /Users/miguelmontero/Documents/superpowers/STAMP &
sleep 1
curl -s http://localhost:8765/index.php | grep -o 'aria-label="[^"]*"'
kill %1
```

Expected: `aria-label="Instagram"`, `aria-label="Facebook"`, `aria-label="WhatsApp"`, `aria-label="Stamps Tour"` (header, 4 total) and `aria-label="Instagram"`, `aria-label="WhatsApp"`, `aria-label="Facebook"` (footer, 3 total) — 7 total on a rendered page.

- [ ] **Step 5: Commit**

```bash
git add includes/header.php includes/footer.php
git commit -m "fix: add accessible names to icon-only social links and sticky logo

axe-core flagged 7 link-name violations on every page: the header's 3
social icons, the footer's 3 (duplicate) social icons, and the sticky
header logo link - all icon-only with no discernible text. Added
aria-label to each link and aria-hidden=true on the now-redundant
decorative icon glyphs (confirmed no CSS selector depends on
aria-hidden anywhere in the codebase)."
```

---

### Task 3: Local verification

**Files:** none modified — verification only, unless a real defect is found, in which case fix it in the relevant file from Task 1/2 before marking this task done.

**Interfaces:**
- Consumes: Tasks 1 and 2 together.

- [ ] **Step 1: Re-run the exact axe-core audit locally**

`axe-core` is available at `/Users/miguelmontero/.claude/jobs/60089a79/tmp/a11y-audit/node_modules/axe-core` (already installed this session) — reuse it, or `npm install axe-core` fresh if that path is gone. Using Puppeteer, load `index.php`, `discover-santiago-city-tour.php`, and `contact-us.php` from a local `php -S` server, inject `axe.min.js`, and run `axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'best-practice'] } })` on each. Confirm `landmark-no-duplicate-contentinfo`, `landmark-contentinfo-is-top-level`, `landmark-unique`, and `link-name` all report **zero** violations on all 3 pages. Confirm no new violations appeared that weren't present before this plan's changes.

- [ ] **Step 2: Full 13-page sweep**

Beyond the 3 audited pages, directly verify the other 10 pages this plan touched (`blog-post.php`, `gallery.php`, `shopping.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`, `cruise-transfer.php`, `blog.php`, `maipo-valley-wine-tour-santiago.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `privacy.php`, `refunds-cancellations.php`) each render exactly one `<footer>` tag (`curl ... | grep -c "<footer>"` = 1 for each).

- [ ] **Step 3: Visual regression check**

Screenshot the header and footer on `index.php`, `discover-santiago-city-tour.php`, and `contact-us.php` and confirm no visual change from removing the wrapper `<footer>` tag (it carries no styling of its own) and from adding `aria-label`/`aria-hidden` attributes (neither has any visual effect).

- [ ] **Step 4: Accessibility-tree spot check**

Using CDP's `Accessibility.getFullAXTree` (or Chrome DevTools' Accessibility pane manually), inspect at least one of the header's social icon links and confirm it now reports an accessible name of "Instagram" (not empty) — this cross-checks axe's own reporting against the actual computed accessibility tree, since the two can diverge in edge cases (as already seen with the sticky-logo node during this investigation).

- [ ] **Step 5: Record findings**

If all checks pass, proceed to Task 4. If a real defect is found, fix it in the relevant file from Task 1/2 and re-run the failing check before proceeding.

---

### Task 4: Deploy and confirm production

**Files:** none modified — deployment/verification only.

**Interfaces:**
- Consumes: all commits from Tasks 1-3.

- [ ] **Step 1: Push to `main`**

```bash
git push origin main
```

- [ ] **Step 2: Ask the user to pull and clear the Cloudflare cache on the HostGator production server**

Per this session's established, repeatedly-confirmed lesson: this deploy pipeline has no automatic CDN cache invalidation. Ask the user to both pull and clear the cache, then confirm via `curl` that a live page reflects the new markup (not stale) before proceeding.

- [ ] **Step 3: Re-run the axe-core audit against live production**

Same 3 pages, same audit configuration as Task 3 Step 1, but against `https://stampstour.com/...` URLs. Confirm the same zero-violation result holds in production, not just locally.

- [ ] **Step 4: Production visual spot check**

Screenshot the header/footer on the 3 production pages, confirm no visual regression, matching Task 3 Step 3's local result.
