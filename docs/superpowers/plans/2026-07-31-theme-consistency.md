# Theme Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up the CityTours theme's blog stylesheet (currently unused) and bring all 9 admin pages onto the same CSS stack `admin.php` already uses, so the blog and admin section both render with consistent theme styling.

**Architecture:** Pure `<link>` tag insertions into existing `<head>` sections — no CSS is written, no markup is restructured, no PHP logic changes. Each file keeps its own existing quote/indentation style; only new lines are added.

**Tech Stack:** PHP (no framework), CityTours v6.8 Bootstrap theme CSS files already present in `css/`.

## Global Constraints

- No new CSS files are created — only linking to CSS files that already exist in `css/`.
- Admin pages use absolute paths (`/css/...`) since they live in the `admin/` subdirectory; `admin.php` itself (at the site root) uses relative paths (`css/...`) — do not copy admin.php's relative-path style into files under `admin/`.
- Preserve each file's existing quote style (`rel="stylesheet">` vs `rel="stylesheet"/>`) and indentation for new lines — match the line immediately above/around the insertion point in that same file.
- `admin/blog-edit.php`'s existing Quill editor stylesheet link (`https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css`) must remain untouched.
- After every edit, run `php -l <file>` and confirm `No syntax errors detected`.
- Working directory for all commands: `/Users/miguelmontero/Documents/superpowers/STAMP`.

---

### Task 1: Link `css/blog.css` on the blog pages

**Files:**
- Modify: `blog.php:24`
- Modify: `blog-post.php:45`

**Interfaces:** None — static `<head>` markup only.

- [ ] **Step 1: Edit `blog.php`**

Current (lines 22-25):
```php
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
</head>
```

Change to:
```php
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<link href="css/blog.css" rel="stylesheet"/>
</head>
```

- [ ] **Step 2: Edit `blog-post.php`**

Current (lines 43-46):
```php
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
</head>
```

Change to:
```php
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<link href="css/blog.css" rel="stylesheet"/>
</head>
```

- [ ] **Step 3: Lint both files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l blog.php
php -l blog-post.php
```

Expected: `No syntax errors detected in ...` for both.

- [ ] **Step 4: Verify**

```bash
grep -n 'blog.css' blog.php blog-post.php
```

Expected: one match per file.

- [ ] **Step 5: Commit**

```bash
git add blog.php blog-post.php
git commit -m "Link css/blog.css on blog pages

The CityTours theme ships a dedicated blog.css (card shadows, tag
pills) that was never linked anywhere, so the blog rendered without
its matching theme styling."
```

---

### Task 2: Bring all 9 admin pages onto the full theme CSS stack

**Files:**
- Modify: `admin/dashboard.php:918`
- Modify: `admin/blog.php:20`
- Modify: `admin/blog-edit.php:126`
- Modify: `admin/check.php:1641`
- Modify: `admin/closing.php:3167`
- Modify: `admin/consolidate-day.php:1220`
- Modify: `admin/consolidate-month.php:1009`
- Modify: `admin/private-booking.php:42`
- Modify: `admin/preferentials.php:49`

**Interfaces:** None — static `<head>` markup only.

**Reference (already correct, do not modify):** `admin.php` loads, in this order: `bootstrap.min.css`, `style.css`, `vendors.css`, `admin.css`, `custom.css`. All edits below match that order, using absolute paths since these files live under `admin/`.

- [ ] **Step 1: Edit `admin/dashboard.php`**

Current (line 918):
```php
    <link href="/css/bootstrap.min.css" rel="stylesheet">
```

Change to:
```php
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/vendors.css" rel="stylesheet">
    <link href="/css/admin.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
```

- [ ] **Step 2: Edit `admin/blog.php`**

Current (line 20):
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
```

Change to:
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
```

- [ ] **Step 3: Edit `admin/blog-edit.php`**

Current (lines 126-127):
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
```

Change to (theme stack added after bootstrap, Quill link left exactly where it was):
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
```

- [ ] **Step 4: Edit `admin/check.php`**

Current (line 1641):
```php
    <link href="/css/bootstrap.min.css" rel="stylesheet">
```

Change to:
```php
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/vendors.css" rel="stylesheet">
    <link href="/css/admin.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
```

- [ ] **Step 5: Edit `admin/closing.php`**

Current (line 3167):
```php
    <link href="/css/bootstrap.min.css" rel="stylesheet">
```

Change to:
```php
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/vendors.css" rel="stylesheet">
    <link href="/css/admin.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
```

- [ ] **Step 6: Edit `admin/consolidate-day.php`**

Current (line 1220):
```php
<link href="/css/bootstrap.min.css" rel="stylesheet">
```

Change to:
```php
<link href="/css/bootstrap.min.css" rel="stylesheet">
<link href="/css/style.css" rel="stylesheet">
<link href="/css/vendors.css" rel="stylesheet">
<link href="/css/admin.css" rel="stylesheet">
<link href="/css/custom.css" rel="stylesheet">
```

- [ ] **Step 7: Edit `admin/consolidate-month.php`**

Current (line 1009):
```php
<link href="/css/bootstrap.min.css" rel="stylesheet">
```

Change to:
```php
<link href="/css/bootstrap.min.css" rel="stylesheet">
<link href="/css/style.css" rel="stylesheet">
<link href="/css/vendors.css" rel="stylesheet">
<link href="/css/admin.css" rel="stylesheet">
<link href="/css/custom.css" rel="stylesheet">
```

- [ ] **Step 8: Edit `admin/private-booking.php`**

Current (line 42):
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
```

Change to:
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
```

- [ ] **Step 9: Edit `admin/preferentials.php`**

This file already has `bootstrap.min.css`, `style.css`, `vendors.css`, `custom.css` (lines 46-49) — only `admin.css` is missing. Current (lines 46-49):
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
```

Change to (insert `admin.css` before `custom.css`, matching admin.php's order):
```php
  <link href="/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/css/style.css" rel="stylesheet"/>
  <link href="/css/vendors.css" rel="stylesheet"/>
  <link href="/css/admin.css" rel="stylesheet"/>
  <link href="/css/custom.css" rel="stylesheet"/>
```

- [ ] **Step 10: Lint all 9 files**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -l admin/dashboard.php
php -l admin/blog.php
php -l admin/blog-edit.php
php -l admin/check.php
php -l admin/closing.php
php -l admin/consolidate-day.php
php -l admin/consolidate-month.php
php -l admin/private-booking.php
php -l admin/preferentials.php
```

Expected: `No syntax errors detected in ...` for all 9.

- [ ] **Step 11: Verify every file now has the full stack**

```bash
for f in admin/dashboard.php admin/blog.php admin/blog-edit.php admin/check.php admin/closing.php admin/consolidate-day.php admin/consolidate-month.php admin/private-booking.php admin/preferentials.php; do
  echo "=== $f ==="
  grep -o '/css/[a-z.]*\.css' "$f"
done
```

Expected: every file lists `/css/bootstrap.min.css`, `/css/style.css`, `/css/vendors.css`, `/css/admin.css`, `/css/custom.css` (5 lines each).

- [ ] **Step 12: Confirm the Quill link survived untouched in blog-edit.php**

```bash
grep -n "quill.snow.css" admin/blog-edit.php
```

Expected: one match, unchanged from before.

- [ ] **Step 13: Commit**

```bash
git add admin/dashboard.php admin/blog.php admin/blog-edit.php admin/check.php admin/closing.php admin/consolidate-day.php admin/consolidate-month.php admin/private-booking.php admin/preferentials.php
git commit -m "Bring all admin pages onto the same CSS stack as admin.php

8 of 9 admin pages (all but admin.php and, partially,
admin/preferentials.php) loaded only bare bootstrap.min.css despite
sharing a common nav (admin/_nav.php) with the fully-themed pages -
a visibly inconsistent admin section. All 9 now load the same
bootstrap.min.css + style.css + vendors.css + admin.css + custom.css
stack admin.php already used."
```

---

### Task 3: Visual verification

**Files:** None (verification only).

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8000 > /tmp/php-server.log 2>&1 &
sleep 1
```

- [ ] **Step 2: Confirm every edited page still returns 200**

```bash
for p in blog.php admin/dashboard.php admin/blog.php admin/blog-edit.php admin/check.php admin/closing.php admin/consolidate-day.php admin/consolidate-month.php admin/private-booking.php admin/preferentials.php; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000/$p")
  echo "$p -> HTTP $code"
done
```

Expected: 200 for `blog.php`. The `admin/*.php` pages may redirect (302) to `login.php` if `admin/_auth.php` requires a session — that's expected without being logged in, not a failure of this change. If any returns 500, investigate before proceeding (a 500 here would mean a PHP error, not a missing-page issue, since `php -l` already passed on all of them).

- [ ] **Step 3: Visual check in a browser**

Open `http://localhost:8000/blog.php` and one `admin/*.php` page (login first via `http://localhost:8000/login.php` if the admin pages redirect) and confirm the styling now looks consistent with the rest of the theme — no layout breakage, tables/buttons/forms picking up the theme's look.

- [ ] **Step 4: Stop the local server**

```bash
pkill -f "php -S localhost:8000"
```

- [ ] **Step 5: No commit needed** (verification-only task, nothing to add).
