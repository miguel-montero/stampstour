# Theme Consistency: Blog Styling + Admin Panel

## Problem

The site is built on the CityTours v6.8 Bootstrap theme (`css/style.css` header: `Theme Name: CITYTOURS v.6.8`, Ansonika/ThemeForest). Two parts of the site aren't using theme assets that are already present in the codebase, or aren't using them consistently:

- **Blog:** `css/blog.css` (160 lines — card shadows, tag pills, and other theme-provided blog styling) exists in the repo but isn't linked from `blog.php` or `blog-post.php`. The blog currently renders without this styling.
- **Admin panel:** 9 admin pages share a common nav via `admin/_nav.php`'s `stamp_admin_nav()`, but only `admin.php` and `admin/preferentials.php` load the full theme CSS stack. The other 8 admin pages load only bare `bootstrap.min.css`, so their content (tables, forms, buttons) renders with generic Bootstrap styling instead of the theme's look — a visibly inconsistent admin section.

## Scope

Two small, purely-additive changes: add missing `<link>` tags. No markup restructuring, no new CSS written, no behavior changes. `admin/_nav.php`'s nav bar itself already renders correctly everywhere (it only uses plain Bootstrap classes) — this only affects how each page's own content looks.

Explicitly out of scope (per earlier discussion): swapping the custom cookie consent banner (`includes/cookie-banner.php`, wired into Google Consent Mode v2) for the theme's generic `js/jquery.cookiebar.js` plugin — the custom banner is more capable and not being replaced.

## Changes

### 1. Blog styling

Add `<link href="css/blog.css" rel="stylesheet">` directly in `blog.php` and `blog-post.php`, immediately after their existing `include __DIR__ . '/includes/head.php';` line. Not added to `includes/head.php` itself, since that file is shared site-wide (homepage, tour pages, etc.) and `blog.css` is only relevant to the two blog pages.

### 2. Admin panel CSS consistency

Bring these 8 pages up to the same CSS stack `admin.php` already uses — `bootstrap.min.css`, `style.css`, `vendors.css`, `custom.css`, `admin.css` (in that order, matching `admin.php`'s existing order):

- `admin/dashboard.php`
- `admin/blog.php`
- `admin/blog-edit.php` (keep its existing Quill editor stylesheet link untouched, add the theme stack alongside it)
- `admin/check.php`
- `admin/closing.php`
- `admin/consolidate-day.php`
- `admin/consolidate-month.php`
- `admin/private-booking.php`

`admin/preferentials.php` already has `bootstrap.min.css` + `style.css` + `vendors.css` + `custom.css` — add only the missing `admin.css` line there.

## Testing

- Visual check: load each of the 9 admin pages (10 counting `admin.php` itself as the reference) and confirm tables/forms/buttons now share a consistent look, and nothing renders broken (no layout collisions from the newly-loaded CSS).
- Visual check: load `/blog.php` and a `/blog/<slug>` post, confirm card shadows/tag pill styling from `blog.css` now appears and nothing looks broken.
- `php -l` on every edited `.php` file.
