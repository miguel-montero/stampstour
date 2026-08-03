# SEO Destination-Guide Content Pipeline

## Problem

Stamps Tour's blog is fully built end-to-end — `blog.php`/`blog-post.php` (public), `admin/blog.php`/`blog-edit.php`/`blog-delete.php` (CRUD), and a `blog_posts` table with SEO fields already in place (`meta_title`, `meta_description`, `slug`, `status`, `published_at`) — but it's empty. There's no repeatable process for deciding *what* to write about or getting a finished post into that pipeline. The site currently gets real search traffic across four regions (Santiago, Valparaíso, Maipo Valley, Andes) via its tour pages, and now has live Search Console + GA4 access connected, but nothing turns that data into content.

## Scope

Two Claude Code skills plus one plain PHP script, covering **destination-guide content only**, in English (the site is English-only per its `hreflang` tags), for the four existing regions.

**Out of scope:** automated publishing (every draft lands as `status='draft'`; a human always flips it to `published` via the existing admin panel), sourcing images (the user supplies their own photos before a post is finalized), non-destination-guide content types (tour-page copy, etc. — `seo-keyword-research` is written generally enough to be reused for these later, but that reuse isn't designed here), any language other than English.

## Changes

### 1. `seo-keyword-research` skill

New project skill at `STAMP/.claude/skills/seo-keyword-research/SKILL.md`, modeled on the existing `seo-audit` skill's frontmatter/step conventions. `allowed-tools` includes the `search-console-mcp` and `analytics-mcp` tools currently connected in this session (not the older `mcp__cogny__*` tools `seo-audit` references — those belong to a different, unrelated MCP integration), plus `WebSearch`/`WebFetch` and `Write`.

**Input:** an optional seed (a region or topic, e.g. "Valparaíso"); if omitted, explores across all four regions.

**Steps:**
1. Query `search-console-mcp` for stampstour.com's query data over a recent window (~3-6 months): `get_search_analytics` / `get_search_by_page_query`, filtered for queries with real impressions but weak CTR or position ~8-30 — proven demand the site isn't capturing — that aren't already served by an existing tour page or blog post.
2. For each promising candidate, use `WebSearch` to find how it naturally expands into longer, more specific long-tail phrasings and related "people also ask"-style questions, and do a quick scan of what currently ranks for it (gauge competitiveness, spot angles competing content misses).
3. Present a shortlist (3-5 candidates, one line each: keyword + why it's an opportunity) with one clearly recommended pick. Only the recommended pick gets fully researched into a brief, to avoid spending research effort on options that won't be chosen.
4. Write the brief to `STAMP/content-drafts/briefs/<slug>-brief.md`: target long-tail keyword, search intent, 2-3 supporting related phrases/questions, which of the four existing tour pages it should link to, and a short competitive note.

This is the checkpoint where the user reviews/confirms the topic before any drafting happens.

### 2. `seo-post-writer` skill

New project skill at `STAMP/.claude/skills/seo-post-writer/SKILL.md`. `allowed-tools`: `Read`, `Write`.

**Input:** a brief file from `seo-keyword-research` (or one written by hand), plus any free-form ideas/angle notes the user gives when invoking it.

**Output:** `STAMP/content-drafts/<slug>.md`, ~1200-1800 words, structured as:

```
---
title: ...
slug: ...
excerpt: ...           (~150-160 chars, for the blog listing card)
meta_title: ...         (<=60 chars)
meta_description: ...   (<=155 chars)
featured_image: [IMAGE: hero shot of ... — save to /img/blog/<slug>/]
---

## Intro
(hook, primary keyword worked in naturally within the first ~100 words)

## <H2 section>
...
## <H2 section>
...
## FAQ
(2-4 Q&As drawn from the brief's supporting phrases/questions)
```

A CTA linking to the tour page named in the brief is worked into the closing section rather than bolted on. Inline `[IMAGE: ...]` placeholders appear wherever a photo would naturally break up the text, each suggesting a save path under `/img/blog/<slug>/` (mirroring the existing `img/Tours/<tour>/` convention). This skill never touches the database — its only output is the draft file, which the user then edits directly and into which they drop their own photos, replacing each placeholder with a real `<img>` tag.

### 3. `insert-blog-draft.php` script

Plain script (not a skill — mechanical, no research/judgment involved) at `STAMP/scripts/insert-blog-draft.php`, invoked as:

```
php insert-blog-draft.php content-drafts/valparaiso-day-trip-from-santiago.md
```

It parses the frontmatter + body, converts the markdown body to HTML, and inserts one row into `blog_posts` with `status='draft'` (`published_at` left `NULL`, matching how `admin/blog-edit.php` already handles new drafts). Reuses `admin/blog-edit.php`'s existing `stamp_blog_slugify()` logic rather than duplicating it, and connects via the same `../db_config.php` used elsewhere.

Before inserting, it validates and fails loudly (non-zero exit, clear message) rather than silently proceeding:
- **Required fields present** — title, slug, excerpt, content, meta_title, meta_description all non-empty.
- **Image paths exist on disk** — every path referenced in `featured_image` and in any `<img>` tag in the body (i.e. every placeholder the user has since replaced) is checked to exist under the site root; if any are missing, the script lists all of them and refuses to insert.
- **Slug collision** — checks for an existing row with the same slug first (giving a clear "slug already used" message), rather than relying solely on the DB's unique-constraint error, though it also correctly handles that error (`errno 1062`) if a race occurs.

On success, it prints the new post's `id` and a direct link to `admin/blog-edit.php?id=<id>` so the user can do a final check and publish from there.

## Testing

- `php -l` on `insert-blog-draft.php`.
- Run the script against a valid sample draft file (real title/content/existing image paths) → confirm a new row appears in `blog_posts` with `status='draft'`, `published_at` NULL, and correct field mapping.
- Run against a draft referencing a nonexistent image path → confirm it refuses to insert and lists the missing path(s).
- Run against a draft with a slug that already exists → confirm it refuses with the "slug already used" message, without a raw SQL error leaking through.
- Run against a draft missing a required field (e.g. empty `meta_description`) → confirm it refuses with a clear message naming the missing field.
- Manual end-to-end pass: invoke `seo-keyword-research` once and sanity-check the brief looks like a real opportunity; invoke `seo-post-writer` on it and check the draft file structure/length; manually drop in a placeholder image and edit a placeholder to a real `<img>` tag; run the insert script; confirm the row shows up correctly in `admin/blog.php`'s list and edit view.
