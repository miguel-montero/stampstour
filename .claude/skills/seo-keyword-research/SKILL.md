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
