# Guide Photo Gallery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the WhatsApp-photo intake pipeline (watch → review/tag → local-LLM caption → publish) and the `gallery.php` page it feeds, replacing the currently-dead `/gallery.html` nav link across the site.

**Architecture:** A standalone Node project at `STAMP/gallery-pipeline/` runs entirely on the site owner's Mac and never touches the production server. It has three entry points — `watch.js` (optional folder watcher), `review-server.js` (local Express review UI), `publish.js` (image resize + manifest + git push) — built on small, independently-tested pure-logic modules under `gallery-pipeline/lib/`. Publishing writes WebP images into `STAMP/img/Gallery/` and appends to a git-tracked JSON manifest (`gallery-pipeline/gallery-data.json`) that `STAMP/gallery.php` reads server-side to render a tag-filterable grid with `lightbox2` (already loaded elsewhere on the site).

**Tech Stack:** Node (chokidar, express, sharp, dotenv) for the pipeline; plain PHP + vanilla JS + the site's existing `lightbox2` for the gallery page. Node's built-in `node:test` runner for unit tests (no test framework dependency needed). No build step anywhere, matching the rest of the site.

## Global Constraints

- New project root: `STAMP/gallery-pipeline/` (sibling to `img/`, `includes/`, etc. — all paths below are relative to `STAMP/` unless stated otherwise).
- Node dependencies (in `gallery-pipeline/package.json`): `chokidar@^4`, `express@^4`, `sharp@^0.33`, `dotenv@^16`.
- Generated gallery images are **WebP-only** (no JPG fallback pair, unlike older `_medium.jpg`/`.webp` pairs elsewhere on the site): thumb ~500px wide, large ~1600px wide, both `quality: 80`.
- `tags.json` (git-tracked) seed content — the 5 tour names are fixed and must not be removed; content tags below them may be edited over time:
  ```json
  [
    "Santiago City Tour",
    "Valparaíso & Viña del Mar",
    "Maipo Valley Wine Tour",
    "Portillo & Andes",
    "Cruise Transfer",
    "Wildlife",
    "Wine",
    "Food",
    "Landscape",
    "History",
    "Adventure",
    "People"
  ]
  ```
- **`.state.json` scope note (implementation clarification of the design spec):** `.state.json` tracks only which source-folder filenames `watch.js` has already copied into `incoming/`. Publish-side idempotency (spec's "processing the same batch twice must not duplicate manifest entries") is instead achieved by checking each queued item's `sourceFile` against existing `gallery-data.json` entries — simpler than a second bookkeeping file, same guarantee.
- `.approved-queue.json` (gitignored, new — not named in the design spec but required to bridge review → publish): array of `{filename, title, tags}`, written by `review-server.js` on Approve, fully drained by `publish.js` on each run.
- `GALLERY_SKIP_GIT=1` environment variable makes `publish.js` skip its final `git add`/`commit`/`push` — needed so this plan's tasks can smoke-test publishing safely inside this isolated worktree without pushing test data to `origin/main`. The real owner runs `publish.js` without this variable set.
- `STAMP/gallery.php` replaces the dead `/gallery.html` link: `includes/header.php`'s nav entry and `includes/footer.php`'s placeholder `<a href="#">Gallery</a>` both get updated to point at `/gallery.php`.
- Page template pattern to follow (established in `blog.php`): `$page_title` / `$page_description` / `$page_canonical` vars, `includes/head.php`, `includes/header.php`, `includes/cookie-banner.php`, `<footer class="revealed">` wrapping `includes/footer.php`.

---

### Task A: Scaffold the gallery-pipeline project

**Files:**
- Create: `gallery-pipeline/package.json`
- Create: `gallery-pipeline/tags.json`
- Create: `gallery-pipeline/gallery-data.json`
- Create: `gallery-pipeline/.env.example`
- Modify: `.gitignore` (repo root)

**Interfaces:**
- Consumes: nothing.
- Produces: the project skeleton every later task builds inside. `tags.json` and `gallery-data.json` are read by `lib/tags.js` (Task D) and `lib/manifest.js` (Task C) respectively.

- [ ] **Step 1: Create the directory and `package.json`**

```bash
mkdir -p gallery-pipeline/lib gallery-pipeline/test/fixtures gallery-pipeline/public
```

Create `gallery-pipeline/package.json`:

```json
{
  "name": "gallery-pipeline",
  "version": "1.0.0",
  "private": true,
  "scripts": {
    "test": "node --test test/",
    "watch": "node watch.js",
    "review": "node review-server.js",
    "publish": "node publish.js"
  },
  "dependencies": {
    "chokidar": "^4.0.1",
    "dotenv": "^16.4.5",
    "express": "^4.21.1",
    "sharp": "^0.33.5"
  }
}
```

- [ ] **Step 2: Install dependencies**

```bash
cd gallery-pipeline && npm install
```

Expected: `node_modules/` created, `package-lock.json` generated, no errors.

- [ ] **Step 3: Create `tags.json`**

Create `gallery-pipeline/tags.json` with the exact content from the Global Constraints section above.

- [ ] **Step 4: Create the empty manifest**

Create `gallery-pipeline/gallery-data.json`:

```json
[]
```

- [ ] **Step 5: Create `.env.example`**

Create `gallery-pipeline/.env.example`:

```
PHONE_SYNC_FOLDER=/Users/you/Pictures/WhatsApp Group Sync
OLLAMA_HOST=http://localhost:11434
REVIEW_PORT=4000
```

- [ ] **Step 6: Add gitignore entries**

Append to `.gitignore` (repo root):

```
# Gallery pipeline: local-only intake/review state, never source
/gallery-pipeline/incoming/
/gallery-pipeline/.state.json
/gallery-pipeline/.approved-queue.json
/gallery-pipeline/.env
/gallery-pipeline/node_modules/
```

- [ ] **Step 7: Verify and commit**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP/.claude/worktrees/linked-nibbling-codd
git status --short gallery-pipeline .gitignore
```

Expected: `gallery-pipeline/package.json`, `package-lock.json`, `tags.json`, `gallery-data.json`, `.env.example` show as untracked (`??`); `.gitignore` shows modified (`M`); `gallery-pipeline/node_modules/` does NOT appear (confirms the gitignore entry works before it's even committed... actually `git status` still shows ignored dirs as absent from output only once the rule is committed or at least present in the working tree, which it is here).

```bash
git add gallery-pipeline/package.json gallery-pipeline/package-lock.json gallery-pipeline/tags.json gallery-pipeline/gallery-data.json gallery-pipeline/.env.example .gitignore
git commit -m "Scaffold gallery-pipeline project"
```

---

### Task B: Pull the Ollama vision model

**Files:**
- None — environment setup only.

**Interfaces:**
- Consumes: nothing.
- Produces: a locally-running `llava` model that Task D's tests reference conceptually and Task G's smoke test exercises for real.

- [ ] **Step 1: Pull the model**

```bash
ollama pull llava
```

Expected: downloads (~4.7GB) and completes with `success`. This may take several minutes depending on connection speed — run with a generous timeout.

- [ ] **Step 2: Confirm it's available and responds**

```bash
ollama list
curl -s http://localhost:11434/api/generate -d '{"model": "llava", "prompt": "Reply with just the word OK.", "stream": false}'
```

Expected: `ollama list` shows `llava` in the table; the `curl` response is a JSON object whose `"response"` field contains text (confirms the model loads and Ollama's HTTP API is reachable on the default port).

---

### Task C: Manifest + slugify utilities

**Files:**
- Create: `gallery-pipeline/lib/slugify.js`
- Create: `gallery-pipeline/lib/manifest.js`
- Test: `gallery-pipeline/test/slugify.test.js`
- Test: `gallery-pipeline/test/manifest.test.js`

**Interfaces:**
- Consumes: nothing.
- Produces: `slugify(title): string`, and from `manifest.js` — `readManifest(manifestPath): array`, `writeManifest(manifestPath, entries): void`, `findBySourceFile(entries, sourceFile): entry|undefined`, `uniqueSlug(entries, baseSlug): string`. `publish.js` (Task H) is the consumer of all of these.

- [ ] **Step 1: Write the failing tests**

Create `gallery-pipeline/test/slugify.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { slugify } = require('../lib/slugify');

test('slugifies a plain title', () => {
  assert.equal(
    slugify('Guide pointing out condors above the Andes'),
    'guide-pointing-out-condors-above-the-andes'
  );
});

test('strips accents', () => {
  assert.equal(slugify('Valparaíso sunset!'), 'valparaiso-sunset');
});

test('collapses repeated punctuation and trims dashes', () => {
  assert.equal(slugify('  Wine & Food -- tasting!!  '), 'wine-food-tasting');
});
```

Create `gallery-pipeline/test/manifest.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { readManifest, writeManifest, findBySourceFile, uniqueSlug } = require('../lib/manifest');

test('readManifest returns [] when the file does not exist', () => {
  const p = path.join(os.tmpdir(), `manifest-${Date.now()}-missing.json`);
  assert.deepEqual(readManifest(p), []);
});

test('writeManifest then readManifest round-trips', () => {
  const p = path.join(os.tmpdir(), `manifest-${Date.now()}.json`);
  const entries = [{ id: 'a', title: 'A' }];
  writeManifest(p, entries);
  assert.deepEqual(readManifest(p), entries);
  fs.unlinkSync(p);
});

test('findBySourceFile finds a matching entry', () => {
  const entries = [{ id: 'a', sourceFile: 'IMG_1.jpg' }, { id: 'b', sourceFile: 'IMG_2.jpg' }];
  assert.equal(findBySourceFile(entries, 'IMG_2.jpg').id, 'b');
  assert.equal(findBySourceFile(entries, 'IMG_9.jpg'), undefined);
});

test('uniqueSlug returns the base slug when unused', () => {
  assert.equal(uniqueSlug([], 'andes-condor'), 'andes-condor');
});

test('uniqueSlug disambiguates collisions', () => {
  const entries = [{ id: 'andes-condor' }, { id: 'andes-condor-2' }];
  assert.equal(uniqueSlug(entries, 'andes-condor'), 'andes-condor-3');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd gallery-pipeline && node --test test/slugify.test.js test/manifest.test.js
```

Expected: FAIL — `Cannot find module '../lib/slugify'` / `'../lib/manifest'`.

- [ ] **Step 3: Implement `lib/slugify.js`**

```js
function slugify(title) {
  return title
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

module.exports = { slugify };
```

- [ ] **Step 4: Implement `lib/manifest.js`**

```js
const fs = require('node:fs');

function readManifest(manifestPath) {
  if (!fs.existsSync(manifestPath)) return [];
  const raw = fs.readFileSync(manifestPath, 'utf8').trim();
  if (!raw) return [];
  return JSON.parse(raw);
}

function writeManifest(manifestPath, entries) {
  fs.writeFileSync(manifestPath, JSON.stringify(entries, null, 2) + '\n');
}

function findBySourceFile(entries, sourceFile) {
  return entries.find((entry) => entry.sourceFile === sourceFile);
}

function uniqueSlug(entries, baseSlug) {
  const existingIds = new Set(entries.map((entry) => entry.id));
  if (!existingIds.has(baseSlug)) return baseSlug;
  let n = 2;
  while (existingIds.has(`${baseSlug}-${n}`)) n += 1;
  return `${baseSlug}-${n}`;
}

module.exports = { readManifest, writeManifest, findBySourceFile, uniqueSlug };
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd gallery-pipeline && node --test test/slugify.test.js test/manifest.test.js
```

Expected: PASS — all 6 tests green.

- [ ] **Step 6: Commit**

```bash
git add gallery-pipeline/lib/slugify.js gallery-pipeline/lib/manifest.js gallery-pipeline/test/slugify.test.js gallery-pipeline/test/manifest.test.js
git commit -m "Add slugify and manifest utility modules"
```

---

### Task D: Tag vocabulary + Ollama captioning client

**Files:**
- Create: `gallery-pipeline/lib/tags.js`
- Create: `gallery-pipeline/lib/ollama-client.js`
- Test: `gallery-pipeline/test/tags.test.js`
- Test: `gallery-pipeline/test/ollama-client.test.js`

**Interfaces:**
- Consumes: nothing (tests mock `fetch`; real usage in Task G passes the global `fetch`).
- Produces: `loadTags(tagsPath): string[]`, `filterValidTags(candidateTags, validTags): string[]` (deduped, capped at 5, only entries present in `validTags`, case-insensitive match returning canonical casing). `buildPrompt(validTags): string`, `parseOllamaResponse(rawText): {title, tags}`, `requestTitleAndTags({imageBase64, validTags, ollamaHost, fetchImpl}): Promise<{title, tags, error}>`. `review-server.js` (Task G) consumes all of these.

- [ ] **Step 1: Write the failing tests**

Create `gallery-pipeline/test/tags.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { filterValidTags } = require('../lib/tags');

const VALID = ['Wildlife', 'Wine', 'Food', 'Portillo & Andes'];

test('keeps only tags present in the valid list, case-insensitively', () => {
  assert.deepEqual(
    filterValidTags(['wildlife', 'Made Up Tag', 'WINE'], VALID),
    ['Wildlife', 'Wine']
  );
});

test('deduplicates and caps at 5 tags', () => {
  const many = ['Wildlife', 'wildlife', 'Wine', 'Food', 'Portillo & Andes', 'Wine'];
  const result = filterValidTags(many, VALID);
  assert.equal(result.length, 3);
  assert.deepEqual(result, ['Wildlife', 'Wine', 'Food']);
});

test('returns an empty array when nothing matches', () => {
  assert.deepEqual(filterValidTags(['Nonsense'], VALID), []);
});
```

Create `gallery-pipeline/test/ollama-client.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { buildPrompt, parseOllamaResponse, requestTitleAndTags } = require('../lib/ollama-client');

test('buildPrompt embeds the exact valid tag list as JSON', () => {
  const prompt = buildPrompt(['Wildlife', 'Wine']);
  assert.match(prompt, /\["Wildlife","Wine"\]/);
});

test('parseOllamaResponse parses plain JSON', () => {
  const result = parseOllamaResponse('{"title": "A condor over the Andes", "tags": ["Wildlife"]}');
  assert.deepEqual(result, { title: 'A condor over the Andes', tags: ['Wildlife'] });
});

test('parseOllamaResponse strips markdown code fences', () => {
  const result = parseOllamaResponse('```json\n{"title": "T", "tags": []}\n```');
  assert.deepEqual(result, { title: 'T', tags: [] });
});

test('parseOllamaResponse returns empty defaults on malformed input', () => {
  assert.deepEqual(parseOllamaResponse('not json at all'), { title: '', tags: [] });
});

test('requestTitleAndTags sends the image and prompt, returns the parsed result', async () => {
  let capturedBody;
  const fakeFetch = async (url, opts) => {
    capturedBody = JSON.parse(opts.body);
    return { ok: true, json: async () => ({ response: '{"title": "T", "tags": ["Wine"]}' }) };
  };
  const result = await requestTitleAndTags({
    imageBase64: 'BASE64DATA',
    validTags: ['Wine'],
    ollamaHost: 'http://localhost:11434',
    fetchImpl: fakeFetch,
  });
  assert.equal(capturedBody.model, 'llava');
  assert.deepEqual(capturedBody.images, ['BASE64DATA']);
  assert.deepEqual(result, { title: 'T', tags: ['Wine'], error: false });
});

test('requestTitleAndTags returns an error result when fetch throws', async () => {
  const fakeFetch = async () => { throw new Error('connection refused'); };
  const result = await requestTitleAndTags({
    imageBase64: 'X',
    validTags: [],
    ollamaHost: 'http://localhost:11434',
    fetchImpl: fakeFetch,
  });
  assert.deepEqual(result, { title: '', tags: [], error: true });
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd gallery-pipeline && node --test test/tags.test.js test/ollama-client.test.js
```

Expected: FAIL — modules don't exist yet.

- [ ] **Step 3: Implement `lib/tags.js`**

```js
const fs = require('node:fs');

function loadTags(tagsPath) {
  return JSON.parse(fs.readFileSync(tagsPath, 'utf8'));
}

function filterValidTags(candidateTags, validTags) {
  const byLower = new Map(validTags.map((t) => [t.toLowerCase(), t]));
  const result = [];
  for (const candidate of candidateTags) {
    const canonical = byLower.get(String(candidate).toLowerCase());
    if (canonical && !result.includes(canonical)) result.push(canonical);
    if (result.length === 5) break;
  }
  return result;
}

module.exports = { loadTags, filterValidTags };
```

- [ ] **Step 4: Implement `lib/ollama-client.js`**

```js
function buildPrompt(validTags) {
  return [
    'You are captioning a candid photo from a Chilean tour company for a website gallery.',
    'Reply with ONLY a JSON object, no markdown fences, no extra text, in this exact shape:',
    '{"title": "short descriptive title, 6-10 words", "tags": ["tag1", "tag2"]}',
    `Tags MUST be chosen only from this list (copy the spelling exactly): ${JSON.stringify(validTags)}`,
    'Pick up to 5 tags that fit the photo. If none fit well, return an empty tags array.',
  ].join('\n');
}

function parseOllamaResponse(rawText) {
  const cleaned = rawText.replace(/```json|```/g, '').trim();
  try {
    const parsed = JSON.parse(cleaned);
    return {
      title: typeof parsed.title === 'string' ? parsed.title.trim() : '',
      tags: Array.isArray(parsed.tags) ? parsed.tags : [],
    };
  } catch {
    return { title: '', tags: [] };
  }
}

async function requestTitleAndTags({ imageBase64, validTags, ollamaHost, fetchImpl = fetch }) {
  try {
    const response = await fetchImpl(`${ollamaHost}/api/generate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: 'llava',
        prompt: buildPrompt(validTags),
        images: [imageBase64],
        stream: false,
      }),
    });
    if (!response.ok) return { title: '', tags: [], error: true };
    const data = await response.json();
    return { ...parseOllamaResponse(data.response || ''), error: false };
  } catch {
    return { title: '', tags: [], error: true };
  }
}

module.exports = { buildPrompt, parseOllamaResponse, requestTitleAndTags };
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd gallery-pipeline && node --test test/tags.test.js test/ollama-client.test.js
```

Expected: PASS — all 8 tests green.

- [ ] **Step 6: Commit**

```bash
git add gallery-pipeline/lib/tags.js gallery-pipeline/lib/ollama-client.js gallery-pipeline/test/tags.test.js gallery-pipeline/test/ollama-client.test.js
git commit -m "Add tag vocabulary and Ollama captioning client modules"
```

---

### Task E: Watch-state tracking + image variant generation

**Files:**
- Create: `gallery-pipeline/lib/state.js`
- Create: `gallery-pipeline/lib/image-processing.js`
- Create: `gallery-pipeline/test/fixtures/test-photo.jpg` (generated, then committed as a small binary fixture)
- Test: `gallery-pipeline/test/state.test.js`
- Test: `gallery-pipeline/test/image-processing.test.js`

**Interfaces:**
- Consumes: nothing new.
- Produces: `loadState(statePath): {copied}`, `saveState(statePath, state): void`, `hasCopied(state, filename): boolean`, `markCopied(state, filename): state` (consumed by `watch.js`, Task F). `generateVariants(sourcePath, outDir, slug): Promise<{thumbPath, largePath}>` (consumed by `publish.js`, Task H) — writes `${slug}-thumb.webp` (≤500px wide) and `${slug}-large.webp` (≤1600px wide) into `outDir`.

- [ ] **Step 1: Generate the test fixture image**

```bash
cd gallery-pipeline
convert -size 2000x1500 xc:SkyBlue test/fixtures/test-photo.jpg
```

Expected: a small flat-color JPG is created (this is a fixture for automated tests, not a real photo — its only job is to exercise `sharp`'s resize/convert path with a real, valid image file).

- [ ] **Step 2: Write the failing tests**

Create `gallery-pipeline/test/state.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { loadState, saveState, hasCopied, markCopied } = require('../lib/state');

test('loadState returns an empty copied map when the file is missing', () => {
  const p = path.join(os.tmpdir(), `state-${Date.now()}-missing.json`);
  assert.deepEqual(loadState(p), { copied: {} });
});

test('markCopied then saveState/loadState round-trips', () => {
  const p = path.join(os.tmpdir(), `state-${Date.now()}.json`);
  let state = loadState(p);
  assert.equal(hasCopied(state, 'IMG_1.jpg'), false);
  state = markCopied(state, 'IMG_1.jpg');
  saveState(p, state);
  const reloaded = loadState(p);
  assert.equal(hasCopied(reloaded, 'IMG_1.jpg'), true);
  assert.equal(hasCopied(reloaded, 'IMG_2.jpg'), false);
  fs.unlinkSync(p);
});
```

Create `gallery-pipeline/test/image-processing.test.js`:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const sharp = require('sharp');
const { generateVariants } = require('../lib/image-processing');

const FIXTURE = path.join(__dirname, 'fixtures', 'test-photo.jpg');

test('generates thumb and large webp variants narrower than the requested caps', async () => {
  const outDir = fs.mkdtempSync(path.join(os.tmpdir(), 'gallery-img-'));
  const { thumbPath, largePath } = await generateVariants(FIXTURE, outDir, 'test-slug');

  const thumbMeta = await sharp(thumbPath).metadata();
  const largeMeta = await sharp(largePath).metadata();

  assert.equal(thumbMeta.format, 'webp');
  assert.equal(largeMeta.format, 'webp');
  assert.ok(thumbMeta.width <= 500);
  assert.ok(largeMeta.width <= 1600);
  assert.ok(largeMeta.width > thumbMeta.width);

  fs.rmSync(outDir, { recursive: true, force: true });
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd gallery-pipeline && node --test test/state.test.js test/image-processing.test.js
```

Expected: FAIL — modules don't exist yet.

- [ ] **Step 4: Implement `lib/state.js`**

```js
const fs = require('node:fs');

function loadState(statePath) {
  if (!fs.existsSync(statePath)) return { copied: {} };
  const raw = fs.readFileSync(statePath, 'utf8').trim();
  if (!raw) return { copied: {} };
  return JSON.parse(raw);
}

function saveState(statePath, state) {
  fs.writeFileSync(statePath, JSON.stringify(state, null, 2) + '\n');
}

function hasCopied(state, filename) {
  return Boolean(state.copied && state.copied[filename]);
}

function markCopied(state, filename) {
  return { ...state, copied: { ...state.copied, [filename]: true } };
}

module.exports = { loadState, saveState, hasCopied, markCopied };
```

- [ ] **Step 5: Implement `lib/image-processing.js`**

```js
const path = require('node:path');
const fs = require('node:fs');
const sharp = require('sharp');

async function generateVariants(sourcePath, outDir, slug) {
  fs.mkdirSync(outDir, { recursive: true });
  const thumbPath = path.join(outDir, `${slug}-thumb.webp`);
  const largePath = path.join(outDir, `${slug}-large.webp`);

  await sharp(sourcePath)
    .resize({ width: 500, withoutEnlargement: true })
    .webp({ quality: 80 })
    .toFile(thumbPath);

  await sharp(sourcePath)
    .resize({ width: 1600, withoutEnlargement: true })
    .webp({ quality: 80 })
    .toFile(largePath);

  return { thumbPath, largePath };
}

module.exports = { generateVariants };
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
cd gallery-pipeline && node --test test/state.test.js test/image-processing.test.js
```

Expected: PASS — all 3 tests green.

- [ ] **Step 7: Run the full test suite so far**

```bash
cd gallery-pipeline && npm test
```

Expected: all 17 tests across all 6 test files pass.

- [ ] **Step 8: Commit**

```bash
git add gallery-pipeline/lib/state.js gallery-pipeline/lib/image-processing.js gallery-pipeline/test/state.test.js gallery-pipeline/test/image-processing.test.js gallery-pipeline/test/fixtures/test-photo.jpg
git commit -m "Add watch-state tracking and image variant generation modules"
```

---

### Task F: Folder watcher (`watch.js`)

**Files:**
- Create: `gallery-pipeline/watch.js`

**Interfaces:**
- Consumes: `lib/state.js` (Task E) — `loadState`, `saveState`, `hasCopied`, `markCopied`.
- Produces: files copied into `incoming/`, consumed by `review-server.js` (Task G).

- [ ] **Step 1: Implement `watch.js`**

```js
const path = require('node:path');
require('dotenv').config({ path: path.join(__dirname, '.env') });
const fs = require('node:fs');
const chokidar = require('chokidar');
const { loadState, saveState, hasCopied, markCopied } = require('./lib/state');

const SOURCE_DIR = process.env.PHONE_SYNC_FOLDER;
const INCOMING_DIR = path.join(__dirname, 'incoming');
const STATE_PATH = path.join(__dirname, '.state.json');
const IMAGE_EXT = /\.(jpe?g|png|heic|heif)$/i;

if (!SOURCE_DIR) {
  console.error('Set PHONE_SYNC_FOLDER in gallery-pipeline/.env before running watch.js');
  process.exit(1);
}

fs.mkdirSync(INCOMING_DIR, { recursive: true });
let state = loadState(STATE_PATH);

function handleFile(filePath) {
  const filename = path.basename(filePath);
  if (!IMAGE_EXT.test(filename)) return;
  if (hasCopied(state, filename)) return;
  fs.copyFileSync(filePath, path.join(INCOMING_DIR, filename));
  state = markCopied(state, filename);
  saveState(STATE_PATH, state);
  console.log(`Copied ${filename} into incoming/`);
}

const watcher = chokidar.watch(SOURCE_DIR, { ignoreInitial: false, depth: 0 });
watcher.on('add', handleFile);
console.log(`Watching ${SOURCE_DIR} for new photos...`);
```

- [ ] **Step 2: Smoke test — first run copies new files**

```bash
mkdir -p /tmp/gallery-watch-fixture-source
cp gallery-pipeline/test/fixtures/test-photo.jpg /tmp/gallery-watch-fixture-source/sample1.jpg
cd gallery-pipeline
cp .env.example .env
sed -i '' 's#^PHONE_SYNC_FOLDER=.*#PHONE_SYNC_FOLDER=/tmp/gallery-watch-fixture-source#' .env
node watch.js &
WATCH_PID=$!
sleep 2
ls incoming/
kill $WATCH_PID
```

Expected: console printed `Copied sample1.jpg into incoming/`; `ls incoming/` shows `sample1.jpg`.

- [ ] **Step 3: Smoke test — second run does not re-copy**

```bash
node watch.js &
WATCH_PID=$!
sleep 2
kill $WATCH_PID
```

Expected: no `Copied ...` line printed this time (confirms `.state.json` dedup works — `sample1.jpg` was already recorded as copied).

- [ ] **Step 4: Clean up test artifacts**

```bash
rm -rf /tmp/gallery-watch-fixture-source
rm -f incoming/sample1.jpg
rm -f .state.json
```

(Leave `sample1.jpg` removed from `incoming/` and `.state.json` cleared so Task G starts from a clean slate — both files are gitignored, so this is just local cleanup, not a git operation.)

- [ ] **Step 5: Commit**

```bash
git add gallery-pipeline/watch.js
git commit -m "Add WhatsApp-sync-folder watcher"
```

---

### Task G: Review server (`review-server.js` + `public/review.html`)

**Files:**
- Create: `gallery-pipeline/review-server.js`
- Create: `gallery-pipeline/public/review.html`

**Interfaces:**
- Consumes: `lib/tags.js` (`loadTags`, `filterValidTags`) and `lib/ollama-client.js` (`requestTitleAndTags`) from Task D.
- Produces: `incoming/_rejected/` (rejected photos), `.approved-queue.json` (approved photos awaiting publish) — consumed by `publish.js` (Task H).

- [ ] **Step 1: Implement `review-server.js`**

```js
const path = require('node:path');
require('dotenv').config({ path: path.join(__dirname, '.env') });
const fs = require('node:fs');
const express = require('express');
const { execFileSync } = require('node:child_process');
const { loadTags, filterValidTags } = require('./lib/tags');
const { requestTitleAndTags } = require('./lib/ollama-client');

const INCOMING_DIR = path.join(__dirname, 'incoming');
const REJECTED_DIR = path.join(INCOMING_DIR, '_rejected');
const PUBLISHED_DIR = path.join(INCOMING_DIR, '_published');
const QUEUE_PATH = path.join(__dirname, '.approved-queue.json');
const TAGS_PATH = path.join(__dirname, 'tags.json');
const OLLAMA_HOST = process.env.OLLAMA_HOST || 'http://localhost:11434';

fs.mkdirSync(INCOMING_DIR, { recursive: true });
fs.mkdirSync(REJECTED_DIR, { recursive: true });
fs.mkdirSync(PUBLISHED_DIR, { recursive: true });

function readQueue() {
  if (!fs.existsSync(QUEUE_PATH)) return [];
  const raw = fs.readFileSync(QUEUE_PATH, 'utf8').trim();
  return raw ? JSON.parse(raw) : [];
}

function writeQueue(queue) {
  fs.writeFileSync(QUEUE_PATH, JSON.stringify(queue, null, 2) + '\n');
}

function listPendingFiles() {
  return fs.readdirSync(INCOMING_DIR).filter((name) => {
    const full = path.join(INCOMING_DIR, name);
    return fs.statSync(full).isFile() && /\.(jpe?g|png|heic|heif)$/i.test(name);
  });
}

const app = express();
app.use(express.json());
app.use('/incoming', express.static(INCOMING_DIR));
app.use('/', express.static(path.join(__dirname, 'public')));
app.get('/tags.json', (req, res) => res.sendFile(TAGS_PATH));

app.get('/api/photos', (req, res) => {
  const queue = readQueue();
  const queuedByFilename = new Map(queue.map((item) => [item.filename, item]));
  const photos = listPendingFiles().map((filename) => {
    const queued = queuedByFilename.get(filename);
    return queued
      ? { filename, status: 'approved', title: queued.title, tags: queued.tags }
      : { filename, status: 'pending' };
  });
  res.json(photos);
});

app.post('/api/photos/:filename/reject', (req, res) => {
  const { filename } = req.params;
  const source = path.join(INCOMING_DIR, filename);
  if (!fs.existsSync(source)) return res.status(404).json({ error: 'not found' });
  fs.renameSync(source, path.join(REJECTED_DIR, filename));
  writeQueue(readQueue().filter((item) => item.filename !== filename));
  res.json({ ok: true });
});

app.post('/api/photos/:filename/suggest', async (req, res) => {
  const { filename } = req.params;
  const source = path.join(INCOMING_DIR, filename);
  if (!fs.existsSync(source)) return res.status(404).json({ error: 'not found' });
  const validTags = loadTags(TAGS_PATH);
  const imageBase64 = fs.readFileSync(source).toString('base64');
  const suggestion = await requestTitleAndTags({ imageBase64, validTags, ollamaHost: OLLAMA_HOST });
  res.json({ ...suggestion, tags: filterValidTags(suggestion.tags, validTags) });
});

app.post('/api/photos/:filename/approve', (req, res) => {
  const { filename } = req.params;
  const { title, tags } = req.body;
  if (!fs.existsSync(path.join(INCOMING_DIR, filename))) {
    return res.status(404).json({ error: 'not found' });
  }
  if (!title || !Array.isArray(tags) || tags.length === 0) {
    return res.status(400).json({ error: 'title and at least one tag are required' });
  }
  const validTags = loadTags(TAGS_PATH);
  const cleanTags = filterValidTags(tags, validTags);
  const queue = readQueue().filter((item) => item.filename !== filename);
  queue.push({ filename, title, tags: cleanTags });
  writeQueue(queue);
  res.json({ ok: true });
});

app.post('/api/publish', (req, res) => {
  try {
    const output = execFileSync('node', [path.join(__dirname, 'publish.js')], { encoding: 'utf8' });
    res.json({ ok: true, output });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message, output: err.stdout });
  }
});

const PORT = process.env.REVIEW_PORT || 4000;
app.listen(PORT, () => console.log(`Review server running at http://localhost:${PORT}`));
```

- [ ] **Step 2: Implement `public/review.html`**

```html
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Gallery Review</title>
<style>
  body { font-family: -apple-system, sans-serif; margin: 2rem; background: #111; color: #eee; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem; }
  .card { background: #1c1c1c; border-radius: 8px; padding: 0.75rem; }
  .card img { width: 100%; border-radius: 4px; display: block; }
  .card.approved { outline: 2px solid #4caf50; }
  .card input[type="text"] { width: 100%; margin-top: 0.5rem; }
  .tag { display: inline-block; padding: 0.15rem 0.5rem; margin: 0.15rem; border-radius: 999px; border: 1px solid #555; cursor: pointer; font-size: 0.8rem; }
  .tag.selected { background: #4caf50; border-color: #4caf50; color: #111; }
  button { margin-top: 0.5rem; margin-right: 0.5rem; }
  #publish { position: sticky; top: 0; background: #222; padding: 1rem; margin-bottom: 1rem; }
</style>
</head>
<body>
<div id="publish">
  <button id="publish-btn">Publish approved</button>
  <span id="publish-status"></span>
</div>
<div id="grid" class="grid"></div>
<script>
let TAGS = [];

async function loadTagsList() {
  const res = await fetch('/tags.json');
  TAGS = await res.json();
}

async function loadPhotos() {
  const res = await fetch('/api/photos');
  return res.json();
}

function renderCard(photo) {
  const card = document.createElement('div');
  card.className = 'card' + (photo.status === 'approved' ? ' approved' : '');

  const img = document.createElement('img');
  img.src = '/incoming/' + encodeURIComponent(photo.filename);
  card.appendChild(img);

  const titleInput = document.createElement('input');
  titleInput.type = 'text';
  titleInput.placeholder = 'Title';
  titleInput.value = photo.title || '';
  card.appendChild(titleInput);

  const tagsDiv = document.createElement('div');
  const selected = new Set(photo.tags || []);
  TAGS.forEach((tag) => {
    const pill = document.createElement('span');
    pill.className = 'tag' + (selected.has(tag) ? ' selected' : '');
    pill.textContent = tag;
    pill.onclick = () => pill.classList.toggle('selected');
    tagsDiv.appendChild(pill);
  });
  card.appendChild(tagsDiv);

  const suggestBtn = document.createElement('button');
  suggestBtn.textContent = 'Suggest with AI';
  suggestBtn.onclick = async () => {
    suggestBtn.textContent = 'Thinking...';
    const res = await fetch(`/api/photos/${encodeURIComponent(photo.filename)}/suggest`, { method: 'POST' });
    const data = await res.json();
    suggestBtn.textContent = 'Suggest with AI';
    if (data.error) { alert('Ollama unavailable — enter title/tags manually.'); return; }
    titleInput.value = data.title;
    tagsDiv.querySelectorAll('.tag').forEach((pill) => {
      pill.classList.toggle('selected', data.tags.includes(pill.textContent));
    });
  };
  card.appendChild(suggestBtn);

  const approveBtn = document.createElement('button');
  approveBtn.textContent = 'Approve';
  approveBtn.onclick = async () => {
    const tags = Array.from(tagsDiv.querySelectorAll('.tag.selected')).map((el) => el.textContent);
    await fetch(`/api/photos/${encodeURIComponent(photo.filename)}/approve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: titleInput.value, tags }),
    });
    refresh();
  };
  card.appendChild(approveBtn);

  const rejectBtn = document.createElement('button');
  rejectBtn.textContent = 'Reject';
  rejectBtn.onclick = async () => {
    await fetch(`/api/photos/${encodeURIComponent(photo.filename)}/reject`, { method: 'POST' });
    refresh();
  };
  card.appendChild(rejectBtn);

  return card;
}

async function refresh() {
  const grid = document.getElementById('grid');
  grid.innerHTML = '';
  const photos = await loadPhotos();
  photos.forEach((photo) => grid.appendChild(renderCard(photo)));
}

document.getElementById('publish-btn').onclick = async () => {
  const status = document.getElementById('publish-status');
  status.textContent = 'Publishing...';
  const res = await fetch('/api/publish', { method: 'POST' });
  const data = await res.json();
  status.textContent = data.ok ? 'Published!' : 'Failed: ' + data.error;
  refresh();
};

(async () => {
  await loadTagsList();
  await refresh();
})();
</script>
</body>
</html>
```

- [ ] **Step 3: Smoke test — list, reject, approve**

```bash
cd gallery-pipeline
cp test/fixtures/test-photo.jpg incoming/review-test.jpg
node review-server.js &
SERVER_PID=$!
sleep 1

curl -s http://localhost:4000/api/photos
```

Expected: `[{"filename":"review-test.jpg","status":"pending"}]`

```bash
curl -s -X POST http://localhost:4000/api/photos/review-test.jpg/reject
ls incoming/_rejected/
```

Expected: `{"ok":true}`; `review-test.jpg` listed in `incoming/_rejected/`.

```bash
cp test/fixtures/test-photo.jpg incoming/approve-test.jpg
curl -s -X POST http://localhost:4000/api/photos/approve-test.jpg/approve \
  -H "Content-Type: application/json" \
  -d '{"title":"Test approve","tags":["Wine"]}'
curl -s http://localhost:4000/api/photos
```

Expected: approve returns `{"ok":true}`; the photos list shows `approve-test.jpg` with `"status":"approved"`, `"title":"Test approve"`, `"tags":["Wine"]`.

- [ ] **Step 4: Smoke test — AI suggestion (requires Task B's Ollama model)**

```bash
curl -s -X POST http://localhost:4000/api/photos/approve-test.jpg/suggest
```

Expected: a JSON object with a non-empty `"title"` string, `"error":false`, and `"tags"` being a subset of `gallery-pipeline/tags.json` (the fixture is a flat blue square, so the title/tags may be generic — this checks the plumbing works, not caption quality).

```bash
kill $SERVER_PID
```

- [ ] **Step 5: Clean up test artifacts**

```bash
rm -rf incoming/_rejected/review-test.jpg incoming/approve-test.jpg
echo '[]' > .approved-queue.json
rm -f .approved-queue.json
```

(`.approved-queue.json` is gitignored; removing it entirely is fine, `review-server.js` recreates it on first write via `writeQueue`.)

- [ ] **Step 6: Commit**

```bash
git add gallery-pipeline/review-server.js gallery-pipeline/public/review.html
git commit -m "Add local review server and review UI"
```

---

### Task H: Publish (`publish.js`)

**Files:**
- Create: `gallery-pipeline/publish.js`

**Interfaces:**
- Consumes: `lib/manifest.js` (`readManifest`, `writeManifest`, `findBySourceFile`, `uniqueSlug`), `lib/slugify.js` (`slugify`), `lib/image-processing.js` (`generateVariants`) — all from Tasks C/E. Reads `.approved-queue.json` written by `review-server.js` (Task G).
- Produces: `gallery-pipeline/gallery-data.json` entries and `STAMP/img/Gallery/*.webp` files — consumed by `gallery.php` (Task I).

- [ ] **Step 1: Implement `publish.js`**

```js
const path = require('node:path');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { readManifest, writeManifest, findBySourceFile, uniqueSlug } = require('./lib/manifest');
const { slugify } = require('./lib/slugify');
const { generateVariants } = require('./lib/image-processing');

const REPO_ROOT = path.join(__dirname, '..');
const INCOMING_DIR = path.join(__dirname, 'incoming');
const PUBLISHED_DIR = path.join(INCOMING_DIR, '_published');
const QUEUE_PATH = path.join(__dirname, '.approved-queue.json');
const MANIFEST_PATH = path.join(__dirname, 'gallery-data.json');
const GALLERY_IMG_DIR = path.join(REPO_ROOT, 'img', 'Gallery');

function readQueue() {
  if (!fs.existsSync(QUEUE_PATH)) return [];
  const raw = fs.readFileSync(QUEUE_PATH, 'utf8').trim();
  return raw ? JSON.parse(raw) : [];
}

function writeQueue(queue) {
  fs.writeFileSync(QUEUE_PATH, JSON.stringify(queue, null, 2) + '\n');
}

async function publish() {
  fs.mkdirSync(PUBLISHED_DIR, { recursive: true });
  const queue = readQueue();
  let manifest = readManifest(MANIFEST_PATH);
  let publishedCount = 0;

  for (const item of queue) {
    const sourcePath = path.join(INCOMING_DIR, item.filename);
    const existing = findBySourceFile(manifest, item.filename);
    if (existing) {
      if (fs.existsSync(sourcePath)) {
        fs.renameSync(sourcePath, path.join(PUBLISHED_DIR, item.filename));
      }
      continue;
    }
    if (!fs.existsSync(sourcePath)) {
      console.warn(`Skipping ${item.filename}: no longer in incoming/`);
      continue;
    }

    const id = uniqueSlug(manifest, slugify(item.title));
    await generateVariants(sourcePath, GALLERY_IMG_DIR, id);

    manifest = [
      ...manifest,
      {
        id,
        title: item.title,
        tags: item.tags,
        thumb: `img/Gallery/${id}-thumb.webp`,
        large: `img/Gallery/${id}-large.webp`,
        sourceFile: item.filename,
        dateAdded: new Date().toISOString().slice(0, 10),
      },
    ];

    fs.renameSync(sourcePath, path.join(PUBLISHED_DIR, item.filename));
    publishedCount += 1;
  }

  writeManifest(MANIFEST_PATH, manifest);
  writeQueue([]);

  if (publishedCount === 0) {
    console.log('No new approved photos to publish.');
    return;
  }

  if (process.env.GALLERY_SKIP_GIT === '1') {
    console.log(`Published ${publishedCount} photo(s) locally (git step skipped via GALLERY_SKIP_GIT).`);
    return;
  }

  execFileSync('git', ['add', 'gallery-pipeline/gallery-data.json', 'img/Gallery'], { cwd: REPO_ROOT });
  execFileSync(
    'git',
    ['commit', '-m', `Add ${publishedCount} gallery photo${publishedCount === 1 ? '' : 's'}`],
    { cwd: REPO_ROOT }
  );
  execFileSync('git', ['push', 'origin', 'main'], { cwd: REPO_ROOT });
  console.log(`Published and pushed ${publishedCount} photo(s).`);
}

publish().catch((err) => {
  console.error(err);
  process.exit(1);
});
```

- [ ] **Step 2: Smoke test — publish a real approved photo (git push skipped)**

```bash
cd gallery-pipeline
cp test/fixtures/test-photo.jpg incoming/approve-test.jpg
cat > .approved-queue.json <<'JSON'
[{"filename": "approve-test.jpg", "title": "Test approve", "tags": ["Wine"]}]
JSON

GALLERY_SKIP_GIT=1 node publish.js
```

Expected output: `Published 1 photo(s) locally (git step skipped via GALLERY_SKIP_GIT).`

```bash
cat gallery-data.json
ls ../img/Gallery/
ls incoming/_published/
cat .approved-queue.json
```

Expected: `gallery-data.json` contains one entry — `id: "test-approve"`, `title: "Test approve"`, `tags: ["Wine"]`, `thumb: "img/Gallery/test-approve-thumb.webp"`, `large: "img/Gallery/test-approve-large.webp"`, `sourceFile: "approve-test.jpg"`; `img/Gallery/` contains both webp files; `incoming/_published/` contains `approve-test.jpg`; `.approved-queue.json` is `[]`.

- [ ] **Step 3: Smoke test — re-running publish with nothing new is a no-op**

```bash
GALLERY_SKIP_GIT=1 node publish.js
```

Expected output: `No new approved photos to publish.` — and `gallery-data.json` is byte-for-byte unchanged (confirm with `git diff gallery-pipeline/gallery-data.json` showing no diff, since Task A already committed the empty-array version and this test hasn't committed the new entry yet).

- [ ] **Step 4: Clean up test artifacts**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP/.claude/worktrees/linked-nibbling-codd
rm -f img/Gallery/test-approve-thumb.webp img/Gallery/test-approve-large.webp
rmdir img/Gallery 2>/dev/null || true
rm -f gallery-pipeline/incoming/_published/approve-test.jpg
echo '[]' > gallery-pipeline/gallery-data.json
```

- [ ] **Step 5: Commit**

```bash
git add gallery-pipeline/publish.js gallery-pipeline/gallery-data.json
git commit -m "Add publish script (resize, manifest, git push)"
```

---

### Task I: Gallery page (`gallery.php`) + nav link updates

**Files:**
- Create: `gallery.php`
- Create: `css/gallery.css`
- Create: `js/gallery.js`
- Modify: `includes/header.php` (nav link `/gallery.html` → `/gallery.php`)
- Modify: `includes/footer.php` (placeholder Gallery link → `/gallery.php`)

**Interfaces:**
- Consumes: `gallery-pipeline/gallery-data.json` (Task H's output format: `{id, title, tags, thumb, large, sourceFile, dateAdded}`).
- Produces: the public-facing gallery page — final task with a user-visible deliverable.

- [ ] **Step 1: Update the header nav link**

In `includes/header.php`, find:

```html
        <li>
         <a href="/gallery.html">
          Gallery
         </a>
        </li>
```

Replace with:

```html
        <li>
         <a href="/gallery.php">
          Gallery
         </a>
        </li>
```

- [ ] **Step 2: Update the footer nav link**

In `includes/footer.php`, find:

```html
       <li><a href="/blog">Blog</a></li>
       <li><a href="#">Gallery</a></li>
```

Replace with:

```html
       <li><a href="/blog">Blog</a></li>
       <li><a href="/gallery.php">Gallery</a></li>
```

- [ ] **Step 3: Create `gallery.php`**

```php
<?php
$manifestPath = __DIR__ . '/gallery-pipeline/gallery-data.json';
$photos = [];
if (file_exists($manifestPath)) {
    $decoded = json_decode(file_get_contents($manifestPath), true);
    if (is_array($decoded)) {
        $photos = $decoded;
    }
}
usort($photos, function ($a, $b) {
    return strcmp($b['dateAdded'] ?? '', $a['dateAdded'] ?? '');
});

$allTags = [];
foreach ($photos as $photo) {
    foreach ($photo['tags'] ?? [] as $tag) {
        $allTags[$tag] = true;
    }
}
$allTags = array_keys($allTags);
sort($allTags);

$page_title       = 'Gallery | Stamps Tour';
$page_description = 'Candid photos from Stamps Tour guides across our Santiago, Valparaíso, Maipo Valley, Andes, and cruise transfer tours.';
$page_canonical   = 'https://stampstour.com/gallery.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<link href="/css/lightbox2.css" rel="stylesheet"/>
<link href="/css/gallery.css" rel="stylesheet"/>
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>
  <?php include __DIR__ . '/includes/cookie-banner.php'; ?>

  <section id="hero_2" class="background-image" data-background="url(/img/Tours/Stgo/big.jpg)">
    <div class="opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.45)">
      <div class="intro_title">
        <h1>Gallery</h1>
      </div>
    </div>
  </section>

  <main>
    <div class="container margin_60">
      <?php if (empty($photos)): ?>
        <p>No photos yet &mdash; check back soon!</p>
      <?php else: ?>
        <div class="gallery-filters">
          <button type="button" class="gallery-filter-pill active" data-tag="">All</button>
          <?php foreach ($allTags as $tag): ?>
            <button type="button" class="gallery-filter-pill" data-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></button>
          <?php endforeach; ?>
        </div>

        <div class="gallery-grid">
          <?php foreach ($photos as $photo): ?>
            <a href="/<?= htmlspecialchars($photo['large'], ENT_QUOTES, 'UTF-8') ?>"
               data-lightbox="gallery"
               data-title="<?= htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8') ?>"
               class="gallery-item"
               data-tags="<?= htmlspecialchars(implode('|', $photo['tags'] ?? []), ENT_QUOTES, 'UTF-8') ?>">
              <img src="/<?= htmlspecialchars($photo['thumb'], ENT_QUOTES, 'UTF-8') ?>"
                   alt="<?= htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8') ?>"
                   loading="lazy">
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer class="revealed">
    <?php include __DIR__ . '/includes/footer.php'; ?>
  </footer>

  <script src="/js/jquery-3.7.1.min.js"></script>
  <script src="/js/lightbox2.js"></script>
  <script src="/js/gallery.js"></script>
</body>
</html>
```

- [ ] **Step 4: Create `css/gallery.css`**

```css
.gallery-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.gallery-filter-pill {
  border: 1px solid #ccc;
  background: #fff;
  border-radius: 999px;
  padding: 0.4rem 1rem;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.gallery-filter-pill.active,
.gallery-filter-pill:hover {
  background: #222;
  color: #fff;
  border-color: #222;
}

.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}

.gallery-item {
  display: block;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  border-radius: 6px;
}

.gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s;
}

.gallery-item:hover img {
  transform: scale(1.05);
}

.gallery-item.gallery-hidden {
  display: none;
}
```

- [ ] **Step 5: Create `js/gallery.js`**

```js
document.addEventListener('DOMContentLoaded', function () {
  var pills = document.querySelectorAll('.gallery-filter-pill');
  var items = document.querySelectorAll('.gallery-item');

  pills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      pills.forEach(function (p) { p.classList.remove('active'); });
      pill.classList.add('active');

      var tag = pill.getAttribute('data-tag');
      items.forEach(function (item) {
        var itemTags = (item.getAttribute('data-tags') || '').split('|');
        var matches = tag === '' || itemTags.indexOf(tag) !== -1;
        item.classList.toggle('gallery-hidden', !matches);
      });
    });
  });
});
```

- [ ] **Step 6: Lint PHP and confirm the empty-state page renders**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP/.claude/worktrees/linked-nibbling-codd
php -l gallery.php
php -l includes/header.php
php -l includes/footer.php

php -S localhost:8899 > /tmp/gallery-php-server.log 2>&1 &
sleep 1
curl -s http://localhost:8899/gallery.php | grep -o "No photos yet"
curl -s http://localhost:8899/gallery.php | grep -c 'href="/gallery.php"'
```

Expected: both `php -l` calls report `No syntax errors detected`; the empty-state message appears (no manifest entries yet); the nav-link grep count is ≥1 (confirms both header and footer link to the new page).

- [ ] **Step 7: Seed temporary sample data and verify the populated page with Puppeteer**

```bash
mkdir -p img/Gallery
cwebp -q 80 gallery-pipeline/test/fixtures/test-photo.jpg -o img/Gallery/sample-a-thumb.webp
cwebp -q 80 gallery-pipeline/test/fixtures/test-photo.jpg -o img/Gallery/sample-a-large.webp
cwebp -q 80 gallery-pipeline/test/fixtures/test-photo.jpg -o img/Gallery/sample-b-thumb.webp
cwebp -q 80 gallery-pipeline/test/fixtures/test-photo.jpg -o img/Gallery/sample-b-large.webp

cat > gallery-pipeline/gallery-data.json <<'JSON'
[
  {"id":"sample-a","title":"Sunset over Valparaíso hills","tags":["Valparaíso & Viña del Mar","Landscape"],"thumb":"img/Gallery/sample-a-thumb.webp","large":"img/Gallery/sample-a-large.webp","sourceFile":"sample-a.jpg","dateAdded":"2026-08-02"},
  {"id":"sample-b","title":"Tasting flight at a Maipo vineyard","tags":["Maipo Valley Wine Tour","Wine"],"thumb":"img/Gallery/sample-b-thumb.webp","large":"img/Gallery/sample-b-large.webp","sourceFile":"sample-b.jpg","dateAdded":"2026-08-01"}
]
JSON
```

Set up Puppeteer in a scratch directory (following this repo's established verification pattern) and run:

```bash
mkdir -p /tmp/gallery-page-verify && cd /tmp/gallery-page-verify
npm init -y >/dev/null 2>&1
npm install puppeteer >/dev/null 2>&1
```

Create `/tmp/gallery-page-verify/check.js`:

```js
const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  await page.goto('http://localhost:8899/gallery.php', { waitUntil: 'networkidle0' });

  const itemCount = await page.$$eval('.gallery-item', (els) => els.length);
  console.log('gallery items:', itemCount);

  const lazyCount = await page.$$eval(
    '.gallery-item img[loading="lazy"]',
    (els) => els.length
  );
  console.log('images with loading=lazy:', lazyCount, '(should equal gallery items count)');

  await page.click('.gallery-filter-pill[data-tag="Wine"]');
  await new Promise((r) => setTimeout(r, 200));
  const visibleAfterFilter = await page.$$eval(
    '.gallery-item:not(.gallery-hidden)',
    (els) => els.map((el) => el.getAttribute('data-title') || el.getAttribute('data-tags'))
  );
  console.log('visible after Wine filter:', visibleAfterFilter);

  await page.click('.gallery-filter-pill[data-tag=""]');
  await new Promise((r) => setTimeout(r, 200));
  const visibleAfterAll = await page.$$eval('.gallery-item:not(.gallery-hidden)', (els) => els.length);
  console.log('visible after All:', visibleAfterAll);

  await page.click('.gallery-item');
  await new Promise((r) => setTimeout(r, 500));
  const lightboxVisible = await page.$eval('#lightbox', (el) => getComputedStyle(el).display !== 'none');
  const caption = await page.$eval('.lb-caption', (el) => el.textContent);
  console.log('lightbox visible:', lightboxVisible, 'caption:', caption);

  await browser.close();
})();
```

```bash
node /tmp/gallery-page-verify/check.js
```

Expected: `gallery items: 2`; `images with loading=lazy: 2` (confirms every thumbnail carries native lazy-loading — with only 2 sample images there's nothing meaningfully "off-screen" to scroll-test, so this attribute check is the practical proxy for the design spec's lazy-loading requirement; a full scroll/network-panel confirmation is worth re-running once real photos make the grid tall enough to have off-screen content); after clicking the "Wine" pill, only the Maipo vineyard item is visible (its `data-tags` contains `Wine`); after clicking "All", `visible after All: 2`; `lightbox visible: true` with `caption` matching the clicked item's title.

- [ ] **Step 8: Clean up temporary sample data**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP/.claude/worktrees/linked-nibbling-codd
pkill -f "php -S localhost:8899"
rm -rf /tmp/gallery-page-verify
rm -f img/Gallery/sample-a-thumb.webp img/Gallery/sample-a-large.webp img/Gallery/sample-b-thumb.webp img/Gallery/sample-b-large.webp
rmdir img/Gallery 2>/dev/null || true
echo '[]' > gallery-pipeline/gallery-data.json
git status --short gallery-pipeline/gallery-data.json img/Gallery
```

Expected: `gallery-data.json` shows no diff from the committed empty-array version (`git status --short` prints nothing for it, or nothing at all if unchanged); `img/Gallery/` no longer exists.

- [ ] **Step 9: Commit**

```bash
git add gallery.php css/gallery.css js/gallery.js includes/header.php includes/footer.php
git commit -m "Add gallery.php page and wire up nav links"
```

---

### Task J: Full pipeline dry run and handoff

**Files:**
- None modified — this task only verifies the whole system end-to-end and documents the owner's real-world workflow.

**Interfaces:**
- Consumes: every module and script from Tasks A-I.
- Produces: confidence the shipped pipeline works end-to-end; final task in the plan.

- [ ] **Step 1: Run the full automated test suite one more time**

```bash
cd gallery-pipeline && npm test
```

Expected: all tests pass (confirms nothing in Tasks F-I's manual smoke testing accidentally broke the unit-tested modules).

- [ ] **Step 2: Full dry run with `GALLERY_SKIP_GIT`, using the real `tags.json` vocabulary**

```bash
cd gallery-pipeline
cp test/fixtures/test-photo.jpg incoming/dry-run.jpg
node review-server.js &
SERVER_PID=$!
sleep 1
curl -s -X POST http://localhost:4000/api/photos/dry-run.jpg/approve \
  -H "Content-Type: application/json" \
  -d '{"title":"Dry run test photo","tags":["Wildlife"]}'
kill $SERVER_PID
GALLERY_SKIP_GIT=1 node publish.js
cat gallery-data.json
```

Expected: one manifest entry with `id: "dry-run-test-photo"`, and `img/Gallery/dry-run-test-photo-{thumb,large}.webp` created. This proves the full chain (review → approve → publish → manifest → images) works together, not just each module in isolation.

- [ ] **Step 3: Final cleanup — leave the repo exactly as a fresh clone would have it**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP/.claude/worktrees/linked-nibbling-codd
rm -f img/Gallery/dry-run-test-photo-thumb.webp img/Gallery/dry-run-test-photo-large.webp
rmdir img/Gallery 2>/dev/null || true
rm -f gallery-pipeline/incoming/_published/dry-run.jpg
echo '[]' > gallery-pipeline/gallery-data.json
rm -f gallery-pipeline/.env gallery-pipeline/.state.json gallery-pipeline/.approved-queue.json
git status --short
```

Expected: `git status --short` shows nothing outstanding beyond what's already committed in Tasks A-I (the manifest is back to `[]`, matching Task A's commit).

- [ ] **Step 4: Note the real-world setup step for the owner**

State clearly (this is documentation for the human, not a code step): before using this for real, the owner needs to create `gallery-pipeline/.env` from `.env.example` and set `PHONE_SYNC_FOLDER` to wherever their phone's WhatsApp-media sync actually lands (e.g. an iCloud Photos folder) — this path is environment-specific and was intentionally left out of the committed `.env` (gitignored) per the design spec's risk notes.

- [ ] **Step 5: Push the branch**

```bash
git push -u origin HEAD
```

- [ ] **Step 6: Note the deferred real-world performance check**

State clearly (documentation, not a code step): the design spec's throttled-mobile-network check is only meaningful once the gallery holds enough real photos to have genuinely off-screen content. Once the owner has run the pipeline against a real batch of guide photos and deployed (`git pull` on production), re-run a PageSpeed Insights mobile check against `/gallery.php` — same pattern this repo already uses for other pages — to confirm lazy-loading is actually keeping initial byte weight low in practice, not just present in markup.
