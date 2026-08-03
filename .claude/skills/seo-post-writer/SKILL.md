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
featured_image: /img/blog/<slug>/hero.jpg
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
- Wherever a photo would naturally break up the text (after the intro, and roughly every 2-3 sections), insert an inline placeholder on its own line: `[IMAGE: <description>]`. The user will replace each body placeholder with a real `<img src="/img/blog/<slug>/<file>.jpg" alt="...">` tag once they've added their own photo to that path.
- Never fabricate specific facts (opening hours, prices, exact addresses) that aren't in the brief or well-established public knowledge — keep those general, since inserting wrong specifics is worse than omitting them.
- **Important:** The `featured_image` field in frontmatter must be replaced with a bare image path (e.g., `/img/blog/<slug>/hero.jpg`), **not** wrapped in an `<img>` tag — it is stored as metadata, not rendered directly. Only the `[IMAGE: ...]` placeholders in the post body are replaced with full `<img>` tags.

### 3. Hand off

Tell the user the draft is ready at `content-drafts/<slug>.md`, and that next they should: edit the text as needed, drop their own photos into `/img/blog/<slug>/`, update the `featured_image` field in frontmatter to point to the actual hero image path (a bare path, not an `<img>` tag), replace each `[IMAGE: ...]` placeholder in the body with a real `<img>` tag — then run `php scripts/insert-blog-draft.php content-drafts/<slug>.md` to stage it as a draft in the CMS.
