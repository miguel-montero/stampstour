# Blog Theme Styling (Sub-project 1 of 2)

## Problem

Two issues, discovered while wiring up the CityTours theme's `css/blog.css` to the blog pages:

1. **`includes/head.php` uses relative asset paths everywhere** (favicon, apple-touch-icons, `fonts/fonts.css`, `bootstrap.min.css`, `style.css`, `vendors.css`, `custom.css`). This file is included on every page. It works fine when a page is served at a URL matching its filesystem location (e.g. `/index.php`), but blog posts are served at `/blog/<slug>` via an `.htaccess` rewrite (`blog-post.php` is never reached directly), and the blog listing is also reachable at `/blog/`. Relative paths on those URLs resolve against the wrong base (e.g. `/blog/css/style.css` instead of `/css/style.css`), so **every blog post page currently loads zero site CSS**, not just the missing blog styling.
2. **`css/blog.css`'s selectors don't match the blog's current markup.** `blog.php` and `blog-post.php` use ad-hoc classes (`blog_post_card`, `blog_post_content`) that don't correspond to anything in `blog.css`, which targets the CityTours theme's actual blog classes (`.box_style_1`, `.post`, `.post_info`, `.post-left`/`.post-right`, `.tags`, etc. — confirmed by fetching the theme's own demo pages at `ansonika.com/citytours/blog.html` and `blog_post.html`, which load this exact `css/blog.css` file). So even with the path bug fixed, linking the stylesheet today changes nothing visually.

## Scope

This is sub-project 1 of 2 for adopting the CityTours theme's blog design. This project: fix the path bug, and restructure `blog.php`/`blog-post.php` markup to match the theme's actual blog classes, using only data the site already has (title, excerpt, featured image, published date). No database changes.

Sub-project 2 (separate, later spec): categories, tags, and the sidebar (search excluded per decision — recent posts/categories/tags widgets only), which requires new database tables and admin UI changes.

Explicitly out of scope for both sub-projects: comments (no moderation system exists; a separate future decision).

## Changes

### 1. Fix `includes/head.php` relative paths

Change these 8 lines from relative to root-absolute paths (add a leading `/`):

```
img/favicon.ico
img/apple-touch-icon-57x57-precomposed.png
img/apple-touch-icon-72x72-precomposed.png
img/apple-touch-icon-114x114-precomposed.png
img/apple-touch-icon-144x144-precomposed.png
fonts/fonts.css
css/bootstrap.min.css
css/style.css
css/vendors.css
css/custom.css
```

This fixes CSS/asset loading for every page reachable via a rewritten URL — currently that's the blog, but it protects any future rewritten route too.

### 2. Restructure `blog.php` (listing) to the theme's actual markup

Replace the current `.blog_post_card` grid with the theme's `.box_style_1` structure (verified against `ansonika.com/citytours/blog.html`, which loads this same `blog.css`): one `.box_style_1` wraps all posts; each post is a `.post` div with the featured image, a `.post_info` line (date only — no category/tags/comment-count, since that data doesn't exist yet), an `<h2>` title, the excerpt, and a `.btn_1` "Read more" link; posts are separated by `<hr>`. Column width: `col-lg-9` (not full width), so sub-project 2 can add a `col-lg-3` sidebar later without resizing the card column.

### 3. Restructure `blog-post.php` (single post) to the theme's actual markup

`.box_style_1 > .post.nopadding`: full-bleed featured image, a `.post_info` line (date only), `<h1>` title (kept as `h1`, not the theme demo's `h2`, for correct page heading semantics/SEO), then the post content, then close the card. The "Back to blog" link stays outside the card, as it is today. Same `col-lg-9` width as the listing.

## Testing

- Visual check (local server + local DB, already set up and confirmed working this session): `/blog.php` and `/blog-post.php?slug=<existing-post>` render with the theme's card styling — box shadow, spacing, icon-prefixed date line, styled "Read more" button.
- Confirm the fix actually addresses the root cause: check the rendered HTML's asset `<link>`/`<img>` paths are root-absolute, and separately verify via curl or the rewritten route (`/blog/<slug>` pattern, not just `blog-post.php` directly) that CSS actually loads — this is what the previous attempt's task-level verification missed.
- `php -l` on every edited file.
- Confirm no other page's rendering changed (the `head.php` fix touches every page, but root-absolute paths should resolve identically to the existing relative paths on every page that's *already* served at its filesystem-matching URL — spot check the homepage and one tour page).
