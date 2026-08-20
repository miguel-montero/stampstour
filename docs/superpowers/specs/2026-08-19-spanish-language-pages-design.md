# Spanish-language pages

## Context

Three of the four SEO research reports in `/seo research/` independently flag the same gap: stampstour.com has zero Spanish-language content and zero organic visibility for Chile-market Spanish search queries, despite the business operating entirely in Santiago, Chile. This is now the largest remaining item in the SEO backlog worked through this session (server-side pricing, FAQ + `FAQPage`/`Product`/`TravelAgency` schema, exact-phrase heading fixes, and the cruise-transfer copy rework are already shipped).

This spec covers adding a Spanish (`es`) version of every page in scope, keeping English as the default/primary language. It does not implement anything - see `writing-plans` for the follow-up implementation plan.

## Goals

- A `/es/...` URL for the homepage and all 5 tour/transfer pages, functionally and structurally identical to their English counterparts, with fully translated content.
- Proper `hreflang` annotations so Google treats the `en`/`es` pairs as translations of each other, not duplicate content.
- A mechanism that lets future structural changes (new FAQ question, new schema field, itinerary edit) be made once in shared PHP logic and apply to both languages - not two parallel codebases that drift.
- A `locale` on `blog_posts` so new Spanish blog content (e.g. the "Tour de Viñedos desde Santiago" opportunity the keyword research flagged) can be authored directly, without needing to translate every existing English post.
- A visible language switcher in the header/nav.

## Non-goals

- Translating `contact-us.php`, `privacy.php`, `refunds-cancellations.php`, or `gallery.php`. These are legal/utility pages, not sales-driving content the research flagged - a natural follow-up, not part of this scope.
- Translating existing English blog posts. New Spanish posts get authored directly; back-translating the archive is a separate, later decision.
- Any language beyond English/Spanish.
- Automatic/machine translation at request time. All Spanish copy is written once and stored as static translation arrays (see Design), not generated live.
- Changing `cruise-transfer.php`'s URL to a clean slug (it's currently the only one of the 6 pages still served at `.php` directly). Out of scope here - the Spanish version mirrors whatever the English URL convention is at build time, not fix it.

## Design

### 1. URL structure and routing

`/es/` subdirectory prefix, not a subdomain (`es.stampstour.com`) or ccTLD (`stampstour.cl`) - subdirectories keep every page's SEO authority consolidated under the one domain that's already been the focus of this session's work.

New `.htaccess` rewrite rules, added alongside the existing clean-URL rules (same file, same pattern already used for `blog-post.php?slug=$1`):

```apache
RewriteRule ^es/?$ index.php?lang=es [L,QSA]
RewriteRule ^es/maipo-valley-wine-tour-santiago/?$ maipo-valley-wine-tour-santiago.php?lang=es [L]
RewriteRule ^es/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca/?$ valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php?lang=es [L]
RewriteRule ^es/discover-santiago-city-tour/?$ discover-santiago-city-tour.php?lang=es [L]
RewriteRule ^es/portillo-inca-lagoon-andes-mountains-vineyard/?$ portillo-inca-lagoon-andes-mountains-vineyard.php?lang=es [L]
RewriteRule ^es/cruise-transfer\.php/?$ cruise-transfer.php?lang=es [L]
RewriteRule ^es/blog/?$ blog.php?lang=es [L]
RewriteRule ^es/blog/([a-zA-Z0-9-]+)/?$ blog-post.php?slug=$1&lang=es [L,QSA]
```

No new PHP files. Each existing page file becomes bilingual via `$lang`:

```php
$lang = (($_GET['lang'] ?? 'en') === 'es') ? 'es' : 'en';
```

This is the key architectural decision: **one template per page, not a duplicated `/es/` file tree.** All of today's work (the FAQ renderer, `Product`/`TravelAgency` schema, server-side price lookups, the timeline markup) is shared code that both languages run through unchanged. Only the text content differs per language. The alternative (duplicate PHP files under `/es/`) would mean every future structural change has to be made twice and re-tested twice, forever - rejected for that reason.

### 2. Translation content

A locale array per page, loaded based on `$lang`:

```php
require __DIR__ . "/includes/i18n/{$lang}/maipo.php"; // defines $t
```

Each `includes/i18n/{en,es}/{page}.php` returns an associative array covering every text node currently hardcoded in that page's HTML: `hero_h1`, `meta_title`, `meta_description`, `overview_intro`, `what_to_expect` (array of paragraphs), `whats_included` / `whats_not_included` (arrays), `departure_return` (start/pickup/end text), `additional_info` (array), `cancellation_policy` (intro + array), `itinerary` (array of `{title, duration, description}` per stop), and `faq` (array of `{q, a}` - this one just needs a Spanish version of the array structure that already exists from today's work, no restructuring needed).

The `en` array is the **existing English content, moved verbatim out of the page body and into `includes/i18n/en/{page}.php`** - a mechanical extraction, not a rewrite. The `es` array is new, translated content.

This is the single largest piece of actual work in this project: every hardcoded paragraph, list item, and itinerary stop across 6 page templates (5 tours + cruise; homepage is smaller) needs to become a keyed array lookup instead of raw HTML text, and every one of those needs a real Spanish translation. `$tour_faqs`, `$page_title`, `$page_description`, and the `name`/`description` fields already fed into `render_tour_product_schema()` are already variables from today's work, so those slot into this scheme with no restructuring - only new Spanish content needs writing for them.

**Translation quality:** this is customer-facing sales copy in a market where the business actually operates (Santiago, Chile) - a first draft can be produced quickly, but it should get a native-Spanish-speaker review pass before shipping, not go live as an unreviewed machine/AI draft.

### 3. hreflang

Added to `includes/head.php`, alongside the existing `$page_canonical` requirement. Each page declares its own English and Spanish URLs explicitly (not derived from a single slug formula) - `$page_canonical` already exists per page; add one sibling:

```php
// caller sets, e.g.:
$page_canonical    = 'https://stampstour.com/maipo-valley-wine-tour-santiago';
$page_canonical_es = 'https://stampstour.com/es/maipo-valley-wine-tour-santiago';
```
```html
<link rel="alternate" hreflang="en" href="<?= $page_canonical ?>">
<link rel="alternate" hreflang="es" href="<?= $page_canonical_es ?>">
<link rel="alternate" hreflang="x-default" href="<?= $page_canonical ?>">
```

`x-default` points at English (the site's current default/primary audience). Explicit per-page pairs, not a shared slug-to-URL formula, deliberately - `cruise-transfer.php` is the one page without a clean-URL rewrite (see section 1), so its Spanish canonical is `https://stampstour.com/es/cruise-transfer.php`, not the clean-slug pattern the other 5 pages use. A single formula would silently produce a broken URL for that one page.

### 4. Nav, header, footer, language switcher

`includes/header.php` (nav labels: Home, Tours submenu, Gallery, Blog, Contact us, "Menu mobile") and `includes/footer.php` (Need help?/About-Legal/Discover headers, link labels) get their own small `$t` array, following the same `includes/i18n/{lang}/nav.php` / `includes/i18n/{lang}/footer.php` pattern - much smaller than the per-tour-page arrays.

A language switcher (e.g. "EN | ES" or flag-style toggle) goes in `header.php`'s top line, linking to the *current page's* equivalent in the other language - not just the homepage. This means `header.php` needs `$page_canonical`/`$page_canonical_es` (already set per page for hreflang, see section 3) and `$lang` passed in, so it can link to whichever of the pair isn't the current language.

### 5. Blog

`blog_posts` gets a new column:

```sql
ALTER TABLE blog_posts ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'en' AFTER slug;
```

`blog.php` and `blog-post.php` filter by `locale` (from `$_GET['lang']`, same as the tour pages). New Spanish posts are authored as new rows with `locale = 'es'` - not translations of existing rows, so no migration needed for the archive. `sitemap-generator.php`'s blog query picks up both locales automatically once it selects on `locale` alongside `status`/`published_at`.

### 6. Rollout scope

All 5 tour/transfer pages plus the homepage, in one project (per this session's decision to do all pages rather than pilot 2 first) - but the actual implementation plan should still sequence page-by-page (translate + wire up Maipo, verify end to end, then repeat), not attempt all 6 simultaneously, so a mistake in the shared `i18n`/routing/hreflang mechanism is caught on page 1 rather than found duplicated across 6.

## Testing

- Each page's `en` output must be byte-for-byte unchanged after the extraction-to-array refactor (a regression here would be a real, user-visible content bug on the already-working English site) - diff the rendered HTML before/after for each page.
- Each `/es/...` URL renders with the translated `$t` array, correct `hreflang` pair, correct `$page_canonical`, and correct `Product`/`FAQPage` schema (name/description/FAQ text in Spanish).
- Language switcher on every page links to the correct sibling URL, not just the Spanish/English homepage.
- `.htaccess` rewrite rules don't conflict with or shadow any existing rule (verified by requesting both languages of every existing clean URL and confirming no redirect loops or 404s).
- Blog: an English-only post doesn't appear on `/es/blog`, and vice versa; sitemap includes both locales.

## Risks

- **Scope of the content extraction.** This is real, substantial work across 6 templates with a lot of prose (itinerary stops alone are ~4-6 entries per page with title+duration+description each). Underestimating this is the main risk to the project timeline, not the routing/schema/hreflang mechanics, which are all small and well-precedented by patterns already in this codebase.
- **Translation quality without native review.** Shipping unreviewed AI-translated sales copy on pages meant to convert real bookings is a real business risk (tone, idiom, and trust signals matter more than literal correctness) - a native-speaker review pass is a hard requirement before go-live, not a nice-to-have.
- **`.htaccess` fragility.** This file already carries a lot of history (legacy redirects, cPanel-generated blocks, performance headers). New rules must be added without disturbing existing rule order or the `RewriteCond %{REQUEST_FILENAME} -f/-d` passthrough at the bottom.
