# Blog Theme Styling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the relative-path bug that makes blog post pages load zero site CSS, and restructure `blog.php`/`blog-post.php` onto the CityTours theme's actual blog markup so `css/blog.css` (already linked) finally styles something.

**Architecture:** Two independent fixes landing as separate commits: (1) a path fix in the shared `includes/head.php`, (2) markup restructuring in `blog.php` and `blog-post.php` to match classes the theme's own `css/blog.css` targets (`.box_style_1`, `.post`, `.post_info`, `.post-left`, `.btn_1`, icon-font classes already defined in `css/vendors.css`). No new files, no database changes, no JS changes.

**Tech Stack:** PHP (no framework), CityTours v6.8 theme CSS/icon-font already present in `css/`. Local verification uses the already-configured local MySQL database (`stampst1_stamptour`, credentials in `db_config.php` one directory above the site root) and PHP's built-in server.

## Global Constraints

- Reference markup for the theme's actual blog classes was pulled directly from the theme vendor's own demo pages (`ansonika.com/citytours/blog.html` and `blog_post.html`), which load this exact `css/blog.css` file — use the class names and structure below verbatim, don't improvise different ones.
- No categories, tags, comments, or sidebar in this project — those are a separate follow-up spec. The `.post_info` line shows only the publish date.
- Column width for both pages: `col-lg-9` (not full container width), so a sidebar can be added later without resizing the card column.
- After every edit, run `php -l <file>` and confirm `No syntax errors detected`.
- Working directory for all commands: `/Users/miguelmontero/Documents/superpowers/STAMP`.
- Local dev server/DB are already set up from earlier work this session: `php -S localhost:8000` from the site root, backed by the local `stampst1_stamptour` MySQL database (already has one real published post: slug `best-time-to-visit-maipo-valley-wine-country`).

---

### Task 1: Fix `includes/head.php` relative asset paths

**Files:**
- Modify: `includes/head.php:67-81`

**Interfaces:** None — static `<head>` markup only. This is a prerequisite for Tasks 2-3 being visible at all on the real `/blog` and `/blog/<slug>` URLs.

- [ ] **Step 1: Edit the favicon/font/CSS block**

Current (lines 67-81):
```php
<!-- Favicons-->
<link href="img/favicon.ico" rel="shortcut icon" type="image/x-icon"/>
<link href="img/apple-touch-icon-57x57-precomposed.png" rel="apple-touch-icon" type="image/x-icon"/>
<link href="img/apple-touch-icon-72x72-precomposed.png" rel="apple-touch-icon" sizes="72x72" type="image/x-icon"/>
<link href="img/apple-touch-icon-114x114-precomposed.png" rel="apple-touch-icon" sizes="114x114" type="image/x-icon"/>
<link href="img/apple-touch-icon-144x144-precomposed.png" rel="apple-touch-icon" sizes="144x144" type="image/x-icon"/>
<!-- GOOGLE WEB FONT (self-hosted) -->
<link rel="preconnect" href="https://cdn.openwidget.com">
<link href="fonts/fonts.css" rel="stylesheet"/>
<!-- COMMON CSS -->
<link href="css/bootstrap.min.css" rel="stylesheet"/>
<link href="css/style.css" rel="stylesheet"/>
<link href="css/vendors.css" rel="stylesheet"/>
<!-- CUSTOM CSS -->
<link href="css/custom.css" rel="stylesheet"/>
```

Change to (add a leading `/` to every `img/`, `fonts/`, and `css/` path — leave the two `https://` lines untouched):
```php
<!-- Favicons-->
<link href="/img/favicon.ico" rel="shortcut icon" type="image/x-icon"/>
<link href="/img/apple-touch-icon-57x57-precomposed.png" rel="apple-touch-icon" type="image/x-icon"/>
<link href="/img/apple-touch-icon-72x72-precomposed.png" rel="apple-touch-icon" sizes="72x72" type="image/x-icon"/>
<link href="/img/apple-touch-icon-114x114-precomposed.png" rel="apple-touch-icon" sizes="114x114" type="image/x-icon"/>
<link href="/img/apple-touch-icon-144x144-precomposed.png" rel="apple-touch-icon" sizes="144x144" type="image/x-icon"/>
<!-- GOOGLE WEB FONT (self-hosted) -->
<link rel="preconnect" href="https://cdn.openwidget.com">
<link href="/fonts/fonts.css" rel="stylesheet"/>
<!-- COMMON CSS -->
<link href="/css/bootstrap.min.css" rel="stylesheet"/>
<link href="/css/style.css" rel="stylesheet"/>
<link href="/css/vendors.css" rel="stylesheet"/>
<!-- CUSTOM CSS -->
<link href="/css/custom.css" rel="stylesheet"/>
```

- [ ] **Step 2: Lint**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l includes/head.php
```

Expected: `No syntax errors detected in includes/head.php`.

- [ ] **Step 3: Verify no relative asset paths remain in this block**

```bash
grep -n 'href="img/\|href="fonts/\|href="css/' includes/head.php
```

Expected: no output (all now have a leading `/`).

- [ ] **Step 4: Confirm the homepage still renders correctly with absolute paths**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8000 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s http://localhost:8000/ | grep -o 'href="/css/style.css"'
pkill -f "php -S localhost:8000"
```

Expected: `href="/css/style.css"` printed once (confirms the homepage — served at its filesystem-matching URL — still resolves the stylesheet correctly with the new absolute path).

- [ ] **Step 5: Commit**

```bash
git add includes/head.php
git commit -m "Fix includes/head.php to use root-absolute asset paths

Relative paths (img/..., fonts/..., css/...) resolve against the
request URL, not the filesystem location. Pages reached via a
rewritten URL (blog posts at /blog/<slug>, the listing at /blog/)
were resolving these against the wrong base and loading zero site
CSS. Root-absolute paths resolve identically on pages already served
at their filesystem-matching URL, so this is a pure bugfix."
```

---

### Task 2: Restructure `blog.php` onto the theme's blog-listing markup

**Files:**
- Modify: `blog.php:38-63`

**Interfaces:**
- Consumes: `includes/head.php`'s corrected absolute paths (Task 1) — this task's visual result isn't verifiable without Task 1 already landed.
- Produces: nothing consumed by later tasks in this plan; Task 4 verifies this task's output.

- [ ] **Step 1: Replace the post-listing markup**

Current (lines 38-63, the `<main>` block):
```php
  <main>
    <div class="container margin_60">
      <?php if (empty($posts)): ?>
        <p>No posts published yet &mdash; check back soon!</p>
      <?php else: ?>
        <div class="row">
          <?php foreach ($posts as $post): $href = '/blog/' . rawurlencode($post['slug']); ?>
            <div class="col-md-4 margin_30">
              <div class="blog_post_card">
                <?php if (!empty($post['featured_image'])): ?>
                  <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid" loading="lazy">
                  </a>
                <?php endif; ?>
                <h3><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h3>
                <p class="text-muted small"><?= date('F j, Y', strtotime($post['published_at'])) ?></p>
                <?php if (!empty($post['excerpt'])): ?>
                  <p><?= htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="btn_1">Read more</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
```

Change to:
```php
  <main>
    <div class="container margin_60">
      <?php if (empty($posts)): ?>
        <p>No posts published yet &mdash; check back soon!</p>
      <?php else: ?>
        <div class="row">
          <div class="col-lg-9">
            <div class="box_style_1">
              <?php foreach ($posts as $i => $post): $href = '/blog/' . rawurlencode($post['slug']); ?>
                <div class="post">
                  <?php if (!empty($post['featured_image'])): ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                      <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid" loading="lazy">
                    </a>
                  <?php endif; ?>
                  <div class="post_info clearfix">
                    <div class="post-left">
                      <ul>
                        <li><i class="icon-calendar-empty"></i> On <span><?= date('j M Y', strtotime($post['published_at'])) ?></span></li>
                      </ul>
                    </div>
                  </div>
                  <h2><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
                  <?php if (!empty($post['excerpt'])): ?>
                    <p><?= htmlspecialchars($post['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
                  <?php endif; ?>
                  <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="btn_1" title="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">Read more</a>
                </div>
                <!-- end post -->
                <?php if ($i < count($posts) - 1): ?><hr><?php endif; ?>
              <?php endforeach; ?>
            </div>
            <!-- end box_style_1 -->
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
```

Note: `$i` comes from `foreach ($posts as $i => $post)` — `$posts` is a plain numerically-indexed array from `fetch_all(MYSQLI_ASSOC)` further up in the file (unchanged), so `$i` runs `0, 1, 2, ...` in the same order as `$posts`, making `$i < count($posts) - 1` correctly skip the `<hr>` after the last post.

- [ ] **Step 2: Lint**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l blog.php
```

Expected: `No syntax errors detected in blog.php`.

- [ ] **Step 3: Verify the new classes are present and the old ones are gone**

```bash
grep -c 'box_style_1\|post_info\|post-left' blog.php
grep -c 'blog_post_card' blog.php
```

Expected: first command outputs `3` or more (all three new classes present), second outputs `0` (old class fully removed).

- [ ] **Step 4: Commit**

```bash
git add blog.php
git commit -m "Restructure blog.php onto the CityTours theme's blog-listing markup

Replaces the ad-hoc blog_post_card grid (which had no matching rules
in css/blog.css) with the theme's actual .box_style_1/.post/.post_info
structure, pulled from the theme vendor's own blog.html demo page."
```

---

### Task 3: Restructure `blog-post.php` onto the theme's single-post markup

**Files:**
- Modify: `blog-post.php:61-77`

**Interfaces:**
- Consumes: `includes/head.php`'s corrected absolute paths (Task 1).
- Produces: nothing consumed by later tasks; Task 4 verifies this task's output.

- [ ] **Step 1: Replace the single-post markup**

Current (lines 61-77, inside the `<?php else: ?>` branch of the `<main>` block):
```php
        <div class="row">
          <div class="col-lg-8">
            <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted"><?= date('F j, Y', strtotime($post['published_at'])) ?></p>
            <?php if (!empty($post['featured_image'])): ?>
              <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid margin_30">
            <?php endif; ?>
            <div class="blog_post_content">
              <?= $post['content'] ?>
            </div>
            <hr>
            <p><a href="/blog" class="btn_1">&larr; Back to the blog</a></p>
          </div>
        </div>
```

Change to:
```php
        <div class="row">
          <div class="col-lg-9">
            <div class="box_style_1">
              <div class="post nopadding">
                <?php if (!empty($post['featured_image'])): ?>
                  <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid">
                <?php endif; ?>
                <div class="post_info clearfix">
                  <div class="post-left">
                    <ul>
                      <li><i class="icon-calendar-empty"></i> On <span><?= date('j M Y', strtotime($post['published_at'])) ?></span></li>
                    </ul>
                  </div>
                </div>
                <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="blog_post_content">
                  <?= $post['content'] ?>
                </div>
              </div>
              <!-- end post -->
            </div>
            <!-- end box_style_1 -->
            <p class="margin_30"><a href="/blog" class="btn_1">&larr; Back to the blog</a></p>
          </div>
        </div>
```

Note: `<h1>` is kept (not the theme demo's `<h2>`) since this is the page's single main heading — better semantics/SEO than matching the demo's heading level exactly. The `.blog_post_content` wrapper around `$post['content']` is preserved as-is, just relocated inside the new structure.

- [ ] **Step 2: Lint**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l blog-post.php
```

Expected: `No syntax errors detected in blog-post.php`.

- [ ] **Step 3: Verify the new classes are present**

```bash
grep -c 'box_style_1\|post_info\|nopadding' blog-post.php
```

Expected: `3` or more.

- [ ] **Step 4: Commit**

```bash
git add blog-post.php
git commit -m "Restructure blog-post.php onto the CityTours theme's single-post markup

Same rationale as blog.php: adopts .box_style_1/.post.nopadding/
.post_info from the theme's blog_post.html demo page instead of the
previous ad-hoc markup that css/blog.css didn't style."
```

---

### Task 4: Visual verification

**Files:** None (verification only).

- [ ] **Step 1: Start the local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8000 > /tmp/php-server.log 2>&1 &
sleep 1
```

- [ ] **Step 2: Confirm both pages return 200 and load blog.css correctly**

```bash
curl -s -o /dev/null -w "blog.php -> HTTP %{http_code}\n" http://localhost:8000/blog.php
curl -s -o /dev/null -w "blog-post.php -> HTTP %{http_code}\n" "http://localhost:8000/blog-post.php?slug=best-time-to-visit-maipo-valley-wine-country"
curl -s http://localhost:8000/blog.php | grep -o 'href="/css/blog.css"'
```

Expected: both `HTTP 200`, and the grep prints `href="/css/blog.css"` once (confirms the absolute path from the earlier fix round is intact and the page is reachable).

- [ ] **Step 3: Screenshot both pages for a real visual check**

If Playwright with Chromium is available (it was installed to `/tmp/node_modules` earlier this session — check with `ls /tmp/node_modules/.bin/playwright`; if missing, run `cd /tmp && npm install playwright --silent && npx playwright install chromium`), use it:

```bash
cd /tmp
cat > screenshot-blog.js << 'EOF'
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto('http://localhost:8000/blog.php', { waitUntil: 'networkidle' });
  await page.screenshot({ path: '/tmp/blog-listing-v2.png', fullPage: true });
  await page.goto('http://localhost:8000/blog-post.php?slug=best-time-to-visit-maipo-valley-wine-country', { waitUntil: 'networkidle' });
  await page.screenshot({ path: '/tmp/blog-post-v2.png', fullPage: true });
  await browser.close();
})();
EOF
node screenshot-blog.js
```

Then view `/tmp/blog-listing-v2.png` and `/tmp/blog-post-v2.png`. Confirm: the post card has a visible box-shadow (`.box_style_1`), the date line shows a calendar icon (not a missing-glyph box), the title renders as a styled heading, and the "Read more"/"Back to the blog" buttons show the theme's teal `btn_1` button style — not plain unstyled browser defaults.

- [ ] **Step 4: Spot-check the homepage and one tour page weren't affected by the Task 1 path change**

```bash
curl -s -o /dev/null -w "homepage -> HTTP %{http_code}\n" http://localhost:8000/
curl -s -o /dev/null -w "tour page -> HTTP %{http_code}\n" http://localhost:8000/maipo-valley-wine-tour-santiago.php
curl -s http://localhost:8000/ | grep -o 'href="/css/style.css"'
```

Expected: both `HTTP 200`, and the stylesheet path check prints once.

- [ ] **Step 5: Stop the local server**

```bash
pkill -f "php -S localhost:8000"
```

- [ ] **Step 6: No commit needed** (verification-only task, nothing to add).
