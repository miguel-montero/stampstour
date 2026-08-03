# SEO Destination-Guide Content Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Stamps Tour a repeatable pipeline — two Claude Code skills plus one CLI script — that takes a blog post from "what should we write about" (grounded in real Search Console demand) through a human-reviewed draft to a `status='draft'` row in the existing `blog_posts` table.

**Architecture:** `seo-keyword-research` (skill) mines Search Console + web search for a long-tail opportunity and writes a brief file. `seo-post-writer` (skill) turns an approved brief into a full draft file with image placeholders. `insert-blog-draft.php` (plain script) validates a human-finished draft (required fields present, referenced images actually exist on disk, slug not already taken) and inserts it into `blog_posts` as a draft, reusing the site's existing slug-generation logic and DB connection convention.

**Tech Stack:** PHP 8.5 (procedural, mysqli, no framework — matches the existing codebase), Claude Code skill files (Markdown + YAML frontmatter), no test framework is installed in this repo (confirmed: no PHPUnit, no `tests/` convention) so verification uses `php -l`, small self-contained assertion scripts run via the `php` CLI, and manual functional checks against the local dev DB — the same approach already used in this repo's other specs (e.g. the GA4 checkout-funnel-tracking plan).

## Global Constraints

- Destination-guide content only, for the four existing regions (Santiago, Valparaíso, Maipo Valley, Andes) — no other content types.
- English only (the site is English-only per its `hreflang` tags).
- No automated publishing: every inserted row must have `status='draft'` and `published_at IS NULL`. A human always flips it to published via the existing `admin/blog.php`/`blog-edit.php`.
- Skills never source or generate images — the human supplies their own photos before a draft is inserted.
- Follow existing codebase conventions exactly: `declare(strict_types=1);`, mysqli prepared statements, the `db_config.php` require-path convention (`STAMP/../db_config.php`, i.e. one level above `STAMP/`), and image paths relative to the `STAMP/` web root (matching the existing `/img/Tours/<tour>/` convention).
- No new dependencies, no new test framework — this repo has none and none is being introduced.

---

## Task 1: `seo-keyword-research` skill

**Files:**
- Create: `STAMP/.claude/skills/seo-keyword-research/SKILL.md`
- Modify: `STAMP/.gitignore`

**Interfaces:**
- Produces: `content-drafts/briefs/<slug>-brief.md`, a Markdown file with a fixed set of headed sections (`Target keyword`, `Search intent`, `Supporting phrases/questions`, `Link to tour`, `Competitive note`) that Task 2's skill reads as input.

- [ ] **Step 1: Add the `content-drafts/` runtime-output directory to `.gitignore`**

This mirrors the existing "Runtime-generated data, not source" section in `STAMP/.gitignore` (which already excludes `/tickets/`, `/pdfs/`, `/logs/`, etc.) — draft content lives in the CMS DB once published, not in git history.

Edit `STAMP/.gitignore`, adding to the existing runtime-generated-data section:

```
# Runtime-generated data, not source
/tickets/
/pdfs/
/logs/
/storage/*.log
/error_log

# Working files for the seo-keyword-research / seo-post-writer skill pipeline -
# briefs and drafts are staging area, not source; the CMS DB is the source of
# truth once a post is inserted
/content-drafts/
```

- [ ] **Step 2: Write the skill file**

Create `STAMP/.claude/skills/seo-keyword-research/SKILL.md`:

```markdown
---
name: seo-keyword-research
description: Find long-tail, high-intent keyword opportunities for Stamps Tour destination guides using live Search Console data plus web research
version: "1.0.0"
author: Stamps Tour
platforms: []
user-invocable: true
argument-hint: "[region or topic]"
allowed-tools:
  - mcp__search-console-mcp__get_search_analytics
  - mcp__search-console-mcp__get_advanced_search_analytics
  - mcp__search-console-mcp__get_search_by_page_query
  - mcp__search-console-mcp__get_performance_overview
  - mcp__analytics-mcp__run_report
  - WebSearch
  - WebFetch
  - Write
  - Bash
---

# SEO Keyword Research

Find one well-researched, long-tail, high-intent keyword opportunity for a Stamps Tour destination-guide blog post, grounded in real Search Console demand for stampstour.com.

## Usage

`/seo-keyword-research` — explore all four regions (Santiago, Valparaíso, Maipo Valley, Andes) for opportunities
`/seo-keyword-research Valparaíso` — focus the search on a specific region or topic

## Steps

### 1. Mine Search Console for content gaps

Use `mcp__search-console-mcp__get_search_analytics` (or `get_search_by_page_query`) for `stampstour.com` over the last ~3-6 months, grouped by query. Look for queries that:
- Already have meaningful impressions (real demand), and
- Have weak CTR or sit at position ~8-30 (the site isn't winning the click), and
- Aren't already well-served by an existing tour page (`discover-santiago-city-tour.php`, `maipo-valley-wine-tour-santiago.php`, `portillo-inca-lagoon-andes-mountains-vineyard.php`, `valparaiso-port-and-vina-del-mar-with-wine-tasting-in-casablanca.php`) or blog post.

If a seed region/topic was given as an argument, filter to queries relevant to it; otherwise consider all four regions.

### 2. Expand into long-tail candidates

For each promising query from step 1, use `WebSearch` to:
- Find how it naturally expands into longer, more specific phrasings (e.g. "Valparaíso" → "best day trip to Valparaíso from Santiago without a tour").
- Find related "people also ask"-style questions.
- Do a quick scan of what currently ranks for the candidate keyword — note whether it's dominated by large travel sites (harder to compete) or thin/outdated content (an opportunity), and what angles the top results miss.

### 3. Shortlist and recommend

Present a shortlist of 3-5 candidates, one line each: the keyword and why it's an opportunity. Mark one as the clear recommendation. Only fully research the recommended pick in the next step — don't deep-dive the others, since the user may pick a different one and that research would be wasted.

Wait for the user to confirm the recommended pick or choose a different one from the shortlist before continuing to step 4.

### 4. Write the brief

Once a keyword is confirmed, write a brief to `content-drafts/briefs/<slug>-brief.md` (create the directory if it doesn't exist), where `<slug>` is a URL-safe slug derived from the keyword:

```markdown
# Brief: <keyword>

**Target keyword:** <the long-tail keyword>
**Search intent:** <informational | commercial | navigational — one sentence on what the searcher wants>

**Supporting phrases/questions:**
- <related phrase or question 1>
- <related phrase or question 2>
- <related phrase or question 3>

**Link to tour:** <which of the four tour pages this post should link to, and why>

**Competitive note:** <what currently ranks for this keyword, and what gap/angle this post should fill>
```

This brief is the input to the `seo-post-writer` skill.
```

- [ ] **Step 3: Verify the frontmatter is well-formed**

Run:

```bash
awk '/^---$/{c++; if(c==2) exit} c==1' STAMP/.claude/skills/seo-keyword-research/SKILL.md | grep -E '^(name|description|user-invocable|allowed-tools):'
```

Expected output includes all four lines (`name:`, `description:`, `user-invocable:`, `allowed-tools:`), confirming the frontmatter block is present and has the fields the Claude Code harness needs. This is a structural check only — actual behavior (does it produce a sane brief?) is verified by hand in Task 6.

- [ ] **Step 4: Commit**

```bash
git add STAMP/.claude/skills/seo-keyword-research/SKILL.md STAMP/.gitignore
git commit -m "Add seo-keyword-research skill"
```

---

## Task 2: `seo-post-writer` skill

**Files:**
- Create: `STAMP/.claude/skills/seo-post-writer/SKILL.md`

**Interfaces:**
- Consumes: a brief file matching Task 1's output format (`Target keyword`, `Search intent`, `Supporting phrases/questions`, `Link to tour`, `Competitive note` sections).
- Produces: `content-drafts/<slug>.md`, a Markdown file with a frontmatter block (`title`, `slug`, `excerpt`, `meta_title`, `meta_description`, `featured_image`) followed by a body of `##`/`###` headings, plain paragraphs, `[IMAGE: ...]` placeholders, and (once the human edits it) raw `<img>` tags — this is exactly the file format `insert-blog-draft.php` (Task 5) parses.

- [ ] **Step 1: Write the skill file**

Create `STAMP/.claude/skills/seo-post-writer/SKILL.md`:

```markdown
---
name: seo-post-writer
description: Turn an approved keyword brief into a full destination-guide blog post draft, ready for photo review before publishing
version: "1.0.0"
author: Stamps Tour
platforms: []
user-invocable: true
argument-hint: "<path-to-brief.md> [notes/ideas]"
allowed-tools:
  - Read
  - Write
---

# SEO Post Writer

Turn a keyword brief (from `seo-keyword-research`, or written by hand) into a full destination-guide blog post draft, staged as a file for human review before anything touches the database.

## Usage

`/seo-post-writer content-drafts/briefs/<slug>-brief.md` — write a draft from a brief
`/seo-post-writer content-drafts/briefs/<slug>-brief.md "mention it's a great day trip from Santiago"` — include your own angle/notes in the draft

## Steps

### 1. Read the brief

Read the brief file for: target keyword, search intent, supporting phrases/questions, which tour page to link to, and the competitive note. If the user supplied extra notes/ideas as an argument, treat those as must-include angles.

### 2. Write the draft

Write `content-drafts/<slug>.md` (create the `content-drafts/` directory if it doesn't exist), where `<slug>` matches the brief's target keyword slugified (lowercase, non-alphanumeric runs collapsed to a single `-`, no leading/trailing `-`). Target length: 1200-1800 words. Structure:

```markdown
---
title: <post title, working the target keyword in naturally>
slug: <slug>
excerpt: <150-160 character summary for the blog listing card>
meta_title: <under 60 characters>
meta_description: <under 155 characters>
featured_image: [IMAGE: <description of the hero shot needed> — save to /img/blog/<slug>/]
---

## Intro

<hook, target keyword worked in naturally within the first ~100 words>

## <H2 section title>

<content>

## <H2 section title>

<content>

## FAQ

### <question 1, drawn from the brief's supporting phrases/questions>

<answer>

### <question 2>

<answer>
```

Rules for the draft:
- Use 3-5 H2 sections beyond the intro and FAQ, each covering a distinct sub-topic relevant to the keyword (e.g. best time to visit, how to get there, top things to do, practical tips — adapt to what the specific keyword/intent calls for).
- Include a FAQ section with 2-4 questions pulled from the brief's supporting phrases/questions — these capture more long-tail queries and can win featured snippets.
- Work a call-to-action linking to the tour page named in the brief into the closing section's prose, not as a bolted-on line at the end.
- Wherever a photo would naturally break up the text (after the intro, and roughly every 2-3 sections), insert an inline placeholder on its own line: `[IMAGE: <description>]`. The user will replace each placeholder with a real `<img src="/img/blog/<slug>/<file>.jpg" alt="...">` tag once they've added their own photo to that path.
- Never fabricate specific facts (opening hours, prices, exact addresses) that aren't in the brief or well-established public knowledge — keep those general, since inserting wrong specifics is worse than omitting them.

### 3. Hand off

Tell the user the draft is ready at `content-drafts/<slug>.md`, and that next they should: edit the text as needed, drop their own photos into `/img/blog/<slug>/`, replace each `[IMAGE: ...]` placeholder with a real `<img>` tag — then run `php scripts/insert-blog-draft.php content-drafts/<slug>.md` to stage it as a draft in the CMS.
```

- [ ] **Step 2: Verify the frontmatter is well-formed**

```bash
awk '/^---$/{c++; if(c==2) exit} c==1' STAMP/.claude/skills/seo-post-writer/SKILL.md | grep -E '^(name|description|user-invocable|allowed-tools):'
```

Expected: all four lines present, same as Task 1's check.

- [ ] **Step 3: Commit**

```bash
git add STAMP/.claude/skills/seo-post-writer/SKILL.md
git commit -m "Add seo-post-writer skill"
```

---

## Task 3: Extract shared slug-generation helper

**Files:**
- Create: `STAMP/includes/blog-slug.php`
- Modify: `STAMP/admin/blog-edit.php:1-10`

**Interfaces:**
- Produces: `stamp_blog_slugify(string $s): string`, used by both `admin/blog-edit.php` (existing behavior, unchanged) and `scripts/insert-blog-draft.php` (Task 5) — a single source of truth so admin-created and script-created posts always slugify titles identically.

`admin/blog-edit.php` currently defines `stamp_blog_slugify()` inline (lines 6-10). The new `insert-blog-draft.php` script needs the exact same logic; duplicating the regex in two places would let them drift out of sync. Extract it once, used by both.

- [ ] **Step 1: Create the shared helper file**

Create `STAMP/includes/blog-slug.php`:

```php
<?php
declare(strict_types=1);

function stamp_blog_slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}
```

- [ ] **Step 2: Point `admin/blog-edit.php` at the shared helper**

Read `STAMP/admin/blog-edit.php` lines 1-10 first to confirm current content matches, then edit:

Old:
```php
<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';

function stamp_blog_slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}
```

New:
```php
<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require __DIR__ . '/../../db_config.php';
require __DIR__ . '/../includes/blog-slug.php';
```

- [ ] **Step 3: Verify syntax and behavior**

```bash
php -l STAMP/includes/blog-slug.php
php -l STAMP/admin/blog-edit.php
php -r "require 'STAMP/includes/blog-slug.php'; var_dump(stamp_blog_slugify('  Hello, World! Best Trip  ') === 'hello-world-best-trip');"
```

Expected: both `php -l` calls report "No syntax errors detected", and the `var_dump` line prints `bool(true)` — confirming the extracted function behaves identically to the original inline one.

- [ ] **Step 4: Commit**

```bash
git add STAMP/includes/blog-slug.php STAMP/admin/blog-edit.php
git commit -m "Extract stamp_blog_slugify() into a shared include"
```

---

## Task 4: Draft-parsing library

**Files:**
- Create: `STAMP/scripts/lib/draft-parser.php`
- Test: `STAMP/scripts/lib/draft-parser.test.php`

**Interfaces:**
- Consumes: nothing (pure functions operating on strings/arrays passed in).
- Produces, for Task 5 to use:
  - `stamp_parse_draft_file(string $path): array` — returns `['fields' => array<string,string>, 'body' => string]`; throws `RuntimeException` if the file can't be read or has no `--- ... ---` frontmatter block.
  - `stamp_markdown_to_html(string $markdown): string` — converts `##`/`###` headings and blank-line-separated paragraphs to HTML; lines already starting with `<` (e.g. a user-inserted `<img>` tag) pass through unchanged.
  - `stamp_extract_image_paths(string $featuredImage, string $bodyHtml): array` — returns a deduplicated, ordered list of every image path referenced (the `featured_image` frontmatter value plus every `<img src="...">` in the body).
  - `stamp_missing_image_paths(array $paths, string $siteRoot): array` — returns the subset of `$paths` that don't exist as a file under `$siteRoot`.
  - `stamp_missing_required_fields(array $fields, string $body): array` — returns the subset of `['title','slug','excerpt','meta_title','meta_description','content']` that are empty/missing (`content` refers to `$body`).

- [ ] **Step 1: Write the test script (will fail — nothing implemented yet)**

Create `STAMP/scripts/lib/draft-parser.test.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/draft-parser.php';

$failures = 0;
$checks = 0;

function stamp_check(string $label, $actual, $expected): void {
    global $failures, $checks;
    $checks++;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    } else {
        echo "PASS: $label\n";
    }
}

// stamp_parse_draft_file
$tmp = tempnam(sys_get_temp_dir(), 'draft');
file_put_contents($tmp, "---\ntitle: Test Title\nslug: test-title\n---\n\n## Heading\n\nSome text.\n");
$parsed = stamp_parse_draft_file($tmp);
stamp_check('parse: title field', $parsed['fields']['title'], 'Test Title');
stamp_check('parse: slug field', $parsed['fields']['slug'], 'test-title');
stamp_check('parse: body', $parsed['body'], "## Heading\n\nSome text.");
unlink($tmp);

// stamp_markdown_to_html
$html = stamp_markdown_to_html("## Heading\n\nFirst paragraph line one\nline two.\n\n### Sub\n\nAnother paragraph.\n\n<img src=\"/img/x.jpg\">\n");
stamp_check(
    'markdown_to_html',
    $html,
    "<h2>Heading</h2>\n<p>First paragraph line one line two.</p>\n<h3>Sub</h3>\n<p>Another paragraph.</p>\n<img src=\"/img/x.jpg\">"
);

// stamp_extract_image_paths
$paths = stamp_extract_image_paths(
    '/img/blog/x/hero.jpg',
    '<p>hi</p><img src="/img/blog/x/one.jpg"><img src=\'/img/blog/x/two.jpg\'>'
);
stamp_check('extract_image_paths', $paths, ['/img/blog/x/hero.jpg', '/img/blog/x/one.jpg', '/img/blog/x/two.jpg']);

$pathsEmpty = stamp_extract_image_paths('', '<p>no images</p>');
stamp_check('extract_image_paths: none', $pathsEmpty, []);

// stamp_missing_image_paths
$dir = sys_get_temp_dir() . '/stamp_test_' . uniqid();
mkdir($dir);
mkdir($dir . '/img');
file_put_contents($dir . '/img/exists.jpg', 'x');
$missing = stamp_missing_image_paths(['/img/exists.jpg', '/img/missing.jpg'], $dir);
stamp_check('missing_image_paths', $missing, ['/img/missing.jpg']);
unlink($dir . '/img/exists.jpg');
rmdir($dir . '/img');
rmdir($dir);

// stamp_missing_required_fields
$missingFields = stamp_missing_required_fields(['title' => 'T', 'slug' => 's'], '');
stamp_check('missing_required_fields', $missingFields, ['excerpt', 'meta_title', 'meta_description', 'content']);

$noMissing = stamp_missing_required_fields(
    ['title' => 'T', 'slug' => 's', 'excerpt' => 'e', 'meta_title' => 'mt', 'meta_description' => 'md'],
    'body text'
);
stamp_check('missing_required_fields: none missing', $noMissing, []);

echo "\n$checks checks, $failures failed.\n";
exit($failures > 0 ? 1 : 0);
```

- [ ] **Step 2: Run it to confirm it fails**

```bash
php STAMP/scripts/lib/draft-parser.test.php
```

Expected: a fatal error (`Failed opening required '.../draft-parser.php'`) since the file doesn't exist yet, or (once an empty file exists) `Call to undefined function` — either way, a hard failure, not a silent pass.

- [ ] **Step 3: Implement the library**

Create `STAMP/scripts/lib/draft-parser.php`:

```php
<?php
declare(strict_types=1);

function stamp_parse_draft_file(string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Could not read draft file: $path");
    }
    if (!preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $raw, $m)) {
        throw new RuntimeException("Draft file is missing the frontmatter (--- ... ---) block: $path");
    }
    $frontmatter = $m[1];
    $body = trim($m[2]);

    $fields = [];
    foreach (preg_split('/\r?\n/', $frontmatter) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        $fields[$key] = $value;
    }

    return ['fields' => $fields, 'body' => $body];
}

function stamp_markdown_to_html(string $markdown): string {
    $lines = preg_split('/\r?\n/', $markdown);
    $html = [];
    $paragraph = [];

    $flushParagraph = function () use (&$paragraph, &$html) {
        if (!empty($paragraph)) {
            $html[] = '<p>' . implode(' ', $paragraph) . '</p>';
            $paragraph = [];
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $flushParagraph();
            continue;
        }
        if (preg_match('/^###\s+(.*)$/', $trimmed, $m)) {
            $flushParagraph();
            $html[] = '<h3>' . $m[1] . '</h3>';
            continue;
        }
        if (preg_match('/^##\s+(.*)$/', $trimmed, $m)) {
            $flushParagraph();
            $html[] = '<h2>' . $m[1] . '</h2>';
            continue;
        }
        if ($trimmed[0] === '<') {
            $flushParagraph();
            $html[] = $trimmed;
            continue;
        }
        $paragraph[] = $trimmed;
    }
    $flushParagraph();

    return implode("\n", $html);
}

function stamp_extract_image_paths(string $featuredImage, string $bodyHtml): array {
    $paths = [];
    $featuredImage = trim($featuredImage);
    if ($featuredImage !== '') {
        $paths[] = $featuredImage;
    }
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $bodyHtml, $m)) {
        foreach ($m[1] as $src) {
            $paths[] = $src;
        }
    }
    return array_values(array_unique($paths));
}

function stamp_missing_image_paths(array $paths, string $siteRoot): array {
    $missing = [];
    foreach ($paths as $path) {
        $rel = ltrim($path, '/');
        $full = rtrim($siteRoot, '/') . '/' . $rel;
        if (!is_file($full)) {
            $missing[] = $path;
        }
    }
    return $missing;
}

function stamp_missing_required_fields(array $fields, string $body): array {
    $required = ['title', 'slug', 'excerpt', 'meta_title', 'meta_description'];
    $missing = [];
    foreach ($required as $key) {
        if (trim($fields[$key] ?? '') === '') {
            $missing[] = $key;
        }
    }
    if (trim($body) === '') {
        $missing[] = 'content';
    }
    return $missing;
}
```

- [ ] **Step 4: Run the test script to confirm it passes**

```bash
php STAMP/scripts/lib/draft-parser.test.php
```

Expected: every line reads `PASS: ...`, followed by `9 checks, 0 failed.`, and exit code `0` (`echo $?` after the command).

- [ ] **Step 5: Commit**

```bash
git add STAMP/scripts/lib/draft-parser.php STAMP/scripts/lib/draft-parser.test.php
git commit -m "Add draft-parsing library for the blog-post insert script"
```

---

## Task 5: `insert-blog-draft.php` CLI script

**Files:**
- Create: `STAMP/scripts/insert-blog-draft.php`

**Interfaces:**
- Consumes: `stamp_parse_draft_file`, `stamp_markdown_to_html`, `stamp_extract_image_paths`, `stamp_missing_image_paths`, `stamp_missing_required_fields` (Task 4); `stamp_blog_slugify` (Task 3); `$conn` (mysqli instance from `db_config.php`, existing).
- Produces: a `blog_posts` row with `status='draft'`, `published_at=NULL`; prints the new row's `id` and the relative admin edit URL to stdout; exits `1` with a message on `STDERR` for any validation failure.

- [ ] **Step 1: Write the script**

Create `STAMP/scripts/insert-blog-draft.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/lib/draft-parser.php';
require __DIR__ . '/../includes/blog-slug.php';

function stamp_insert_fail(string $message): void {
    fwrite(STDERR, "Error: $message\n");
    exit(1);
}

$path = $argv[1] ?? '';
if ($path === '') {
    stamp_insert_fail('Usage: php insert-blog-draft.php <path-to-draft.md>');
}
if (!is_file($path)) {
    stamp_insert_fail("Draft file not found: $path");
}

try {
    $parsed = stamp_parse_draft_file($path);
} catch (RuntimeException $e) {
    stamp_insert_fail($e->getMessage());
}

$fields = $parsed['fields'];
$bodyHtml = stamp_markdown_to_html($parsed['body']);

$missingFields = stamp_missing_required_fields($fields, $bodyHtml);
if (!empty($missingFields)) {
    stamp_insert_fail('Missing required field(s): ' . implode(', ', $missingFields));
}

$siteRoot = dirname(__DIR__);
$imagePaths = stamp_extract_image_paths($fields['featured_image'] ?? '', $bodyHtml);
$missingImages = stamp_missing_image_paths($imagePaths, $siteRoot);
if (!empty($missingImages)) {
    stamp_insert_fail("Missing image file(s):\n  " . implode("\n  ", $missingImages));
}

$slugSource = ($fields['slug'] ?? '') !== '' ? $fields['slug'] : $fields['title'];
$slug = stamp_blog_slugify($slugSource);
if ($slug === '') {
    stamp_insert_fail('Slug could not be generated from the title or slug field.');
}

require __DIR__ . '/../../db_config.php';

$check = $conn->prepare('SELECT id FROM blog_posts WHERE slug = ?');
$check->bind_param('s', $slug);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    stamp_insert_fail("That slug is already used by another post: $slug");
}
$check->close();

$title = $fields['title'];
$excerpt = $fields['excerpt'];
$featuredImage = $fields['featured_image'] ?? '';
$metaTitle = $fields['meta_title'];
$metaDescription = $fields['meta_description'];
$status = 'draft';

$stmt = $conn->prepare("
    INSERT INTO blog_posts (slug, title, excerpt, content, featured_image, meta_title, meta_description, status, published_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)
");
$stmt->bind_param(
    'ssssssss',
    $slug, $title, $excerpt, $bodyHtml, $featuredImage, $metaTitle, $metaDescription, $status
);

if (!$stmt->execute()) {
    if ($conn->errno === 1062) {
        stamp_insert_fail("That slug is already used by another post: $slug");
    }
    stamp_insert_fail('Database error: ' . $stmt->error);
}

$id = (int)$stmt->insert_id;
$stmt->close();

echo "Inserted draft post #$id ($slug)\n";
echo "Review at: admin/blog-edit.php?id=$id\n";
```

- [ ] **Step 2: Syntax check**

```bash
php -l STAMP/scripts/insert-blog-draft.php
```

Expected: "No syntax errors detected".

- [ ] **Step 3: Functional check — missing required field is rejected**

```bash
cat > /tmp/stamp-test-missing-field.md <<'EOF'
---
title: Test Post
slug: test-post-missing-field
excerpt: An excerpt.
meta_title: Test
---

## Section

Some content.
EOF
php STAMP/scripts/insert-blog-draft.php /tmp/stamp-test-missing-field.md; echo "exit: $?"
```

Expected: prints `Error: Missing required field(s): meta_description` to stderr, `exit: 1`. No row inserted (nothing to clean up).

- [ ] **Step 4: Functional check — missing image is rejected**

```bash
cat > /tmp/stamp-test-missing-image.md <<'EOF'
---
title: Test Post
slug: test-post-missing-image
excerpt: An excerpt.
meta_title: Test
meta_description: A description.
featured_image: /img/this-file-does-not-exist.jpg
---

## Section

Some content.
EOF
php STAMP/scripts/insert-blog-draft.php /tmp/stamp-test-missing-image.md; echo "exit: $?"
```

Expected: prints `Error: Missing image file(s):` followed by `  /img/this-file-does-not-exist.jpg`, `exit: 1`. No row inserted.

- [ ] **Step 5: Functional check — valid draft is inserted as a draft row, then clean up**

`STAMP/img/blog-1.jpg` already exists in the repo, so it doubles as a real image fixture:

```bash
cat > /tmp/stamp-test-valid.md <<'EOF'
---
title: Test Post For Pipeline Verification
slug: test-post-for-pipeline-verification
excerpt: An excerpt for the pipeline verification test post.
meta_title: Test Post
meta_description: A meta description for the pipeline verification test post.
featured_image: /img/blog-1.jpg
---

## Section One

Some content for section one.

## Section Two

Some content for section two.
EOF
cd STAMP && php scripts/insert-blog-draft.php /tmp/stamp-test-valid.md; echo "exit: $?"
```

Expected: prints `Inserted draft post #<id> (test-post-for-pipeline-verification)` and `Review at: admin/blog-edit.php?id=<id>`, `exit: 0`.

Then confirm the row and clean it up (this is a throwaway verification row, not real content):

```bash
mysql -u stampst1_user -p stampst1_stamptour -e "SELECT id, slug, status, published_at FROM blog_posts WHERE slug = 'test-post-for-pipeline-verification';"
mysql -u stampst1_user -p stampst1_stamptour -e "DELETE FROM blog_posts WHERE slug = 'test-post-for-pipeline-verification';"
```

Expected `SELECT` output: one row, `status='draft'`, `published_at` is `NULL`.

- [ ] **Step 6: Functional check — re-running against the same slug is rejected**

Re-run Step 5's insert twice in a row *before* deleting (i.e., run it once, then immediately again without cleanup) to confirm the pre-insert slug check catches the collision:

```bash
cd STAMP && php scripts/insert-blog-draft.php /tmp/stamp-test-valid.md
php scripts/insert-blog-draft.php /tmp/stamp-test-valid.md; echo "exit: $?"
mysql -u stampst1_user -p stampst1_stamptour -e "DELETE FROM blog_posts WHERE slug = 'test-post-for-pipeline-verification';"
```

Expected: the second call prints `Error: That slug is already used by another post: test-post-for-pipeline-verification` and `exit: 1`. Final `DELETE` cleans up the one row the first call inserted.

- [ ] **Step 7: Commit**

```bash
git add STAMP/scripts/insert-blog-draft.php
git commit -m "Add insert-blog-draft.php CLI script"
```

---

## Task 6: End-to-end manual verification

**Files:** none created or modified — this task exercises Tasks 1-5 together.

- [ ] **Step 1: Run the keyword research skill**

Invoke `/seo-keyword-research` (optionally with a region, e.g. `/seo-keyword-research Valparaíso`) in a live Claude Code session against this project. Confirm:
- It actually calls the `search-console-mcp` tools (not just web search).
- The shortlist it presents makes sense (real, plausible destination-guide angles, not generic filler).
- It writes `content-drafts/briefs/<slug>-brief.md` with all five required sections populated (no placeholder text left in).

- [ ] **Step 2: Run the post writer skill**

Invoke `/seo-post-writer content-drafts/briefs/<slug>-brief.md` using the brief from Step 1. Confirm:
- `content-drafts/<slug>.md` is created with a valid frontmatter block (all six fields present) and a body with an intro, 3-5 H2 sections, an FAQ section, and at least one `[IMAGE: ...]` placeholder.
- Word count is roughly in the 1200-1800 range.
- A CTA linking to one of the four tour pages appears in the closing section's prose.

- [ ] **Step 3: Simulate the human review step**

Manually edit `content-drafts/<slug>.md`: replace each `[IMAGE: ...]` placeholder with a real `<img src="/img/blog-1.jpg" alt="...">` tag (reusing the existing `STAMP/img/blog-1.jpg` fixture so no new binary needs to be added), so the file's image references all point to files that actually exist.

- [ ] **Step 4: Run the insert script and verify in the admin panel**

```bash
cd STAMP && php scripts/insert-blog-draft.php content-drafts/<slug>.md
```

Then open `admin/blog.php` in a browser (logged in) and confirm the new post appears in the list with status "draft", and that `admin/blog-edit.php?id=<id>` shows the correct title/excerpt/content/meta fields/image.

- [ ] **Step 5: Clean up the test row**

```bash
mysql -u stampst1_user -p stampst1_stamptour -e "DELETE FROM blog_posts WHERE slug = '<slug>';"
```

(Skip this step if the post from this run is actually good enough to keep and publish for real — in that case, leave it as a draft for the user to review and publish normally instead of deleting it.)
