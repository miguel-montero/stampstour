# Spanish-Language Pages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a fully translated Spanish (`/es/...`) version of the homepage and all 5 tour/transfer pages, with proper `hreflang`, a shared bilingual FAQ/schema/price mechanism, and a `locale`-aware blog.

**Architecture:** One PHP template per page (no duplicated `/es/` file tree). Each page reads `$lang` from a query param set by `.htaccess` rewrite rules, then `require`s a small translation array (`includes/i18n/{en,es}/{page}.php`) and renders from `$t[...]` instead of hardcoded text. Shared components already built this session (`tour_faq.php`, `tour_schema.php`, `tour_price.php`) are untouched — they already take data as PHP arrays/variables, so they work for both languages with zero changes.

**Tech Stack:** PHP (no framework), MySQL/mysqli, Apache `.htaccess` rewrites.

**Spec:** `docs/superpowers/specs/2026-08-19-spanish-language-pages-design.md`

## Global Constraints

- URL structure is `/es/` subdirectory prefix, never a subdomain or ccTLD (spec section 1).
- One template per page reading `$lang` — never duplicate a page file under `/es/` (spec section 1).
- `hreflang` uses explicit per-page `$page_canonical`/`$page_canonical_es` pairs, never a derived slug formula (spec section 3) — `cruise-transfer.php` has no clean-URL rewrite, unlike the other 5 pages.
- Every EN page's rendered output must be byte-for-byte unchanged after the extraction-to-array refactor (spec Testing).
- New Spanish blog posts get `locale = 'es'` as new rows — never back-translate existing English rows (spec section 5, non-goals).
- Legal/utility pages (`contact-us.php`, `privacy.php`, `refunds-cancellations.php`, `gallery.php`) and existing blog posts are explicitly out of scope (spec non-goals).
- All Spanish copy needs a native-Spanish-speaker review pass before going live — flag this explicitly when a task's deliverable is translated content (spec Risks).

---

## Terminology glossary (EN → ES)

Use these exact translations everywhere they recur, so voice stays consistent across all 6 pages. Established in Task 7 (Maipo) and reused for every later translation task.

| English | Spanish |
|---|---|
| Hotel pickup and drop-off | Recogida y regreso al hotel |
| Pick up time will be delivered the night before the tour | El horario de recogida se enviará la noche anterior al tour |
| Professional and expert tour guide | Guía turístico profesional y experto |
| Professional tour guide | Guía turístico profesional |
| Wine tasting | Degustación de vinos |
| Pisco Tasting | Degustación de pisco |
| Air Conditioned Bus | Bus con aire acondicionado |
| Wildlife | Fauna silvestre |
| Departure and return | Salida y regreso |
| Start: / End: | Salida: / Regreso: |
| Multiple pickup locations offered. | Se ofrecen múltiples puntos de recogida. |
| Pickup details | Detalles de recogida |
| Pick ups are available from central locations in the following districts: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Airport Area. | La recogida está disponible en puntos céntricos de las siguientes comunas: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, sector Aeropuerto. |
| If your hotel is not in the available areas please send us the location and we will evaluate if it is possible to pick up; otherwise we will provide a meeting point at the nearest place from your location. | Si tu hotel no está en las áreas disponibles, envíanos la ubicación y evaluaremos si es posible pasar a recogerte; de lo contrario, te daremos un punto de encuentro lo más cercano posible a tu ubicación. |
| Hotel pickup offered | Recogida en el hotel disponible |
| During checkout you will be able to select from the list of included hotels. | Durante la reserva podrás seleccionar tu hotel de la lista de hoteles incluidos. |
| This activity ends back at the meeting point. | Esta actividad finaliza de vuelta en el punto de encuentro. |
| Additional Information / Additional information | Información adicional |
| Confirmation will be received at time of booking | La confirmación se recibe al momento de la reserva |
| Minimum numbers apply (4 people). There is a possibility of cancellation after confirmation if there are not enough passengers to meet requirements. In the event of this occurring, you will be offered an alternative or full refund | Aplican números mínimos (4 personas). Existe la posibilidad de cancelación después de la confirmación si no se cuenta con suficientes pasajeros. En ese caso, se ofrecerá una alternativa o un reembolso completo |
| This experience requires good weather. If it's canceled due to poor weather, you'll be offered a different date or a full refund. | Esta experiencia requiere buen clima. Si se cancela por mal tiempo, se ofrecerá una fecha alternativa o un reembolso completo. |
| This tour/activity will have a maximum of 15 travelers | Este tour/actividad tendrá un máximo de 15 viajeros |
| Cancellation policy | Política de cancelación |
| For a full refund, you must cancel at least 24 hours before the experience start time. | Para un reembolso completo, debes cancelar al menos 24 horas antes del inicio de la experiencia. |
| If you cancel less than 24 hours before the experience's start time, the amount you paid will not be refunded. | Si cancelas con menos de 24 horas de anticipación, el monto pagado no será reembolsado. |
| Any changes made less than 24 hours before the experience's start time will not be accepted. | No se aceptarán cambios realizados con menos de 24 horas de anticipación. |
| Cut-off times are based on the experience's local time. | Los plazos se basan en la hora local de la experiencia. |
| This experience requires a minimum number of travelers. If it's canceled because the minimum isn't met, you'll be offered a different date/experience or a full refund. | Esta experiencia requiere un número mínimo de viajeros. Si se cancela por no alcanzar el mínimo, se ofrecerá una fecha/experiencia alternativa o un reembolso completo. |
| Detailed timeline for your tour | Cronograma detallado de tu tour |
| take a look | échale un vistazo |
| Meeting point | Punto de encuentro |
| Pick up at your location in Santiago City | Recogida en tu ubicación en la ciudad de Santiago |
| Return to the starting point | Regreso al punto de partida |
| Drop-off at your location in Santiago City | Regreso a tu ubicación en la ciudad de Santiago |
| See more | Ver más |
| FAQ | Preguntas frecuentes |
| Is lunch included? | ¿El almuerzo está incluido? |
| What's the cancellation policy? | ¿Cuál es la política de cancelación? |
| Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded. | Cancelación gratuita hasta 24 horas antes del inicio del tour; las cancelaciones dentro de ese plazo no son reembolsables. |

District/place names (Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Isla de Maipo, Viña del Mar, etc.) and winery/venue proper nouns are never translated.

---

## Task 1: `.htaccess` routing for `/es/`

**Files:**
- Modify: `.htaccess`

**Interfaces:**
- Produces: `$_GET['lang']` will be `'es'` for every `/es/...` URL, unset (defaults to `'en'`) for existing English URLs. Every later task's `$lang` detection (Task 2) depends on this.

- [ ] **Step 1: Add the new rewrite rules**

Insert immediately after the existing `# 3) Internal rewrites: slug -> actual PHP file (no redirect)` block (before the `# Blog` block), so it sits alongside the pattern it mirrors:

```apache
# =========================
# Spanish-language routes (added 2026-08-19): same pages, ?lang=es passed
# internally. See docs/superpowers/specs/2026-08-19-spanish-language-pages-design.md
RewriteRule ^es/?$ index.php?lang=es [L,QSA]
RewriteRule ^es/maipo-valley-wine-tour-santiago/?$ maipo-valley-wine-tour-santiago.php?lang=es [L]
RewriteRule ^es/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca/?$ valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php?lang=es [L]
RewriteRule ^es/discover-santiago-city-tour/?$ discover-santiago-city-tour.php?lang=es [L]
RewriteRule ^es/portillo-inca-lagoon-andes-mountains-vineyard/?$ portillo-inca-lagoon-andes-mountains-vineyard.php?lang=es [L]
RewriteRule ^es/cruise-transfer\.php/?$ cruise-transfer.php?lang=es [L]
```

Leave the `# Blog` block's existing 2 lines untouched here - the Spanish blog rules are added in Task 6, in the same block, so all blog routing stays together.

- [ ] **Step 2: Verify no syntax errors and rules don't shadow existing ones**

Run: `apachectl configtest` if available locally, otherwise `php -l` won't catch `.htaccess` syntax - instead verify by request once deployed (Step 3 below covers this for local testing via the PHP built-in server, which ignores `.htaccess` - so this step is a manual read-through: confirm every new `RewriteRule` pattern starts with `^es/`, which cannot match any existing rule's pattern (none of which start with `es`), so there is no shadowing risk).

- [ ] **Step 3: Commit**

```bash
git add .htaccess
git commit -m "Add /es/ rewrite rules for Spanish-language tour pages"
```

---

## Task 2: `$lang` detection + `hreflang` in `includes/head.php`

**Files:**
- Modify: `includes/head.php`

**Interfaces:**
- Consumes: `$page_canonical` (already required by every page), new `$page_canonical_es` (each page sets this starting in Task 7+).
- Produces: `$lang` is available as a global once any page does `$lang = (($_GET['lang'] ?? 'en') === 'es') ? 'es' : 'en';` - this exact line is what Task 7+ add to each page file, at the very top, before any output. `head.php` itself does not compute `$lang` - it only renders `hreflang` tags from whatever `$page_canonical`/`$page_canonical_es` the caller set.

- [ ] **Step 1: Add hreflang tags to head.php**

Find this existing block near the top of `includes/head.php` (the file already requires `$page_canonical`, documented in its own header comment at line 6):

```php
 *   $page_canonical    (required) - full https://stampstour.com/... URL
```

Change to document the new optional param, then add the actual tags. Locate where `<link rel="canonical" ...>` (or equivalent) is emitted - if none exists yet, add hreflang right after the `<title>` tag (`includes/head.php:83`, from this session's earlier work):

```php
<title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
<?php if (!empty($page_canonical_es)): ?>
<link rel="alternate" hreflang="en" href="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8') ?>">
<link rel="alternate" hreflang="es" href="<?= htmlspecialchars($page_canonical_es, ENT_QUOTES, 'UTF-8') ?>">
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
```

The `!empty($page_canonical_es)` guard means pages that haven't been migrated yet (in earlier tasks of this plan, before their own task runs) emit no hreflang tags at all, rather than broken ones - safe, incremental rollout.

- [ ] **Step 2: Verify no regression on a page that doesn't set `$page_canonical_es` yet**

Run a local PHP server and diff the `<head>` output of `maipo-valley-wine-tour-santiago.php` before and after this change - since Maipo doesn't set `$page_canonical_es` until Task 7, its head output must be byte-for-byte identical right now.

```bash
php -S 127.0.0.1:8199 -t /Users/miguelmontero/Documents/superpowers/STAMP &
curl -s http://127.0.0.1:8199/maipo-valley-wine-tour-santiago.php | sed -n '/<head>/,/<\/head>/p' > /tmp/head-after.html
```

Expected: no `hreflang` lines appear (since `$page_canonical_es` is unset), and everything else matches the pre-change output.

- [ ] **Step 3: Commit**

```bash
git add includes/head.php
git commit -m "Add hreflang support to head.php, gated on \$page_canonical_es"
```

---

## Task 3: Nav (`includes/header.php`) translation + language switcher

**Files:**
- Create: `includes/i18n/en/nav.php`
- Create: `includes/i18n/es/nav.php`
- Modify: `includes/header.php`

**Interfaces:**
- Consumes: `$lang` (global, set by the calling page per Task 2's interface), `$page_canonical`/`$page_canonical_es` (set by the calling page, may be empty on pages not yet migrated).
- Produces: `$navT` array available inside `header.php` for its own markup.

- [ ] **Step 1: Create the English nav strings file**

```php
<?php
// includes/i18n/en/nav.php
return [
    'home' => 'Home',
    'menu_mobile' => 'Menu mobile',
    'tours' => 'Tours',
    'valparaiso' => 'Valparaíso',
    'isla_de_maipo' => 'Isla de Maipo',
    'andes_tour' => 'Andes Tour',
    'santiago_city_tour' => 'Santiago City Tour',
    'cruise_transfer' => 'Cruise Transfer with Tour',
    'gallery' => 'Gallery',
    'blog' => 'Blog',
    'contact_us' => 'Contact us',
    'switch_to_label' => 'ES',
];
```

- [ ] **Step 2: Create the Spanish nav strings file**

```php
<?php
// includes/i18n/es/nav.php
return [
    'home' => 'Inicio',
    'menu_mobile' => 'Menú móvil',
    'tours' => 'Tours',
    'valparaiso' => 'Valparaíso',
    'isla_de_maipo' => 'Isla de Maipo',
    'andes_tour' => 'Tour Andes',
    'santiago_city_tour' => 'Tour por Santiago',
    'cruise_transfer' => 'Traslado de Crucero con Tour',
    'gallery' => 'Galería',
    'blog' => 'Blog',
    'contact_us' => 'Contáctanos',
    'switch_to_label' => 'EN',
];
```

- [ ] **Step 3: Wire header.php to load the array and use it**

At the top of `includes/header.php`, right after the existing doc-comment block (before the `<header...>` tag):

```php
<?php
$lang = (($_GET['lang'] ?? 'en') === 'es') ? 'es' : 'en';
$navT = require __DIR__ . "/i18n/{$lang}/nav.php";
?>
```

Then replace the hardcoded nav text. Each replacement below is an exact match-and-replace against the current file (confirmed against the live file this session):

- `Menu mobile` → `<?= htmlspecialchars($navT['menu_mobile'], ENT_QUOTES, 'UTF-8') ?>`
- `Home` (inside `<li><a href="/">Home</a></li>`) → `<?= htmlspecialchars($navT['home'], ENT_QUOTES, 'UTF-8') ?>`, and the `href="/"` becomes `href="<?= $lang === 'es' ? '/es/' : '/' ?>"`
- `Tours` → `<?= htmlspecialchars($navT['tours'], ENT_QUOTES, 'UTF-8') ?>`
- `Valparaíso` (submenu item) → `<?= htmlspecialchars($navT['valparaiso'], ENT_QUOTES, 'UTF-8') ?>`, href becomes `href="<?= $lang === 'es' ? '/es/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca' : '/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php' ?>"`
- `Isla de Maipo` → `<?= htmlspecialchars($navT['isla_de_maipo'], ENT_QUOTES, 'UTF-8') ?>`, href becomes `href="<?= $lang === 'es' ? '/es/maipo-valley-wine-tour-santiago' : '/maipo-valley-wine-tour-santiago.php' ?>"`
- `Andes Tour` → `<?= htmlspecialchars($navT['andes_tour'], ENT_QUOTES, 'UTF-8') ?>`, href becomes `href="<?= $lang === 'es' ? '/es/portillo-inca-lagoon-andes-mountains-vineyard' : '/portillo-inca-lagoon-andes-mountains-vineyard.php' ?>"`
- `Santiago City Tour` → `<?= htmlspecialchars($navT['santiago_city_tour'], ENT_QUOTES, 'UTF-8') ?>`, href becomes `href="<?= $lang === 'es' ? '/es/discover-santiago-city-tour' : '/discover-santiago-city-tour.php' ?>"`
- `Cruise Transfer with Tour` → `<?= htmlspecialchars($navT['cruise_transfer'], ENT_QUOTES, 'UTF-8') ?>`, href becomes `href="<?= $lang === 'es' ? '/es/cruise-transfer.php' : '/cruise-transfer.php' ?>"`
- `Gallery` → `<?= htmlspecialchars($navT['gallery'], ENT_QUOTES, 'UTF-8') ?>` (href stays `/gallery.php` - out of scope per spec non-goals, no Spanish version exists)
- `Blog` → `<?= htmlspecialchars($navT['blog'], ENT_QUOTES, 'UTF-8') ?>`, href becomes `href="<?= $lang === 'es' ? '/es/blog' : '/blog' ?>"`
- `Contact us` → `<?= htmlspecialchars($navT['contact_us'], ENT_QUOTES, 'UTF-8') ?>` (href stays `https://stampstour.com/contact-us.php` - out of scope)

- [ ] **Step 4: Add the language switcher**

In the `#top_links` list (where Instagram/Facebook/WhatsApp icons already live), add one more `<li>` before the closing `</ul>`:

```php
<li>
  <a href="<?= !empty($page_canonical_es) ? htmlspecialchars(($lang === 'es') ? $page_canonical : $page_canonical_es, ENT_QUOTES, 'UTF-8') : ($lang === 'es' ? '/' : '/es/') ?>" aria-label="Switch language">
    <?= htmlspecialchars($navT['switch_to_label'], ENT_QUOTES, 'UTF-8') ?>
  </a>
</li>
```

This links to the *other* language's version of `$page_canonical`/`$page_canonical_es` when the current page has been migrated (both are set), falling back to the site root in the other language for any page not yet migrated.

- [ ] **Step 5: Verify English pages are unchanged**

Load `maipo-valley-wine-tour-santiago.php` (no `?lang=es`) locally and confirm the nav renders identically to before (same English text, same hrefs, `EN`/`ES` switcher link added pointing at `/es/`).

```bash
curl -s http://127.0.0.1:8199/maipo-valley-wine-tour-santiago.php | grep -A2 "Isla de Maipo"
```

Expected: `href="/maipo-valley-wine-tour-santiago.php"` unchanged in meaning even though the literal markup now goes through the ternary (same resolved URL as before, since `$page_canonical_es` isn't set yet on Maipo until Task 7 - wait, verify: at this point in the plan Maipo hasn't been migrated, so `$page_canonical_es` is unset/empty and the nav href logic above uses the hardcoded `$lang === 'es' ? '/es/...' : '/...' ` ternaries directly, not the `$page_canonical`/`$page_canonical_es` pair - those ternaries work correctly on any page regardless of migration status, since they're hardcoded to the known final URL of each of the 5 tour pages).

- [ ] **Step 6: Commit**

```bash
git add includes/header.php includes/i18n/en/nav.php includes/i18n/es/nav.php
git commit -m "Add bilingual nav strings and language switcher to header.php"
```

---

## Task 4: Footer (`includes/footer.php`) translation

**Files:**
- Create: `includes/i18n/en/footer.php`
- Create: `includes/i18n/es/footer.php`
- Modify: `includes/footer.php`

**Interfaces:**
- Consumes: `$lang` (global, already set by the calling page per Task 3).
- Produces: `$footerT` array for footer.php's own markup.

- [ ] **Step 1: Create the English footer strings file**

```php
<?php
// includes/i18n/en/footer.php
return [
    'need_help' => 'Need help?',
    'about_legal' => 'About/Legal',
    'refunds_cancellations' => 'Refunds & Cancellations',
    'privacy' => 'Privacy',
    'manage_cookies' => 'Manage cookies',
    'faq' => 'FAQ',
    'discover' => 'Discover',
    'blog' => 'Blog',
    'gallery' => 'Gallery',
    'copyright' => "© Stamp's Tour 2025",
];
```

- [ ] **Step 2: Create the Spanish footer strings file**

```php
<?php
// includes/i18n/es/footer.php
return [
    'need_help' => '¿Necesitas ayuda?',
    'about_legal' => 'Legal',
    'refunds_cancellations' => 'Reembolsos y Cancelaciones',
    'privacy' => 'Privacidad',
    'manage_cookies' => 'Gestionar cookies',
    'faq' => 'Preguntas frecuentes',
    'discover' => 'Descubre',
    'blog' => 'Blog',
    'gallery' => 'Galería',
    'copyright' => "© Stamp's Tour 2025",
];
```

- [ ] **Step 3: Wire footer.php**

At the top of `includes/footer.php`, right after its existing doc-comment:

```php
<?php
$lang = (($_GET['lang'] ?? 'en') === 'es') ? 'es' : 'en';
$footerT = require __DIR__ . "/i18n/{$lang}/footer.php";
?>
```

Then swap the hardcoded strings for `$footerT[...]` lookups (`Need help?`, `About/Legal`, `Refunds & Cancellations`, `Privacy`, `Manage cookies`, `FAQ`, `Discover`, `Blog`, `Gallery` labels), same pattern as Task 3 Step 3. Note: `refunds-cancellations.php` and `privacy.php` links themselves stay pointed at the English-only pages (out of scope per spec) - only the *label text* is translated, not the destination.

- [ ] **Step 4: Verify no regression, then commit**

Same diff-based check as Task 3 Step 5, applied to the footer section of the rendered page.

```bash
git add includes/footer.php includes/i18n/en/footer.php includes/i18n/es/footer.php
git commit -m "Add bilingual footer strings"
```

---

## Task 5: Blog `locale` column and query filtering

**Files:**
- Modify: `blog.php`
- Modify: `blog-post.php`
- Modify: `sitemap-generator.php`
- Modify: `.htaccess`

**Interfaces:**
- Produces: `blog_posts.locale` column, default `'en'`. `blog.php`/`blog-post.php` both filter on it via `$lang`.

- [ ] **Step 1: Add the column**

```sql
ALTER TABLE blog_posts ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'en' AFTER slug;
```

Run this against the actual database (not a migration file - this codebase has no migration runner; `migrate_2026_07_29.php` at the repo root is the precedent for one-off schema changes run manually).

- [ ] **Step 2: Verify the column exists**

```bash
mysql -u stampst1_user -pD4t stampst1_stamptour -e "DESCRIBE blog_posts;" | grep locale
```

Expected: a `locale` row, `varchar(5)`, default `en`.

- [ ] **Step 3: Filter `blog.php`'s listing query by locale**

Find `blog.php`'s query that lists published posts (currently `WHERE status = 'published'`, no locale filter since the column didn't exist). Add:

```php
$lang = (($_GET['lang'] ?? 'en') === 'es') ? 'es' : 'en';
// ... existing query, add to its WHERE clause:
// AND locale = ?
// bind $lang alongside existing params
```

- [ ] **Step 4: Filter `blog-post.php`'s single-post query by locale**

Same pattern - add `AND locale = ?` to the query that fetches a post by `slug`, so an English-locale slug requested via `/es/blog/...` 404s instead of serving the wrong language's post.

- [ ] **Step 5: Add Spanish blog rewrite rules to `.htaccess`**

In the same `# Blog` block Task 1 left untouched:

```apache
RewriteRule ^es/blog/?$ blog.php?lang=es [L]
RewriteRule ^es/blog/([a-zA-Z0-9-]+)/?$ blog-post.php?slug=$1&lang=es [L,QSA]
```

- [ ] **Step 6: Update the sitemap generator to include both locales**

In `sitemap-generator.php`'s blog post query, select `locale` alongside the existing columns, and when building each post's `<url>` entry, prefix the `<loc>` with `/es` when `locale = 'es'`.

- [ ] **Step 7: Verify with a local test post**

```bash
mysql -u stampst1_user -pD4t stampst1_stamptour -e "INSERT INTO blog_posts (title, slug, locale, status, content, published_at) VALUES ('Test ES Post', 'test-es-post', 'es', 'published', 'Contenido de prueba', NOW());"
curl -s http://127.0.0.1:8199/blog.php | grep -c "Test ES Post"
```

Expected: `0` (English blog listing must not show the Spanish-only post).

```bash
curl -s "http://127.0.0.1:8199/blog.php?lang=es" | grep -c "Test ES Post"
```

Expected: `1`.

```bash
mysql -u stampst1_user -pD4t stampst1_stamptour -e "DELETE FROM blog_posts WHERE slug = 'test-es-post';"
```

- [ ] **Step 8: Commit**

```bash
git add blog.php blog-post.php sitemap-generator.php .htaccess
git commit -m "Add locale column and filtering to blog, plus /es/blog routes"
```

---

## Task 6: Maipo — extract EN content, write ES translation, wire the page (pilot)

**Files:**
- Create: `includes/i18n/en/maipo.php`
- Create: `includes/i18n/es/maipo.php`
- Modify: `maipo-valley-wine-tour-santiago.php`

**Interfaces:**
- Consumes: `$lang` (set at the top of this task's Step 3).
- Produces: the exact key schema every later tour-page task (7-10) mirrors: `hero_h1`, `meta_title`, `meta_description`, `product_name`, `overview_intro`, `what_to_expect` (array of HTML paragraph strings), `whats_included` (array of strings), `departure_return` (assoc array: `start`, `pickup_details`, `hotel_pickup`, `end`), `additional_info` (array of strings), `cancellation_intro`, `cancellation_bullets` (array), `itinerary` (array of assoc arrays: `duration`, `title_location`, `title_name`, `desc`), `faq` (array of assoc arrays: `q`, `a` - same shape `$tour_faqs` already uses).

- [ ] **Step 1: Create `includes/i18n/en/maipo.php` - extract the existing English content verbatim**

```php
<?php
// includes/i18n/en/maipo.php
return [
    'meta_title' => 'Maipo Valley Wine Tour with 4 vineyards from Santiago.',
    'meta_description' => 'Small-group or private Maipo Valley wine tour from Santiago. Multiple tastings, optional winery lunch, hotel pickup, English-speaking guide.',
    'product_name' => 'Small-Group Maipo Valley Wine Tour: 4 Vineyards from Santiago',
    'hero_h1' => 'Small-Group Maipo Valley Wine Tour: 4 Vineyards from Santiago',
    'h2_overview' => 'Maipo Wine Tour Overview & Highlights',
    'overview_intro' => 'Visit the bucolic town of Isla de Maipo for a day of exploring Chilean wine country. Though not truly an island, the area takes its name because it is surrounded by tributaries of the Maipo River, which make for perfect grape-growing conditions. Visit four wineries, each with their own specialties and enjoy several tastings and a delicious lunch along the way.',
    'what_to_expect_heading' => 'What to expect.',
    'what_to_expect' => [
        'Your adventure begins with pickup from your Santiago hotel or private residence in Downtown, Providencia, Las Condes, Vitacura, and Recoleta. If you are not in one of these areas, you will be provided with the nearest pickup point.',
        'The first stop on your wine tour is the picturesque family farm, Campo La Quirinca, to learn about Chilean winemaking traditions and enjoy wine tastings, accompanied by the famous Chilean pisco.',
        'Next, visit Viña Santa Ema, a charming winery offering tastings of three premium wines, including a signature blend. Then it\'s on to Viña TerraMater for lunch at the Zinfandel restaurant (at your own cost).',
        'Cap off your wine tour at Viña Undurraga, one of the oldest and most traditional wineries with 130 years of expertise. Delight in tastings of four premium wines and a comprehensive tour of gardens, vineyards, production facilities, and the wine barrel cellar.',
        'Your experience concludes with a drop-off at your original departure point.',
    ],
    'whats_included_heading' => "What's included",
    'whats_included' => [
        'Hotel pickup and drop-off (Pick up time will be delivered the night before the tour)',
        'Professional and expert tour guide',
        'Pisco Tasting',
        'Entry/Admission - Campo la Quirinca',
        'Entry/Admission - Viña Santa Ema',
        'Entry/Admission - Viña Undurraga',
        'Live coordination via WhatsApp with guide. (Recommended the use of WhatsApp)',
    ],
    'departure_return' => [
        'heading' => 'Departure and return',
        'start_label' => 'Start:',
        'start' => 'Multiple pickup locations offered.',
        'pickup_details_label' => 'Pickup details',
        'pickup_details' => 'Pick ups are available from central locations in the following districts: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Airport Area.',
        'pickup_note' => 'If your hotel is not in the available areas please send us the location and we will evaluate if it is possible to pick up; otherwise we will provide a meeting point at the nearest place from your location.',
        'hotel_pickup_label' => 'Hotel pickup offered',
        'hotel_pickup' => 'During checkout you will be able to select from the list of included hotels.',
        'end_label' => 'End:',
        'end' => 'This activity ends back at the meeting point.',
    ],
    'additional_info_heading' => 'Additional Information',
    'additional_info' => [
        'Confirmation will be received at time of booking',
        'Wheelchair accessible',
        'Stroller accessible',
        'Minimum numbers apply. There is a possibility of cancellation after confirmation if there are not enough passengers (4) to meet requirements. In the event of this occurring, you will be offered an alternative or full refund.',
        'This experience requires good weather. If it\'s canceled due to poor weather, you\'ll be offered a different date or a full refund.',
        'This tour/activity will have a maximum of 15 travelers',
        'On national holidays some locations may close; in such cases the itinerary may be adjusted or replaced with a vineyard of equal or higher quality.',
    ],
    'cancellation_heading' => 'Cancellation policy',
    'cancellation_intro' => 'For a full refund, you must cancel at least 24 hours before the experience start time.',
    'cancellation_bullets' => [
        "If you cancel less than 24 hours before the experience's start time, the amount you paid will not be refunded.",
        "Any changes made less than 24 hours before the experience's start time will not be accepted.",
        "Cut-off times are based on the experience's local time.",
        "This experience requires a minimum number of travelers. If it's canceled because the minimum isn't met, you'll be offered a different date/experience or a full refund.",
    ],
    'timeline_heading' => 'Detailed timeline for your tour',
    'timeline_subtext' => 'take a look',
    'meeting_point_location' => 'Santiago',
    'meeting_point_title' => 'Meeting point',
    'meeting_point_desc' => 'Pick up at your location in Santiago City',
    'itinerary' => [
        [
            'duration' => '1 hour 30 minutes',
            'location' => 'Isla de Maipo',
            'title' => 'Campo La Quirinca',
            'desc' => 'The first stop is the amazing family farm "Campo La Quirinca". The experience is completed with a full tour in the facilities and gardens, discovering different kinds of agricultural productions. You will be introduced to the Chilean countryside way to produce wine, also learn about the animal husbandry of alpacas, various breeds of chickens and more. After the fun tour you\'ll relax in the salon and enjoy the wine tasting plus the famous Chilean pisco. Many of the local products are available for purchase so you can take something authentic home.',
        ],
        [
            'duration' => '1 Hour',
            'location' => 'Isla de Maipo',
            'title' => 'Viña Santa Ema',
            'desc' => 'This charming winery offers a full tasting of 3 premium wines including one of their signature wines in a beautiful environment.',
        ],
        [
            'duration' => '1 hour 30 minutes',
            'location' => 'Isla de Maipo',
            'title' => 'Viña TerraMater',
            'desc' => 'Around 1 pm you will visit the TerraMater winery which has a fantastic restaurant, Zinfandel, for lunch (own cost). This winery is also home to an olive oil that is the most awarded in Chile and the world. In the shop you will be able to buy it at cellar price.',
        ],
        [
            'duration' => '1 hour 30 minutes',
            'location' => 'Isla de Maipo',
            'title' => 'Viña Undurraga',
            'desc' => 'One of the oldest and most traditional wineries with 130 years of experience. This winery will share the long history of Chilean wine production, giving you a better understanding of why our wines are high quality. You\'ll taste 4 premium wines and enjoy a full tour of the gardens, vineyards, production warehouse, wine barrel cellar, and pre-Columbian exhibit.',
        ],
    ],
    'return_location' => 'Santiago',
    'return_title' => 'Return to the starting point',
    'return_desc' => 'Drop-off at your location in Santiago City',
    'see_more_button' => 'See more',
    'faq' => [
        ['q' => 'How many wineries do we visit on the Maipo Valley wine tour?', 'a' => "Four family-run stops in Isla de Maipo: Campo La Quirinca (wine and pisco tasting), Viña Santa Ema (wine tasting), Viña TerraMater (your lunch stop, at your own cost), and Viña Undurraga (wine tasting). Tastings aren't at every stop - see the itinerary below for the full breakdown."],
        ['q' => 'Is lunch included?', 'a' => "No. You'll stop at the Zinfandel restaurant on the TerraMater estate, but the meal itself is at your own cost."],
        ['q' => 'Where does the tour pick me up?', 'a' => 'Hotel pickup is offered from Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, and the Airport area; your exact pickup time is sent the night before.'],
        ['q' => 'How many people are on the tour?', 'a' => "Groups are capped at 15 travelers, with a minimum of 4 required to run - if the minimum isn't met, you'll be offered another date or a full refund."],
        ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
        ['q' => 'Is the Maipo Valley wine tour worth it?', 'a' => "If you want a taste of Chilean wine country without renting a car or planning your own route between wineries, yes - in one day you visit four different Isla de Maipo wineries with guided tastings and hotel pickup included."],
        ['q' => 'Can I book this tour privately?', 'a' => 'A private version of this tour is available - pricing depends on your group size, so please contact us directly to inquire.'],
    ],
];
```

- [ ] **Step 2: Create `includes/i18n/es/maipo.php` - real Spanish translation**

```php
<?php
// includes/i18n/es/maipo.php
return [
    'meta_title' => 'Tour de Vinos por el Valle del Maipo con 4 viñas desde Santiago',
    'meta_description' => 'Tour de vinos en el Valle del Maipo en grupo pequeño o privado desde Santiago. Varias degustaciones, almuerzo opcional en viña, recogida en el hotel, guía en español e inglés.',
    'product_name' => 'Tour de Vinos en Grupo Pequeño por el Valle del Maipo: 4 Viñas desde Santiago',
    'hero_h1' => 'Tour de Vinos en Grupo Pequeño por el Valle del Maipo: 4 Viñas desde Santiago',
    'h2_overview' => 'Tour de Vinos en Maipo: Resumen y Destacados',
    'overview_intro' => 'Visita el bucólico pueblo de Isla de Maipo para explorar durante un día la zona vitivinícola de Chile. Aunque no es realmente una isla, el área toma su nombre porque está rodeada por afluentes del río Maipo, que crean condiciones perfectas para el cultivo de la vid. Visita cuatro viñas, cada una con sus propias especialidades, y disfruta de varias degustaciones y un delicioso almuerzo en el camino.',
    'what_to_expect_heading' => 'Qué esperar.',
    'what_to_expect' => [
        'Tu aventura comienza con la recogida en tu hotel o residencia particular en Santiago Centro, Providencia, Las Condes, Vitacura o Recoleta. Si no te encuentras en una de estas zonas, se te asignará el punto de recogida más cercano.',
        'La primera parada de tu tour de vinos es la pintoresca granja familiar Campo La Quirinca, donde aprenderás sobre las tradiciones vitivinícolas chilenas y disfrutarás de degustaciones de vino, acompañadas del famoso pisco chileno.',
        'Luego, visita Viña Santa Ema, una encantadora viña que ofrece degustaciones de tres vinos premium, incluyendo un blend característico. Después continúa hacia Viña TerraMater para almorzar en el restaurante Zinfandel (a tu propio costo).',
        'Cierra tu tour de vinos en Viña Undurraga, una de las viñas más antiguas y tradicionales, con 130 años de experiencia. Disfruta de degustaciones de cuatro vinos premium y un recorrido completo por sus jardines, viñedos, instalaciones de producción y bodega de barricas.',
        'Tu experiencia concluye con el regreso a tu punto de partida original.',
    ],
    'whats_included_heading' => 'Qué incluye',
    'whats_included' => [
        'Recogida y regreso al hotel (el horario de recogida se enviará la noche anterior al tour)',
        'Guía turístico profesional y experto',
        'Degustación de pisco',
        'Entrada - Campo La Quirinca',
        'Entrada - Viña Santa Ema',
        'Entrada - Viña Undurraga',
        'Coordinación en vivo vía WhatsApp con el guía (se recomienda el uso de WhatsApp)',
    ],
    'departure_return' => [
        'heading' => 'Salida y regreso',
        'start_label' => 'Salida:',
        'start' => 'Se ofrecen múltiples puntos de recogida.',
        'pickup_details_label' => 'Detalles de recogida',
        'pickup_details' => 'La recogida está disponible en puntos céntricos de las siguientes comunas: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, sector Aeropuerto.',
        'pickup_note' => 'Si tu hotel no está en las áreas disponibles, envíanos la ubicación y evaluaremos si es posible pasar a recogerte; de lo contrario, te daremos un punto de encuentro lo más cercano posible a tu ubicación.',
        'hotel_pickup_label' => 'Recogida en el hotel disponible',
        'hotel_pickup' => 'Durante la reserva podrás seleccionar tu hotel de la lista de hoteles incluidos.',
        'end_label' => 'Regreso:',
        'end' => 'Esta actividad finaliza de vuelta en el punto de encuentro.',
    ],
    'additional_info_heading' => 'Información adicional',
    'additional_info' => [
        'La confirmación se recibe al momento de la reserva',
        'Accesible para sillas de ruedas',
        'Accesible para coches de bebé',
        'Aplican números mínimos. Existe la posibilidad de cancelación después de la confirmación si no se cuenta con suficientes pasajeros (4) para cumplir los requisitos. En ese caso, se ofrecerá una alternativa o un reembolso completo.',
        'Esta experiencia requiere buen clima. Si se cancela por mal tiempo, se ofrecerá una fecha alternativa o un reembolso completo.',
        'Este tour/actividad tendrá un máximo de 15 viajeros',
        'En feriados nacionales algunos lugares pueden cerrar; en esos casos el itinerario podrá ajustarse o reemplazarse por una viña de igual o mayor calidad.',
    ],
    'cancellation_heading' => 'Política de cancelación',
    'cancellation_intro' => 'Para un reembolso completo, debes cancelar al menos 24 horas antes del inicio de la experiencia.',
    'cancellation_bullets' => [
        'Si cancelas con menos de 24 horas de anticipación, el monto pagado no será reembolsado.',
        'No se aceptarán cambios realizados con menos de 24 horas de anticipación.',
        'Los plazos se basan en la hora local de la experiencia.',
        'Esta experiencia requiere un número mínimo de viajeros. Si se cancela por no alcanzar el mínimo, se ofrecerá una fecha/experiencia alternativa o un reembolso completo.',
    ],
    'timeline_heading' => 'Cronograma detallado de tu tour',
    'timeline_subtext' => 'échale un vistazo',
    'meeting_point_location' => 'Santiago',
    'meeting_point_title' => 'Punto de encuentro',
    'meeting_point_desc' => 'Recogida en tu ubicación en la ciudad de Santiago',
    'itinerary' => [
        [
            'duration' => '1 hora 30 minutos',
            'location' => 'Isla de Maipo',
            'title' => 'Campo La Quirinca',
            'desc' => 'La primera parada es la increíble granja familiar "Campo La Quirinca". La experiencia se completa con un recorrido completo por las instalaciones y jardines, descubriendo distintos tipos de producción agrícola. Conocerás la forma tradicional chilena de producir vino y aprenderás sobre la crianza de alpacas, distintas razas de gallinas y más. Después del entretenido recorrido, relájate en el salón y disfruta de la degustación de vino junto con el famoso pisco chileno. Muchos de los productos locales están disponibles para comprar, así podrás llevarte algo auténtico a casa.',
        ],
        [
            'duration' => '1 hora',
            'location' => 'Isla de Maipo',
            'title' => 'Viña Santa Ema',
            'desc' => 'Esta encantadora viña ofrece una degustación completa de 3 vinos premium, incluyendo uno de sus vinos característicos, en un ambiente hermoso.',
        ],
        [
            'duration' => '1 hora 30 minutos',
            'location' => 'Isla de Maipo',
            'title' => 'Viña TerraMater',
            'desc' => 'Alrededor de la 1 pm visitarás la viña TerraMater, que cuenta con un fantástico restaurante, Zinfandel, para almorzar (a tu propio costo). Esta viña también produce un aceite de oliva que es el más premiado de Chile y del mundo. En su tienda podrás comprarlo a precio de bodega.',
        ],
        [
            'duration' => '1 hora 30 minutos',
            'location' => 'Isla de Maipo',
            'title' => 'Viña Undurraga',
            'desc' => 'Una de las viñas más antiguas y tradicionales, con 130 años de experiencia. Esta viña compartirá contigo la larga historia de la producción vitivinícola chilena, para que comprendas mejor por qué nuestros vinos son de tan alta calidad. Degustarás 4 vinos premium y disfrutarás de un recorrido completo por los jardines, viñedos, bodega de producción, bodega de barricas y exhibición precolombina.',
        ],
    ],
    'return_location' => 'Santiago',
    'return_title' => 'Regreso al punto de partida',
    'return_desc' => 'Regreso a tu ubicación en la ciudad de Santiago',
    'see_more_button' => 'Ver más',
    'faq' => [
        ['q' => '¿Cuántas viñas visitamos en el tour de vinos del Valle del Maipo?', 'a' => 'Cuatro paradas familiares en Isla de Maipo: Campo La Quirinca (degustación de vino y pisco), Viña Santa Ema (degustación de vino), Viña TerraMater (tu parada de almuerzo, a tu propio costo) y Viña Undurraga (degustación de vino). Las degustaciones no están en todas las paradas - revisa el itinerario para el detalle completo.'],
        ['q' => '¿El almuerzo está incluido?', 'a' => 'No. Harás una parada en el restaurante Zinfandel, en la propiedad de TerraMater, pero el almuerzo en sí es a tu propio costo.'],
        ['q' => '¿Dónde me recoge el tour?', 'a' => 'Se ofrece recogida en el hotel desde Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta y el sector Aeropuerto; tu horario exacto de recogida se envía la noche anterior.'],
        ['q' => '¿Cuántas personas van en el tour?', 'a' => 'Los grupos tienen un máximo de 15 viajeros, con un mínimo de 4 para realizar el tour - si no se alcanza el mínimo, se ofrecerá otra fecha o un reembolso completo.'],
        ['q' => '¿Cuál es la política de cancelación?', 'a' => 'Cancelación gratuita hasta 24 horas antes del inicio del tour; las cancelaciones dentro de ese plazo no son reembolsables.'],
        ['q' => '¿Vale la pena el tour de vinos del Valle del Maipo?', 'a' => 'Si buscas conocer la zona vitivinícola de Chile sin arrendar un auto ni planificar tu propia ruta entre viñas, sí - en un solo día visitas cuatro viñas distintas en Isla de Maipo con degustaciones guiadas y recogida en el hotel incluida.'],
        ['q' => '¿Puedo reservar este tour de forma privada?', 'a' => 'Existe una versión privada de este tour - el precio depende del tamaño de tu grupo, así que contáctanos directamente para consultar.'],
    ],
];
```

**Note for whoever reviews this:** this is a first-pass translation. Per the spec's Risks section, it needs a native-Spanish-speaker review before this page goes live in production - flag it as a review task, don't skip it.

- [ ] **Step 3: Wire `maipo-valley-wine-tour-santiago.php` to use `$t`**

At the very top of the file, before the existing `$page_title = ...` line:

```php
<?php
$lang = (($_GET['lang'] ?? 'en') === 'es') ? 'es' : 'en';
$t = require __DIR__ . "/includes/i18n/{$lang}/maipo.php";
```

Change the existing metadata lines to pull from `$t`:

```php
$page_title       = $t['meta_title'];
$page_description = $t['meta_description'];
$page_canonical    = 'https://stampstour.com/maipo-valley-wine-tour-santiago';
$page_canonical_es = 'https://stampstour.com/es/maipo-valley-wine-tour-santiago';
```

Change `render_tour_product_schema()`'s `'name'` to `$t['product_name']`, and `$tour_faqs = [...]` to `$tour_faqs = $t['faq'];` (delete the old hardcoded array entirely - it now lives in the i18n files).

Change `<html lang="en">` to `<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">`.

In the hero section, `<h1>Small-Group Maipo Valley Wine Tour: 4 Vineyards from Santiago</h1>` becomes `<h1><?= htmlspecialchars($t['hero_h1'], ENT_QUOTES, 'UTF-8') ?></h1>`.

In the Overview section:
- `<h2>Maipo Wine Tour Overview & Highlights</h2>` → `<h2><?= htmlspecialchars($t['h2_overview'], ENT_QUOTES, 'UTF-8') ?></h2>`
- `<p>Visit the bucolic town...</p>` → `<p><?= $t['overview_intro'] ?></p>`
- `<h4>What to expect.</h4>` → `<h4><?= htmlspecialchars($t['what_to_expect_heading'], ENT_QUOTES, 'UTF-8') ?></h4>`
- The 5 `<p>` paragraphs that follow → `<?php foreach ($t['what_to_expect'] as $para): ?><p><?= $para ?></p><?php endforeach; ?>`
- `<h4>What's included</h4>` → `<h4><?= htmlspecialchars($t['whats_included_heading'], ENT_QUOTES, 'UTF-8') ?></h4>`
- The `<ul class="list_ok">` items → `<?php foreach ($t['whats_included'] as $item): ?><li><?= $item ?></li><?php endforeach; ?>`

Departure/return, Additional Information, and Cancellation policy sections follow the identical `foreach`-over-array pattern using `$t['departure_return']`, `$t['additional_info']`, `$t['cancellation_intro']`/`$t['cancellation_bullets']` - each hardcoded string replaced by its `$t[...]` equivalent, each `<li>`-per-array-item list replaced by a `foreach`.

Timeline section: the static "Meeting point" `<li>` uses `$t['meeting_point_location']`/`$t['meeting_point_title']`/`$t['meeting_point_desc']`. The 4 itinerary stops become:

```php
<?php foreach ($t['itinerary'] as $stop): ?>
<li>
 <time class="cbp_tmtime" datetime="07:30"><span><?= htmlspecialchars($stop['duration'], ENT_QUOTES, 'UTF-8') ?></span><span></span></time>
 <div class="cbp_tmicon icon-wine"></div>
 <div class="cbp_tmlabel">
  <h2><span><?= htmlspecialchars($stop['location'], ENT_QUOTES, 'UTF-8') ?></span> <?= htmlspecialchars($stop['title'], ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= $stop['desc'] ?></p>
 </div>
</li>
<?php endforeach; ?>
```

(Note: this collapses the 4 different `cbp_tmicon` classes - `icon-camera-alt`, `icon-wine`, `icon-restaurant`, `icon-wine` - from the original into one. To preserve the original per-stop icons exactly, add an `'icon' => 'icon-camera-alt'` key to each item in Steps 1-2's `itinerary` arrays instead, and use `<div class="cbp_tmicon <?= htmlspecialchars($stop['icon'], ENT_QUOTES, 'UTF-8') ?>"></div>` here - do this, don't silently drop the distinct icons.)

The final "Return to starting point" `<li>` uses `$t['return_location']`/`$t['return_title']`/`$t['return_desc']`. The `See more` button text uses `$t['see_more_button']`.

- [ ] **Step 4: Verify the English version is unchanged**

```bash
curl -s http://127.0.0.1:8199/maipo-valley-wine-tour-santiago.php > /tmp/maipo-en-after.html
```

Diff this against a copy saved before Step 3's edits. Expected: identical rendered text content (whitespace/attribute-ordering differences from the refactor are fine; visible text, links, and structure must match exactly).

- [ ] **Step 5: Verify the Spanish version renders correctly**

```bash
curl -s "http://127.0.0.1:8199/maipo-valley-wine-tour-santiago.php?lang=es" > /tmp/maipo-es.html
grep -o "<h1>[^<]*</h1>" /tmp/maipo-es.html
```

Expected: `<h1>Tour de Vinos en Grupo Pequeño por el Valle del Maipo: 4 Viñas desde Santiago</h1>`.

```bash
grep -c "accordion-item" /tmp/maipo-es.html
```

Expected: `7` (matches the 7-item `$t['faq']` array).

```bash
python3 -c "
import re, json
html = open('/tmp/maipo-es.html', encoding='utf-8').read()
for s in re.findall(r'<script type=\"application/ld\+json\">(.*?)</script>', html, re.S):
    d = json.loads(s)
    if d.get('@type') == 'Product':
        print(d['name'])
"
```

Expected: `Tour de Vinos en Grupo Pequeño por el Valle del Maipo: 4 Viñas desde Santiago` (confirms the schema picks up the Spanish `product_name`).

- [ ] **Step 6: Verify hreflang**

```bash
grep "hreflang" /tmp/maipo-es.html
```

Expected: 3 `<link rel="alternate" hreflang="...">` lines (`en`, `es`, `x-default`), `en`/`x-default` pointing at `https://stampstour.com/maipo-valley-wine-tour-santiago`, `es` at `https://stampstour.com/es/maipo-valley-wine-tour-santiago`.

- [ ] **Step 7: Commit**

```bash
git add maipo-valley-wine-tour-santiago.php includes/i18n/en/maipo.php includes/i18n/es/maipo.php
git commit -m "Add Spanish translation for the Maipo tour page (pilot for i18n pattern)"
```

---

## Task 7: Discover Santiago — extract, translate, wire

**Files:**
- Create: `includes/i18n/en/santiago.php`
- Create: `includes/i18n/es/santiago.php`
- Modify: `discover-santiago-city-tour.php`

**Interfaces:**
- Same key schema as Task 6 (`meta_title`, `meta_description`, `product_name`, `hero_h1`, `overview_intro`, `what_to_expect`, `whats_included`, plus this page's extra `whats_not_included`, `departure_return`, `additional_info`, `cancellation_intro`/`cancellation_bullets`, `itinerary`, `faq`). Follow Task 6's exact wiring pattern (Step 3) applied to this page's own markup.

- [ ] **Step 1: Create `includes/i18n/en/santiago.php` with the current English content, extracted verbatim**

Source content (confirmed against the live file this session, `discover-santiago-city-tour.php`):

```php
<?php
// includes/i18n/en/santiago.php
return [
    'meta_title' => 'Discover Santiago Half Day Guided Tour Included Local Snack',
    'meta_description' => 'Half-day guided city tour of Santiago with an English-speaking guide. Hotel pickup, snack included, views, historic center & market.',
    'product_name' => 'Santiago City Tour with Hotel Pickup & English Guide',
    'hero_h1' => 'Santiago City Tour with Hotel Pickup & English Guide',
    'h2_overview' => 'Tour Overview & Highlights',
    'overview_intro' => "Get acquainted with the treasured landmarks of the Chilean capital on a 5-hour tour of Santiago by luxury coach. From your guide, you'll receive an interesting introduction to Santiago as you visit landmarks such as Metropolitan Cathedral of Santiago, Cerro Santa Lucia and La Moneda palace. Absorb the bohemian charm of Bellavista neighborhood and opt to explore the city's financial district, nicknamed 'Sanhattan', due to its plethora of skyscrapers. Hotel pickup and drop-off is included in this tour.",
    'what_to_expect_heading' => 'What to expect.',
    'what_to_expect' => [
        "Your half-day adventure begins with hotel pickup in Santiago. From there, you'll head to <strong>Parque Bicentenario</strong>, a peaceful green space in Vitacura where you'll enjoy views of the city alongside native flora and fauna — a perfect way to ease into the rhythm of the capital.",
        "Continuing through the vibrant city, you'll pass through <strong>Bellavista</strong>, Santiago's cultural and bohemian district. Your guide will point out local landmarks like La Chascona, Pablo Neruda's quirky former home, and the entrance to the Metropolitan Park, where cable cars and funiculars offer scenic rides up the hills.",
        "Next, immerse yourself in local life at the <strong>Central Market</strong>, where the flavors, colors, and energy of Santiago come alive. This is where you'll get your first real taste of the city's daily heartbeat.",
        "Step into history at <strong>Plaza de Armas</strong>, the historic heart of Santiago, surrounded by colonial-era architecture. Nearby, you'll enter the <strong>Metropolitan Cathedral</strong>, a masterpiece of neoclassical design and Chile's most important Catholic church.",
        "You'll also stop by the <strong>Ex Congreso Nacional</strong>, the former Chilean Congress building, and admire the façade of the elegant <strong>Stock Exchange</strong> building — both key pieces of Santiago's historical and political landscape.",
        "Then, at the iconic <strong>La Moneda Palace</strong>, you'll explore Chile's civic center. Your guide will share the story of the 1973 military coup that took place here, a pivotal moment in the country's history.",
        "From there, cruise past <strong>Parque Forestal</strong>, a beautiful French-style park that's home to the first fine arts museum in Latin America, before heading to your final destination: <strong>Cerro Santa Lucía</strong>.",
        "This hilltop park offers panoramic 360° views of the city and a fascinating glimpse into Santiago's origins. It's one of the city's most iconic spots.",
        "After a refreshing break, you'll be dropped off at your hotel, with a deeper appreciation of Santiago's culture, history, and charm — all in just half a day.",
    ],
    'whats_included_heading' => "What's included",
    'whats_included' => [
        'Hotel pickup and drop-off (Pick up time will be delivered the night before the tour)',
        'Professional and expert tour guide',
        'Local Snack',
    ],
    'whats_not_included_heading' => "What's not included",
    'whats_not_included' => [
        'Airport pickup and drop-off (inquire about extra fee)',
        'Lunch',
        'Gratuities',
    ],
    'departure_return' => [
        'heading' => 'Departure and return',
        'start_label' => 'Start:',
        'start' => 'Multiple pickup locations offered.',
        'pickup_details_label' => 'Pickup details',
        'pickup_details' => 'Pick ups are available from central locations in the following districts: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Airport Area.',
        'pickup_note' => 'If your hotel is not in the available areas please send us the location and we will evaluate if it is possible to pick up; otherwise we will provide a meeting point at the nearest place from your location.',
        'hotel_pickup_label' => 'Hotel pickup offered',
        'hotel_pickup' => 'During checkout you will be able to select from the list of included hotels.',
        'end_label' => 'End:',
        'end' => 'This activity ends back at the meeting point.',
    ],
    'additional_info_heading' => 'Additional Information',
    'additional_info' => [
        'Confirmation will be received at time of booking',
        'Most travelers can participate',
        'Minimum numbers apply (4 people). There is a possibility of cancellation after confirmation if there are not enough passengers to meet requirements. In the event of this occurring, you will be offered an alternative or full refund',
        "This experience requires good weather. If it's canceled due to poor weather, you'll be offered a different date or a full refund.",
        'This tour/activity will have a maximum of 15 travelers',
        'On Sundays and national holidays, the winery is closed, and the wine tasting will be held at an alternative location.',
    ],
    'cancellation_heading' => 'Cancellation policy',
    'cancellation_intro' => 'For a full refund, you must cancel at least 24 hours before the experience start time.',
    'cancellation_bullets' => [
        "If you cancel less than 24 hours before the experience's start time, the amount you paid will not be refunded.",
        "Any changes made less than 24 hours before the experience's start time will not be accepted.",
        "Cut-off times are based on the experience's local time.",
        "This experience requires a minimum number of travelers. If it's canceled because the minimum isn't met, you'll be offered a different date/experience or a full refund.",
    ],
    'timeline_heading' => 'Detailed timeline for your tour',
    'timeline_subtext' => 'take a look',
    'meeting_point_location' => 'Santiago',
    'meeting_point_title' => 'Meeting point',
    'meeting_point_desc' => 'Pick up at your location in Santiago City',
    'itinerary' => [
        ['duration' => '15 minutes', 'icon' => 'icon-leaf', 'location' => 'Vitacura', 'title' => 'Parque Bicentenario', 'desc' => 'A peaceful park with native flora and lagoon birds — a perfect start to the day.'],
        ['duration' => 'Pass by', 'icon' => 'icon_set_1_icon-28', 'location' => 'Providencia', 'title' => 'Barrio Bellavista', 'desc' => 'Bohemian quarter. Pass by Pablo Neruda\'s <em>La Chascona</em> and the entrance to Cerro San Cristóbal.'],
        ['duration' => '15 minutes', 'icon' => 'icon_set_3_restaurant-7', 'location' => 'Santiago Centro', 'title' => 'Mercado Central', 'desc' => 'Iconic seafood market with colorful stalls and lively local atmosphere.'],
        ['duration' => '20 minutes', 'icon' => 'icon-monument', 'location' => 'Santiago Centro', 'title' => 'Plaza de Armas', 'desc' => 'Main square surrounded by historic buildings and street life — the heart of Santiago\'s old town.'],
        ['duration' => '15 minutes', 'icon' => 'icon_set_1_icon-2', 'location' => 'Santiago Centro', 'title' => 'Metropolitan Cathedral', 'desc' => 'Neoclassical interior and altars — Chile\'s most important Catholic church.'],
        ['duration' => '5 minutes', 'icon' => 'icon_set_1_icon-4', 'location' => 'Santiago Centro', 'title' => 'Ex Congreso Nacional', 'desc' => 'Elegant former Congress building, today used for cultural/government events.'],
        ['duration' => '5 minutes', 'icon' => 'icon_set_1_icon-44', 'location' => 'Santiago Centro', 'title' => 'Bolsa de Comercio (Stock Exchange)', 'desc' => 'Quick photo stop for the beautiful neoclassical exchange building.'],
        ['duration' => '15 minutes', 'icon' => 'icon-flag', 'location' => 'Distrito Cívico', 'title' => 'Palacio de La Moneda', 'desc' => 'Chile\'s presidential palace; stories of the modern republic and the 1973 events.'],
        ['duration' => 'Pass by', 'icon' => 'icon-tree', 'location' => 'Santiago Centro', 'title' => 'Parque Forestal', 'desc' => 'Drive along this French-style park, home to the Fine Arts Museum (MNBA).'],
        ['duration' => '30 minutes', 'icon' => 'icon-eye', 'location' => 'Santiago Centro', 'title' => 'Cerro Santa Lucía', 'desc' => 'Hilltop park with fountains, terraces, and a panoramic 360° city view.'],
    ],
    'return_location' => 'Santiago',
    'return_title' => 'Return to the starting point',
    'return_desc' => 'Drop-off at your location in Santiago City.',
    'see_more_button' => 'See more',
    'faq' => [
        ['q' => 'How long is the Santiago city tour?', 'a' => "It's a 5-hour half-day tour, so you'll still have the rest of the day free."],
        ['q' => 'What landmarks does the tour visit?', 'a' => 'Parque Bicentenario, Bellavista, the Central Market, Plaza de Armas, the Metropolitan Cathedral, the former Congress building, La Moneda Palace, Parque Forestal, and Cerro Santa Lucía.'],
        ['q' => 'Is food included?', 'a' => 'A local snack is included; lunch is not, since the tour runs half a day.'],
        ['q' => 'Is there a minimum group size?', 'a' => 'Yes - a minimum of 4 travelers is required to run the tour, and groups are capped at 15.'],
        ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
    ],
];
```

- [ ] **Step 2: Create `includes/i18n/es/santiago.php` with a real Spanish translation**

Use the Task 6 glossary for every recurring phrase (departure/return block, additional info, cancellation policy, meeting point/return, `See more`). Translate the page-specific content (overview, what-to-expect landmarks, itinerary stop descriptions, FAQ) matching the register and quality bar Task 6 established. This is a full, real deliverable, not a placeholder - produce complete Spanish prose for every key in Step 1's array, in the same structure. Flag for native-speaker review before shipping, same as Task 6.

- [ ] **Step 3: Wire `discover-santiago-city-tour.php`, following Task 6 Step 3's exact pattern** (own `$lang`/`$t` require at the top, `$page_canonical_es = 'https://stampstour.com/es/discover-santiago-city-tour'`, `<html lang="<?= $lang ?>">`, hero H1, overview/what-to-expect/included/not-included sections, departure/additional-info/cancellation sections, itinerary `foreach` with the `icon` key preserved, return block, FAQ array swap).

- [ ] **Step 4: Verify EN unchanged, ES renders, hreflang correct, schema correct** - identical checks to Task 6 Steps 4-6, run against this page's URLs.

- [ ] **Step 5: Commit**

```bash
git add discover-santiago-city-tour.php includes/i18n/en/santiago.php includes/i18n/es/santiago.php
git commit -m "Add Spanish translation for the Discover Santiago tour page"
```

---

## Task 8: Andes/Portillo — extract, translate, wire

**Files:**
- Create: `includes/i18n/en/andes.php`
- Create: `includes/i18n/es/andes.php`
- Modify: `portillo-inca-lagoon-andes-mountains-vineyard.php`

**Interfaces:** Same schema as Task 6/7.

- [ ] **Step 1: Create `includes/i18n/en/andes.php`**, extracted verbatim from the live file:

```php
<?php
// includes/i18n/en/andes.php
return [
    'meta_title' => 'Andes Range Tour at Inca Lagoon & InSitu Winery Snack Included',
    'meta_description' => 'See the Andes and the turquoise Inca Lagoon at Portillo, plus a winery tasting. Small-group or private tour with hotel pickup in Santiago.',
    'product_name' => 'Andes Day Trip from Santiago with Wine Tasting',
    'hero_h1' => 'Andes Day Trip from Santiago with Wine Tasting',
    'h2_overview' => 'Tour Overview & Highlights',
    'overview_intro' => "Immerse yourself in the mountainous splendor of the Andes on an exhilarating full-day excursion to In Situ Vineyard from Santiago. With a professional guide, travel through spectacular scenery to one of the most beautiful lagoons in the Andes Mountains; capture photographs of the water's iridescent turquoise surface and listen to poignant legends of Incan royalty. Ascend to the vertiginous altitude of Hotel Portillo ski resort; and absorb the verdant beauty of the vineyard before pleasuring your palate with wine samples.",
    'what_to_expect_heading' => 'What to expect.',
    'what_to_expect' => [
        "Your journey into the Andes begins with pickup at your hotel in Santiago. Board a comfortable vehicle and travel north toward the towering mountain range that defines Chile's eastern frontier. Along the way, make a brief stop at a Copec service station. This a technical stop to grab a bottle of water, coffee or some stacks to bring on the way.",
        "Continuing along Autopista Los Libertadores, marvel at the distant silhouette of Aconcagua, the highest peak in the Americas. Weather permitting, you'll stop for photos of this majestic mountain before heading into the heart of the Andes.",
        'Your next stop is In Situ Family Vineyards, an exceptional high-altitude winery. Savor a selection of wines while enjoying stunning views of the surrounding mountains. The experience is both relaxing and memorable, ideal for lovers of nature and wine alike.',
        "From there, ascend the dramatic road known as Los Caracoles, a twisting highway of over 30 hairpin curves that climbs to 3,000 meters. Pause to capture breathtaking photos of the switchbacks and valleys below.",
        "Soon after, you'll reach the striking turquoise waters of the Inca Lagoon. Nestled at the base of snowcapped peaks, this serene spot is perfect for exploration and photography. You'll have time to enjoy lunch at the historic Hotel Portillo, South America's oldest ski resort, known for hosting world-famous skiers and celebrities.",
        'In the afternoon, visit Ventisquero Guardia Vieja, a charming rest stop where you can snack on fresh Chilean empanadas and even meet friendly llamas at a local farm.',
        'The final stop of the day is Salto del Soldado, a dramatic canyon shrouded in legend. Learn the story of the brave soldier who leapt to safety from these cliffs as you take in the impressive rock formations.',
        "After a full day of adventure, natural beauty, and cultural discovery, you'll return to your hotel in Santiago with unforgettable memories of the Andes.",
    ],
    'whats_included_heading' => "What's included",
    'whats_included' => [
        'Hotel pickup and drop-off (Pick up time will be delivered the night before the tour)',
        'Professional and expert tour guide',
        'Entry/Admission - In Situ Family Vineyars',
        'Entry/Admission - Laguna del Inca',
        'Empanada snack',
    ],
    'departure_return' => [
        'heading' => 'Departure and return',
        'start_label' => 'Start:',
        'start' => 'Multiple pickup locations offered.',
        'pickup_details_label' => 'Pickup details',
        'pickup_details' => 'Pick ups are available from central locations in the following districts: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Airport Area.',
        'pickup_note' => 'If your hotel is not in the available areas please send us the location and we will evaluate if it is possible to pick up; otherwise we will provide a meeting point at the nearest place from your location.',
        'hotel_pickup_label' => 'Hotel pickup offered',
        'hotel_pickup' => 'During checkout you will be able to select from the list of included hotels.',
        'end_label' => 'End:',
        'end' => 'This activity ends back at the meeting point.',
    ],
    'additional_info_heading' => 'Additional Information',
    'additional_info' => [
        'Confirmation will be received at time of booking',
        'Most travelers can participate',
        'Minimum numbers apply (4 people). There is a possibility of cancellation after confirmation if there are not enough passengers to meet requirements. In the event of this occurring, you will be offered an alternative or full refund',
        "This experience requires good weather. If it's canceled due to poor weather, you'll be offered a different date or a full refund.",
        'This tour/activity will have a maximum of 15 travelers',
        'On Sundays and national holidays, the winery is closed, and the wine tasting will be held at an alternative location.',
    ],
    'cancellation_heading' => 'Cancellation policy',
    'cancellation_intro' => 'For a full refund, you must cancel at least 24 hours before the experience start time.',
    'cancellation_bullets' => [
        "If you cancel less than 24 hours before the experience's start time, the amount you paid will not be refunded.",
        "Any changes made less than 24 hours before the experience's start time will not be accepted.",
        "Cut-off times are based on the experience's local time.",
        "This experience requires a minimum number of travelers. If it's canceled because the minimum isn't met, you'll be offered a different date/experience or a full refund.",
    ],
    'timeline_heading' => 'Detailed timeline for your tour',
    'timeline_subtext' => 'take a look',
    'meeting_point_location' => 'Santiago',
    'meeting_point_title' => 'Meeting point',
    'meeting_point_desc' => 'Pick up at your location in Santiago City',
    'itinerary' => [
        ['duration' => '20 minutes', 'icon' => 'icon-coffee', 'location' => 'Santiago', 'title' => 'Copec', 'desc' => 'This is a technical stop to grab a bottle of water, coffee, or some snacks to bring on the way.'],
        ['duration' => '15 minutes', 'icon' => 'icon-picture', 'location' => 'Los Andes, Valparaíso', 'title' => 'Autopista los Libertadores', 'desc' => 'In a specific location on the highway it is possible to see the highest mountain in America — the Aconcagua peak. On clear days, you may stop here to take beautiful pictures.'],
        ['duration' => '1 hour', 'icon' => 'icon-wine', 'location' => 'Los Andes, Valparaíso', 'title' => 'In Situ Family Vineyards', 'desc' => 'Set high in the Andes, this picturesque vineyard invites you to savor its signature wines in a relaxed, scenic setting — perfect for sharing great flavors and beautiful views.'],
        ['duration' => '10 minutes', 'icon' => 'icon-road', 'location' => 'Los Andes, Valparaíso', 'title' => 'Los Caracoles Road', 'desc' => "This is one of the most remarkable highways you'll ever see. Known as Los Caracoles (The Snails), it features over 30 sharp curves winding their way up to 3,000 meters above sea level. A unique photo opportunity."],
        ['duration' => '1 hour', 'icon' => 'icon-eye-7', 'location' => 'Los Andes, Valparaíso', 'title' => 'Laguna del Inca', 'desc' => 'Arrive at the stunning Inca Lagoon — one of the highlights of the Andes. Explore the area, enjoy the incredible views, and, if you like, have lunch at the Portillo Hotel restaurant.'],
        ['duration' => '30 minutes', 'icon' => 'icon-skiing', 'location' => 'Los Andes, Valparaíso', 'title' => 'Portillo', 'desc' => 'The oldest ski resort in South America, Portillo has welcomed famous visitors from around the world. A place rich in history and charm, nestled high in the Andes.'],
        ['duration' => '30 minutes', 'icon' => 'icon-food', 'location' => 'Los Andes, Valparaíso', 'title' => 'Ventisquero Guardia Vieja', 'desc' => 'A technical stop where you can enjoy a tasty Chilean empanada and visit a charming llama farm — a fun and flavorful break in the journey.'],
        ['duration' => '10 minutes', 'icon' => 'icon-camera', 'location' => 'Los Andes, Valparaíso', 'title' => 'Salto del Soldado', 'desc' => 'This dramatic viewpoint overlooks a canyon carved by centuries of erosion. Legend says a soldier once leapt to safety here, giving it the name "The Soldier\'s Leap".'],
    ],
    'return_location' => 'Santiago',
    'return_title' => 'Return to the starting point',
    'return_desc' => 'Drop-off at your location in Santiago City.',
    'see_more_button' => 'See more',
    'faq' => [
        ['q' => 'How high up does the Andes tour go?', 'a' => 'The route climbs the switchback road Los Caracoles to about 3,000 meters, reaching Hotel Portillo and the turquoise Inca Lagoon.'],
        ['q' => 'What should I bring or wear?', 'a' => "Warm layers are recommended even in summer - you're at high altitude near snowcapped peaks, and it's noticeably colder than Santiago."],
        ['q' => 'Is food included?', 'a' => 'An empanada snack stop is included at Ventisquero Guardia Vieja; lunch at Hotel Portillo is at your own expense.'],
        ['q' => 'How long is the tour and where does it start?', 'a' => "It's a full 10-hour day tour with hotel pickup in Santiago, from Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, or the Airport area."],
        ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
    ],
];
```

- [ ] **Step 2: Create `includes/i18n/es/andes.php`** with a full, real Spanish translation of every key above, following the Task 6 glossary and register. Note: the Andes page also has the keyword-research-flagged "tour a la nieve" phrasing decision (deferred per this session's earlier discussion) - this task translates the page as-is; it does NOT add that phrase, since that was explicitly deferred pending the broader Spanish-content decision this plan now resolves. If desired, a small follow-up task can revisit it once this page has a real Spanish version live to build on.

- [ ] **Step 3: Wire `portillo-inca-lagoon-andes-mountains-vineyard.php`**, following Task 6 Step 3's pattern (`$page_canonical_es = 'https://stampstour.com/es/portillo-inca-lagoon-andes-mountains-vineyard'`).

- [ ] **Step 4: Verify EN unchanged, ES renders, hreflang correct, schema correct.**

- [ ] **Step 5: Commit**

```bash
git add portillo-inca-lagoon-andes-mountains-vineyard.php includes/i18n/en/andes.php includes/i18n/es/andes.php
git commit -m "Add Spanish translation for the Andes tour page"
```

---

## Task 9: Valparaíso — extract, translate, wire

**Files:**
- Create: `includes/i18n/en/valparaiso.php`
- Create: `includes/i18n/es/valparaiso.php`
- Modify: `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`

**Interfaces:** Same schema as Task 6-8, plus this page's 13-stop itinerary (longest of the 5 tour pages) and its `Can I book this directly...` reseller FAQ.

- [ ] **Step 1: Create `includes/i18n/en/valparaiso.php`**, extracted verbatim from the live file:

```php
<?php
// includes/i18n/en/valparaiso.php
return [
    'meta_title' => 'Valparaiso Wine Tour from Santiago with Viña del Mar',
    'meta_description' => 'Full-day Valparaíso & Viña del Mar tour from Santiago with Casablanca Valley wine tasting. Hotel pickup, small groups, free cancellation.',
    'product_name' => 'Valparaiso Wine Tour from Santiago: Viña del Mar & Casablanca Valley',
    'hero_h1' => 'Valparaiso Wine Tour from Santiago: Viña del Mar & Casablanca Valley',
    'h2_overview' => 'Tour Overview & Highlights',
    'overview_intro' => 'No trip to Santiago is complete without seeing the colorful confection of Valparaiso and Viña del Mar. Let a guide organize transport and activities on a hassle-free excursion that gives a comprehensive introduction to the UNESCO World Heritage sites. Travel with ease between the highlights of Valparaiso and Viña del Mar, and receive personalized attention in a small group limited to 15. For added convenience, hotel pickup and drop-off are included.',
    'what_to_expect_heading' => 'What to expect.',
    'what_to_expect' => [
        'Your tour begins with hotel pickup in Santiago. Board a coach destined for the coastal towns of Valparaiso and Viña del Mar. In Valparaiso, a UNESCO World Heritage Site, embark on a panoramic tour of the port and Plaza Sotomayor (Sotomayor Square).',
        'Then, ascend to the summit of the Concepción and Alegre hills by traditional elevators. Break for lunch and enjoy Chilean cuisine at your own expense.',
        'The other stop is Viña del Mar, known as the Garden City for its plethora of greenery. Visit a coastal spot to observe sea lions before traveling to your final stop, the Casablanca Valley.',
        'Here, you can sample wines at a vineyard before returning to Santiago in the early evening.',
        'Your experience concludes with a drop-off at your original departure point.',
    ],
    'whats_included_heading' => "What's included",
    'whats_included' => [
        'Hotel pickup and drop-off (Pick up time will be delivered the night before the tour)',
        'Professional and expert tour guide',
        'Wine tasting',
        'Entry/Admission - Winery',
        'One Funicular Ride in Valparaiso',
        'Live coordination via WhatsApp with guide. (Recommended the use of WhatsApp)',
    ],
    'whats_not_included_heading' => "What's not included",
    'whats_not_included' => [
        'Airport pickup and drop-off (inquire about extra fee)',
        'Lunch',
        'Gratuities',
    ],
    'departure_return' => [
        'heading' => 'Departure and return',
        'start_label' => 'Start:',
        'start' => 'Multiple pickup locations offered.',
        'pickup_details_label' => 'Pickup details',
        'pickup_details' => 'Pick ups are available from central locations in the following districts: Las Condes, Vitacura, Providencia, Santiago Centro, Recoleta, Airport Area.',
        'pickup_note' => 'If your hotel is not in the available areas please send us the location and we will evaluate if it is possible to pick up; otherwise we will provide a meeting point at the nearest place from your location.',
        'hotel_pickup_label' => 'Hotel pickup offered',
        'hotel_pickup' => 'During checkout you will be able to select from the list of included hotels.',
        'end_label' => 'End:',
        'end' => 'This activity ends back at the meeting point.',
    ],
    'additional_info_heading' => 'Additional Information',
    'additional_info' => [
        'Confirmation will be received at time of booking',
        'Most travelers can participate',
        'Minimum numbers apply (4 people). There is a possibility of cancellation after confirmation if there are not enough passengers to meet requirements. In the event of this occurring, you will be offered an alternative or full refund',
        "This experience requires good weather. If it's canceled due to poor weather, you'll be offered a different date or a full refund.",
        'This tour/activity will have a maximum of 15 travelers',
    ],
    'cancellation_heading' => 'Cancellation policy',
    'cancellation_intro' => 'For a full refund, you must cancel at least 24 hours before the experience start time.',
    'cancellation_bullets' => [
        "If you cancel less than 24 hours before the experience's start time, the amount you paid will not be refunded.",
        "Any changes made less than 24 hours before the experience's start time will not be accepted.",
        "Cut-off times are based on the experience's local time.",
        "This experience requires a minimum number of travelers. If it's canceled because the minimum isn't met, you'll be offered a different date/experience or a full refund.",
    ],
    'timeline_heading' => 'Detailed timeline for your tour',
    'timeline_subtext' => 'take a look',
    'meeting_point_location' => 'Santiago',
    'meeting_point_title' => 'Meeting point',
    'meeting_point_desc' => 'Pick up at your location in Santiago City',
    'itinerary' => [
        ['duration' => '5 minutes', 'icon' => 'icon-camera-alt', 'location' => 'Viña del Mar', 'title' => 'Flower Clock', 'desc' => 'Flowery garden that houses the famous clock.'],
        ['duration' => '10 minutes', 'icon' => 'icon-user', 'location' => 'Viña del Mar', 'title' => 'Moai del Ahu', 'desc' => 'Genuine Moai brought from Easter Island in 1951; part of the Fonck Museum collection.'],
        ['duration' => '15 minutes', 'icon' => 'icon_set_2_icon-108', 'location' => 'Viña del Mar', 'title' => 'Avenida Peru', 'desc' => 'Oceanview overlooking the coast of Viña del Mar and Cerro Castillo.'],
        ['duration' => '20 minutes', 'icon' => 'icon_set_3_restaurant-7', 'location' => 'Valparaiso', 'title' => 'Caleta Portales', 'desc' => 'Fish market where you can watch sea lions and touch the Pacific Ocean.'],
        ['duration' => '15 minutes', 'icon' => 'icon-monument', 'location' => 'Valparaiso', 'title' => 'Sotomayor Square', 'desc' => 'Main civic center with Navy HQ and Monument to the Pacific War heroes.'],
        ['duration' => '5 minutes', 'icon' => 'icon_set_1_icon-28', 'location' => 'Valparaiso', 'title' => 'Ascensor el Peral', 'desc' => 'Historic funicular included in this tour (ticket included).'],
        ['duration' => '10 minutes', 'icon' => 'icon-eye', 'location' => 'Valparaiso', 'title' => 'Paseo Yugoslavo', 'desc' => 'Famous walkway with spectacular views of the bay and colorful hills.'],
        ['duration' => '10 minutes', 'icon' => 'icon_set_1_icon-4', 'location' => 'Valparaiso', 'title' => 'Palacio Baburizza', 'desc' => 'Fine art museum in an old mansion with superb hill and bay views.'],
        ['duration' => '5 minutes', 'icon' => 'icon-home-1', 'location' => 'Valparaiso', 'title' => 'Pasaje Bavestrello', 'desc' => 'Historic stairs built by the Italian community as a shortcut up the hill.'],
        ['duration' => '15 minutes', 'icon' => 'icon-brush', 'location' => 'Valparaiso', 'title' => 'Galvez Inc. Arte Contemporaneo', 'desc' => 'Gateway to the wall art alleyways — a labyrinth of surprising street art.'],
        ['duration' => '30 minutes', 'icon' => 'icon-picture', 'location' => 'Valparaiso', 'title' => 'Cerro Alegre & Cerro Concepcion', 'desc' => 'Walking tour past Victorian & German Tudor architecture, vintage shops, and more.'],
        ['duration' => '15 minutes', 'icon' => 'icon-eye', 'location' => 'Valparaiso', 'title' => 'Paseo Atkinson', 'desc' => 'Historic walkway with sea views; the oldest neighborhood on Concepción hill.'],
        ['duration' => '5 minutes', 'icon' => 'icon-brush', 'location' => 'Valparaiso', 'title' => 'Piano Staircase', 'desc' => "Iconic piano-themed mural on one of Valparaíso's many stairways."],
        ['duration' => '30 minutes', 'icon' => 'icon-wine', 'location' => 'Casablanca', 'title' => 'Winery', 'desc' => 'Enjoy a wine sample in one of the beautiful wineries of this prestigious valley.'],
    ],
    'return_location' => 'Santiago',
    'return_title' => 'Return to the starting point',
    'return_desc' => 'Drop-off at your location in Santiago City.',
    'see_more_button' => 'See more',
    'faq' => [
        ['q' => "What's included in the Valparaíso tour?", 'a' => "Hotel pickup and drop-off, a professional guide, one funicular ride up Valparaíso's hills, and a wine tasting at a Casablanca Valley winery."],
        ['q' => 'Is lunch included?', 'a' => "No - you'll have free time for lunch in Valparaíso at your own cost."],
        ['q' => 'How big are the groups?', 'a' => 'Departures are limited to 15 travelers, with a minimum of 4 required to run.'],
        ['q' => 'What will I see in Viña del Mar?', 'a' => "A stop to see sea lions along the coast, plus the city's well-known gardens, before continuing to the Casablanca Valley for wine tasting."],
        ['q' => "What's the cancellation policy?", 'a' => "Free cancellation up to 24 hours before the tour's start time; cancellations made inside that window aren't refunded."],
        ['q' => 'Can I book this directly instead of through Viator or Tripadvisor?', 'a' => "Yes - this is the same tour you may see listed on Viator or Tripadvisor. Booking directly on this page means working straight with the local team that runs it, with no reseller in between."],
    ],
];
```

- [ ] **Step 2: Create `includes/i18n/es/valparaiso.php`** with a full Spanish translation, following the Task 6 glossary and register, including a Spanish version of the reseller FAQ (`¿Puedo reservar directamente en lugar de a través de Viator o Tripadvisor?`).

- [ ] **Step 3: Wire the page**, following Task 6 Step 3's pattern (`$page_canonical_es = 'https://stampstour.com/es/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca'`). Preserve the `sameAs` Tripadvisor link in `render_tour_product_schema()` unchanged - it's the same real listing regardless of page language.

- [ ] **Step 4: Verify EN unchanged, ES renders, hreflang correct, schema correct (including the Tripadvisor `sameAs` still present on the Spanish version).**

- [ ] **Step 5: Commit**

```bash
git add valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php includes/i18n/en/valparaiso.php includes/i18n/es/valparaiso.php
git commit -m "Add Spanish translation for the Valparaiso tour page"
```

---

## Task 10: Cruise Transfer — extract, translate, wire

**Files:**
- Create: `includes/i18n/en/cruise.php`
- Create: `includes/i18n/es/cruise.php`
- Modify: `cruise-transfer.php`

**Interfaces:** Different shape from Tasks 6-9 - this page has no static server-rendered itinerary (`#timelineContainer` is populated client-side by `js/transfer.js` based on the pickup/dropoff selection, not from `$t`), and its FAQ array already has 11 entries (largest of the 5). Still uses `render_tour_faq()`/`render_tour_product_schema()` identically.

- [ ] **Step 1: Create `includes/i18n/en/cruise.php`**, extracted verbatim:

```php
<?php
// includes/i18n/en/cruise.php
return [
    'meta_title' => 'San Antonio & Valparaíso Shore Excursion | Santiago Cruise Transfer',
    'meta_description' => 'Pre-cruise or post-cruise shore excursion from San Antonio or Valparaíso, with Santiago transfer, guided sightseeing, and Casablanca Valley wine tasting on disembarkation day or before boarding.',
    'product_name' => 'San Antonio & Valparaíso Shore Excursion with Santiago Cruise Transfer',
    'hero_h1' => 'San Antonio & Valparaíso Shore Excursion with Santiago Cruise Transfer',
    'description_heading' => 'San Antonio & Valparaíso Shore Excursion: Pre-Cruise and Post-Cruise Transfer with Wine Tasting',
    'description_body' => "Whether it's disembarkation day and you're stepping off your ship in San Antonio or Valparaíso for good, or you need a pre-cruise transfer from Santiago to reach your ship before boarding, turn this one-way trip into a seamless, value-packed experience by pairing it with curated stops in Chile's most iconic wine region and port cities. Instead of spending your limited time in port stuck in traffic or juggling separate bookings, a single small-group service handles luggage, timing, and door-to-dock logistics while adding guided sightseeing and wine tasting en route—so you arrive at your next stop having already sampled the local culture, scenery, and flavors.",
    'timeline_heading' => 'Detailed timeline for your tour',
    'timeline_subtext' => 'select pick up and drop-off to see',
    'whats_included_heading' => "What's included",
    'whats_included' => [
        'Small-group transport (air-conditioned van)',
        'Professional, bilingual tour guide',
        'Valparaíso walking highlights',
        'Wine tasting in Casablanca (per itinerary)',
        'Pickup & luggage handling',
    ],
    'whats_not_included_heading' => 'Not included',
    'whats_not_included' => [
        'Meals and personal expenses',
        'Gratuities (optional)',
        'Additional tastings not mentioned',
    ],
    'departure_return' => [
        'heading' => 'Departure and return',
        'start_label' => 'Start:',
        'start' => 'Multiple pickup locations offered.',
        'pickup_details_label' => 'Pickup details',
        'pickup_details' => 'Pickups from Santiago hotels/Airbnbs or cruise terminal (depending on route). Exact time is confirmed the evening before.',
        'hotel_pickup_label' => 'Hotel pickup offered',
        'hotel_pickup' => "Available in common tourist areas (Las Condes, Vitacura, Providencia, Santiago Centro, Airport area). If you're outside these areas, we'll arrange a nearby meeting point.",
        'end_label' => 'End:',
        'end' => 'Drop-off at Valparaíso Passenger Terminal (VTP), San Antonio Passenger Terminal (DP WORLD) Santiago Airport (SCL), or your hotel per selected route. Typical arrival: ~2:00–2:30 PM (to Passenger Terminal) or ~5:00 PM (to Santiago), subject to traffic/port ops.',
    ],
    'additional_info_heading' => 'Additional information',
    'additional_info' => [
        'Luggage travels in the same van; standard suitcase + carry-on per person fits.',
        'Funicular rides may pause for maintenance or weather.',
        'Small-group service; child seats on request (notify in advance).',
        'Route order can shift due to traffic, port schedules, or winery slots.',
    ],
    'cancellation_heading' => 'Cancellation policy',
    'cancellation_intro_label' => 'Flexible:',
    'cancellation_intro' => 'Full refund for cancellations up to 24 hours before the experience start time.',
    'cancellation_bullets' => [
        'Changes within 24 hours may not be accepted.',
        'No-show is non-refundable.',
        'If weather/operations force a cancellation, you can reschedule or receive a full refund.',
    ],
    'faq' => [
        ['q' => 'Can I combine my cruise transfer with sightseeing?', 'a' => 'Yes - every transfer between Santiago and the port includes a stop in Valparaíso and a wine tasting in the Casablanca Valley, so you see the region instead of just sitting in traffic.'],
        ['q' => 'What pickup and drop-off points are available?', 'a' => 'Pickup from the San Antonio or Valparaíso cruise terminals, a Santiago hotel, or Santiago Airport (SCL); drop-off works the same way in reverse, with typical arrival around 2:00-2:30 PM at the passenger terminal or 5:00 PM in Santiago.'],
        ['q' => 'Will my luggage fit?', 'a' => 'Yes - luggage travels in the same van as passengers, and a standard suitcase plus a carry-on per person fits comfortably.'],
        ['q' => 'Is lunch included?', 'a' => 'No - meals and personal expenses are not included, only the wine tasting stop in Casablanca.'],
        ['q' => "What's the cancellation policy?", 'a' => "Full refund for cancellations made at least 24 hours before the transfer's start time; no-shows are non-refundable."],
        ['q' => 'Do we stop at the historic funicular in Valparaíso?', 'a' => "Yes - the transfer includes a ride on one of Valparaíso's historic funiculars as part of the included sightseeing stop."],
        ['q' => "What happens if the funicular isn't running?", 'a' => 'Funiculars occasionally pause for maintenance or weather; if that happens on your transfer day, your guide will adjust the stop accordingly.'],
        ['q' => 'Is this a private or shared transfer?', 'a' => "It's a small-group service, not a large bus tour. If you're traveling with a child, let us know in advance so we can arrange a child seat."],
        ['q' => 'Can I book this as a shore excursion from my cruise ship?', 'a' => "This is a one-way transfer, not a same-day round trip back to your ship. If your cruise is ending, we pick you up at the San Antonio or Valparaíso terminal on disembarkation day, add guided sightseeing and wine tasting, and drop you in Santiago. If your cruise is starting, it runs the other way: pickup in Santiago, sightseeing and wine tasting, then drop-off at the port in time to board."],
        ['q' => "What's the difference between a pre-cruise and post-cruise transfer?", 'a' => 'A pre-cruise transfer picks you up in Santiago (hotel or airport) and drops you at your ship in San Antonio or Valparaíso before boarding. A post-cruise transfer picks you up at the cruise terminal on disembarkation day and takes you into Santiago. Either way, the same guided sightseeing and Casablanca wine tasting are included en route.'],
        ['q' => 'Can I book this directly instead of through Viator, Expedia, or Travelocity?', 'a' => 'Yes - this is the same shore excursion listed on Viator, Expedia, and Travelocity. Booking directly on this page means working straight with the local team that runs it, with no reseller in between.'],
    ],
];
```

- [ ] **Step 2: Create `includes/i18n/es/cruise.php`** with a full Spanish translation of every key, following the Task 6 glossary and register - including a careful Spanish rendering of the one-way-vs-round-trip disambiguation FAQ (this is the page's most safety-critical piece of copy - a mistranslation that implies round-trip could cause a real customer to miss their ship's departure, so this specific answer needs extra care and should be double-checked in the native-speaker review pass, not just spot-checked).

- [ ] **Step 3: Wire `cruise-transfer.php`.** This page's structure differs slightly from Tasks 6-9's pattern:
  - `$lang`/`$t` require goes at the very top, before the existing `$expNames`/`$prices` price-fetching block (unaffected by language).
  - `$tour_faqs = $t['faq'];` replaces the hardcoded array (same as Tasks 6-9).
  - `$page_canonical_es = 'https://stampstour.com/es/cruise-transfer.php';` (note the `.php` - this page has no clean-URL rewrite, per the spec's explicit non-goal).
  - `render_tour_product_schema()`'s `'name'` becomes `$t['product_name']`.
  - Hero H1, the Description section's `<h4>`/`<p>`, `What's included`/`Not included` lists, Departure/Additional info/Cancellation sections all swap to `$t[...]` lookups identically to Tasks 6-9's pattern - **except** the itinerary/timeline section, which stays untouched (it's rendered by `js/transfer.js` client-side, not from this file, and is out of scope for this task - the timeline's dynamic stop content comes from a different data source entirely and would need its own follow-up task if it needs translation later).
  - The booking sidebar's `<option>` labels (`San Antonio (Cruise Port)`, `Valparaíso (Cruise Port)`, `Hotel in Santiago`, `Santiago Airport (SCL)`) and form labels (`Pick-up location`, `Adults (12+)`, etc.) are also out of scope for this task - they're booking-flow UI, not marketing copy, and translating the booking form is a larger, separate concern (the form posts to `submit.php`, which would need its own review) not covered by this spec.

- [ ] **Step 4: Verify EN unchanged, ES renders (description/FAQ/included sections), hreflang correct (`/es/cruise-transfer.php`), schema correct.**

- [ ] **Step 5: Commit**

```bash
git add cruise-transfer.php includes/i18n/en/cruise.php includes/i18n/es/cruise.php
git commit -m "Add Spanish translation for the cruise transfer page (marketing copy only - booking form and dynamic itinerary out of scope)"
```

---

## Task 11: Homepage — extract, translate, wire

**Files:**
- Create: `includes/i18n/en/home.php`
- Create: `includes/i18n/es/home.php`
- Modify: `index.php`

**Interfaces:** Smaller schema - `meta_title`/`meta_description`/`og_title`/`og_description`, hidden H1, hero title/subtitle/CTA, `travel_with_us` section, 5 tour card titles, `why_choose_us` section (4 cards).

- [ ] **Step 1: Create `includes/i18n/en/home.php`**, extracted verbatim:

```php
<?php
// includes/i18n/en/home.php
return [
    'meta_title' => "Stamp's Tour | Santiago Day Tours: Valparaíso, Maipo & Andes",
    'meta_description' => 'Daily small-group and private day tours from Santiago: Valparaíso wine tasting, Maipo Valley, the Andes, city tours & cruise transfers. Hotel pickup included.',
    'og_title' => "Stamp's Tour - Discover Chile",
    'og_description' => "Daily tours to Valparaíso, Maipo Wine Valley, Andes & Santiago. Book your curated experience with Stamp's Tour!",
    'hidden_h1' => "Santiago Day Tours: Valparaíso, Maipo Wine Valley, the Andes & More | Stamp's Tour",
    'hero_title' => 'Discover Chile',
    'hero_subtitle' => 'Daily tours, expert local guides,<br class="d-md-none"> unforgettable experiences',
    'hero_cta' => 'EXPLORE OUR TOURS',
    'travel_with_us_heading' => 'Travel with Us',
    'travel_with_us_body' => "Whether you're sipping wine in Maipo Valley, exploring the vibrant hills of Valparaíso, or reaching the heights of the Andes, Stamp's Tour offers curated experiences designed for curious, adventurous travelers. Our expert guides, comfortable transport, and small group tours ensure an unforgettable day, every day.",
    'card_valparaiso' => 'Valparaíso',
    'card_maipo' => 'Maipo Wine Tour',
    'card_andes' => 'Andes',
    'card_santiago' => 'Santiago',
    'card_cruise' => 'Cruise Transfer ↔ Santiago with Valparaiso Tour & Casablanca Wine Tasting',
    'why_choose_heading' => 'Why Choose Us',
    'why_choose' => [
        ['title' => 'Expert Local Guides', 'body' => "Our bilingual guides are passionate storytellers who bring Chile's culture and history to life."],
        ['title' => 'Curated Experiences', 'body' => 'From iconic landmarks to hidden gems, every tour is handpicked for authenticity and depth.'],
        ['title' => 'Comfort & Safety', 'body' => 'Modern, air-conditioned vehicles and attention to every detail make travel easy and worry-free.'],
        ['title' => 'Small Group Tours', 'body' => 'We keep groups small to ensure a more personal, immersive, and flexible experience.'],
    ],
];
```

- [ ] **Step 2: Create `includes/i18n/es/home.php`** with a full Spanish translation, following the Task 6 glossary/register.

- [ ] **Step 3: Wire `index.php`.**
  - `$lang`/`$t` require at the very top, before the existing `$page_title = ...` line.
  - `$page_title = $t['meta_title'];`, `$page_description = $t['meta_description'];`, `$page_og['title'] = $t['og_title'];`, `$page_og['description'] = $t['og_description'];`.
  - `$page_canonical = 'https://stampstour.com/';` stays; add `$page_canonical_es = 'https://stampstour.com/es/';`.
  - `<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">`.
  - Hidden H1, hero title/subtitle/CTA, "Travel with Us" heading/body, all 5 tour card `<h3>` titles, and the 4 "Why Choose Us" cards each swap to `$t[...]` lookups.
  - Each tour card's `<a href="...">` link needs to point at the Spanish URL when `$lang === 'es'` - e.g. `href="<?= $lang === 'es' ? '/es/valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca' : 'valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php' ?>"`, following the same per-link ternary pattern as Task 3's nav.

- [ ] **Step 4: Verify EN unchanged, ES renders (hero, cards, why-choose-us, tour card links pointing at `/es/...`), hreflang correct.**

- [ ] **Step 5: Commit**

```bash
git add index.php includes/i18n/en/home.php includes/i18n/es/home.php
git commit -m "Add Spanish translation for the homepage"
```

---

## Task 12: Site-wide verification pass

**Files:** None modified - verification only.

- [ ] **Step 1: Full click-through of the language switcher**

For each of the 6 pages (homepage + 5 tours), load the English version, click the `ES` switcher link, confirm it lands on the correct `/es/...` URL for *that specific page* (not just the Spanish homepage), then click `EN` from there and confirm it lands back on the original page.

- [ ] **Step 2: hreflang audit across all 12 URLs (6 pages × 2 languages)**

```bash
for url in "" "maipo-valley-wine-tour-santiago" "valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca" "discover-santiago-city-tour" "portillo-inca-lagoon-andes-mountains-vineyard"; do
  echo "=== $url ==="
  curl -s "http://127.0.0.1:8199/${url:-index.php}" | grep -c hreflang
  curl -s "http://127.0.0.1:8199/${url:-index.php}?lang=es" | grep -c hreflang
done
```

Expected: `3` for every call (en/es/x-default), on every page, in both languages.

- [ ] **Step 3: Sitemap check**

```bash
curl -s http://127.0.0.1:8199/sitemap.xml | grep -c "/es/"
```

Expected: at least 5 (the migrated pages), once each page's `$page_canonical_es` is wired - note the sitemap generator itself (Task 5) only handles blog URLs; the static page list in `sitemap-generator.php` needs its own small update in this task to add the 5 `/es/` tour URLs + `/es/` homepage alongside the existing English ones, following the same array-literal pattern already used for the English `$staticPages` list.

- [ ] **Step 4: Full-site EN regression check**

Load all 6 pages once more with no `?lang` param and confirm every one renders with English text, matching Task 6-11's individual "EN unchanged" checks - this step is the final cross-page confirmation after all 6 pages have been migrated, not a repeat of a single page's check.

- [ ] **Step 5: Flag remaining native-review requirement**

This step produces no code change - it's a checklist reminder for whoever runs this plan: every `includes/i18n/es/*.php` file created in Tasks 6-11 needs a native-Spanish-speaker review pass before this ships to production, per the spec's Risks section. Do not consider this plan "done" until that review has happened, even though every technical/testing step above will pass without it.
