# Guide photo gallery: WhatsApp intake pipeline + site gallery page

## Context

Guides send candid tour photos into a shared WhatsApp group. The site's nav already links to `/gallery.html` (`includes/header.php`), but that page has never been built — it's a dead link. Separately, `footer.php` has a placeholder "Gallery" link (`<a href="#">`) that should point to the same page once it exists.

The site (`STAMP/`) is a plain PHP codebase (no framework, no build step) backed by MySQL, deployed by pushing to `origin/main` on GitHub and pulling on the production server. Images are currently served as WebP with a JPG fallback pair (e.g. `img/Tours/Stgo/1_medium.jpg` / `.webp`), and the tour pages already load `lightbox2` (`css/lightbox2.css`, `js/lightbox2.js`) sitewide via `includes/tour-scripts.php`, unused so far outside that inclusion.

Rather than a one-off gallery page fed by manually-curated images, the goal is a repeatable pipeline: pull new candid photos out of the WhatsApp group's synced folder, let the site owner quickly approve/reject and tag them, auto-generate a title and content tags with a local vision LLM, and publish the result as optimized WebP images plus a small JSON manifest that the gallery page renders. The owner's own workflow after that is just `git pull` on the server — no server-side automation is in scope.

Confirmed available on the development machine: Node v26, PHP 8.5, Python 3, ImageMagick/`cwebp`, and Ollama (installed, but no vision-capable model pulled yet — `llava:7b` will need `ollama pull llava`).

## Goals

- A local, repeatable pipeline (run on the site owner's Mac, never on the production server) that takes raw photos from a watched/manually-populated folder through: review (approve/reject, per-photo tour+content tags) → auto title/tag suggestion via a local Ollama vision model → resize/convert to WebP → append to a git-tracked manifest → commit and push to `origin/main`.
- A tag system where every photo can carry multiple tags: the tour it belongs to (Santiago, Valparaíso, Maipo Wine, Andes/Portillo, Cruise Transfer) is always available as a tag, plus content tags (e.g. Wildlife, Wine, Food, Landscape, History, Adventure, People) drawn from a curated, owner-editable list — not free-form invention by the LLM.
- A `gallery.php` page, wired up at the existing `/gallery.html` nav link, that renders all published photos as a fast-loading, lazily-loaded responsive grid with tag-filter pills and a lightbox for full-size viewing, using the site's existing `lightbox2` include rather than a new library.
- Idempotent, safe re-runs: processing the same batch twice must not duplicate manifest entries or reprocess already-published photos.
- Photos load fast regardless of guides' original phone-camera resolution: every published photo is served as two generated WebP sizes (thumbnail for the grid, large for the lightbox), never the original file.

## Non-goals

- No automated pulling directly from WhatsApp's servers/API. The pipeline consumes whatever the phone's own sync already deposits into a local folder (e.g. Photos/iCloud) — it does not authenticate to WhatsApp or scrape the app's chat storage.
- No always-on background service. The pipeline is triggered by the owner (`npm run watch` optionally, then `npm run review`, then `npm run publish`) — nothing runs unattended or on a schedule.
- No server-side deploy automation. Publishing pushes to `origin/main`; pulling that onto the production server stays a manual step the owner already does for other work.
- No admin-panel UI in the PHP site itself for this workflow. The review tool is a separate local Node app, not an addition to `STAMP/admin/`.
- No JPG fallback for gallery images. Unlike the older `_medium.jpg`/`.webp` pairs elsewhere on the site, gallery images are WebP-only (see Design), since target browsers all support it and it halves pipeline output per photo.
- Not rebuilding or replacing the unused `finaltilesgallery`/`slider-pro` plugins already present in `js/`/`css/` — the new grid is plain CSS Grid + native lazy-loading, reusing only `lightbox2` for the full-size view.

## Design

### Repo layout

```
STAMP/gallery-pipeline/          # new Node project, git-tracked (code + tags.json only)
  incoming/                      # gitignored — raw photos land here
  incoming/_rejected/            # gitignored — photos you rejected during review
  incoming/_published/           # gitignored — local archive of originals after publish
  .state.json                    # gitignored — processed-file tracking, for idempotent re-runs
  .env                           # gitignored — path to the phone-sync folder, Ollama host
  tags.json                      # git-tracked — curated tag vocabulary
  watch.js                       # optional folder watcher
  review-server.js               # local Express app for approve/reject + tagging
  publish.js                     # resize/convert, manifest write, git commit+push
  gallery-data.json              # git-tracked — the manifest gallery.php reads
  package.json
```

`STAMP/img/Gallery/` (new, git-tracked) holds the generated WebP output: `<slug>-thumb.webp` and `<slug>-large.webp` per published photo.

### Intake

`watch.js` uses `chokidar` to watch a configured source folder (set once in `.env` — wherever the phone's sync deposits WhatsApp media, e.g. an iCloud Photos folder) and copies newly-seen image files into `gallery-pipeline/incoming/`, recording each copied filename in `.state.json` so re-runs never re-copy the same file. This step is optional — dragging photos into `incoming/` by hand works identically, since every downstream step only looks at what's in that folder.

### Review

`npm run review` starts a local Express server (default `localhost:4000`) and opens it in a browser. It lists every file in `incoming/` not yet recorded as rejected or published, rendered as a grid of on-the-fly thumbnails. Per photo:

- **Reject** moves the file to `incoming/_rejected/` and excludes it from all future review sessions.
- **Approve** triggers a call to the local Ollama vision model (`llava:7b`) with the image, requesting a short title (~6-10 words) and up to 5 suggested tags, constrained to the vocabulary in `tags.json` (the prompt includes that list explicitly). The suggestion appears as editable fields — an editable text input for the title, and toggleable pills for tags — which the owner can change before confirming. If Ollama is unreachable, the fields are simply left blank for manual entry rather than blocking the review; the page shows a visible warning banner in that case.

Confirmed photos (title + at least one tag) are queued; nothing touches the filesystem outside `incoming/` or the manifest until the owner clicks a final "Publish approved" action in the review UI, which invokes `publish.js`.

### Titling & tagging vocabulary

`tags.json` is a flat array of strings, git-tracked, hand-edited by the owner to add new content tags over time:

```json
["Santiago City Tour", "Valparaíso & Viña del Mar", "Maipo Valley Wine Tour",
 "Portillo & Andes", "Cruise Transfer", "Wildlife", "Wine", "Food",
 "Landscape", "History", "Adventure", "People"]
```

The five tour names are fixed (they mirror the site's actual tour pages) and should not be removed; content tags below them are freely editable. The Ollama prompt always presents this full list and instructs the model to pick only from it — it does not invent new tags.

### Publish

For each approved-and-confirmed photo, `publish.js`:

1. Slugifies the title into a filename-safe string (e.g. "Guide pointing out condors above the Andes" → `guide-pointing-out-condors-above-the-andes`), disambiguating with a numeric suffix if the slug already exists in the manifest.
2. Uses `sharp` to generate two WebP files into `STAMP/img/Gallery/`: `<slug>-thumb.webp` (~500px wide) and `<slug>-large.webp` (~1600px wide), both quality-tuned to match the compression level already used for `_medium.webp` files elsewhere on the site.
3. Appends one entry to `gallery-data.json`:
   ```json
   {
     "id": "guide-pointing-out-condors-above-the-andes",
     "title": "Guide pointing out condors above the Andes",
     "tags": ["Portillo & Andes", "Wildlife"],
     "thumb": "img/Gallery/guide-pointing-out-condors-above-the-andes-thumb.webp",
     "large": "img/Gallery/guide-pointing-out-condors-above-the-andes-large.webp",
     "dateAdded": "2026-08-02"
   }
   ```
4. Moves the original source file from `incoming/` to `incoming/_published/` and records it in `.state.json`.
5. Once all photos in the batch are processed: `git add gallery-data.json img/Gallery/*`, commit (`Add N gallery photos`), and `git push origin main`.

Re-running `publish` with nothing new approved is a no-op; photos already recorded in `.state.json` are never reprocessed, so partial batches (e.g. a crash mid-run) don't produce duplicate manifest entries on retry.

### Gallery page (`STAMP/gallery.php`)

Replaces the dead `/gallery.html` link (update both `includes/header.php`'s nav entry and `includes/footer.php`'s placeholder `<a href="#">Gallery</a>` to point at `/gallery.php`). Server-side PHP reads `gallery-pipeline/gallery-data.json`, sorts by `dateAdded` descending, and renders:

- A responsive CSS Grid of `<img loading="lazy">` thumbnails — native lazy-loading is what keeps initial page weight small, since off-screen images never download until scrolled near.
- Filter pills across the top for every tag present in the manifest (tour names and content tags together); clicking a pill toggles visibility of non-matching cards client-side (the full dataset is small enough — expected low hundreds of photos — that shipping it all and filtering in JS avoids a server round-trip per filter click).
- Each thumbnail wrapped in a `lightbox2`-compatible anchor pointing at the `large` WebP, with the title as the `data-title` caption — reusing the `lightbox2.css`/`lightbox2.js` already loaded via `includes/tour-scripts.php` rather than adding a new dependency.
- Standard page chrome via the existing `includes/header.php` / `includes/footer.php` includes, matching every other page on the site.

## Verification

1. Pull `ollama pull llava` and confirm a manual request to `localhost:11434` against a sample candid photo returns a sensible title and tags drawn only from `tags.json`.
2. Run the full loop end-to-end on a small real batch (5-10 actual guide photos): watch/drop into `incoming/`, reject a couple, approve the rest with edited titles/tags, publish, and confirm `gallery-data.json` and `img/Gallery/*` are correct and `git log`/`git status` show exactly one clean commit pushed.
3. Re-run `publish` immediately after with no new approvals — confirm it's a no-op (no duplicate manifest entries, no new commit).
4. Load `gallery.php` locally via `php -S localhost:8000`, confirm: lazy-loading actually defers off-screen images (check via browser devtools network panel while scrolling), tag filtering shows/hides the right cards, lightbox opens the `large` variant with the correct caption, and nav links from both header and footer land on the page correctly.
5. Confirm on a throttled mobile network profile that initial page load only fetches above-the-fold thumbnails, not the entire gallery.

## Risks

- **Ollama vision quality is unproven for this exact use case (candid tour photos, Spanish/English mixed context).** Titles/tags are always owner-editable before publish, so a bad suggestion never reaches the live site uninspected — but if `llava:7b` output turns out consistently weak, swapping models (e.g. to `llama3.2-vision`) is a config change, not a pipeline redesign.
- **The phone-sync folder path is environment-specific and will break silently if the sync mechanism changes** (e.g. switching phones, iCloud settings). Since `watch.js` is optional and `incoming/` also accepts manually-dropped files, this degrades gracefully to manual copying rather than blocking the pipeline.
- **Manifest is a single flat JSON file with no concurrent-write protection.** Fine for a single-owner, locally-run tool triggered manually, but would need real locking if this were ever run from multiple machines at once — out of scope here since only the owner runs it, from one machine.
