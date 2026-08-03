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
  // Items whose processing threw. They stay in the queue (so the owner's
  // title/tags aren't lost) and their source file stays in incoming/, so the
  // next run retries them naturally.
  const failed = [];

  for (const item of queue) {
    try {
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

      // A title with no ASCII alphanumerics (all emoji, all non-Latin script)
      // slugifies to '', which would produce id:'' and files named '-thumb.webp'.
      const baseSlug = slugify(item.title) || 'photo';
      const id = uniqueSlug(manifest, baseSlug);
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

      // Persist this item's manifest entry BEFORE moving its source file out of
      // incoming/. If the process dies between these two lines, the photo is
      // already recorded and the source is still in incoming/, so the next run
      // takes the `existing` branch above and simply files it away. Persisting
      // per item also means one later item's failure can never discard the
      // entries of items that already succeeded in this batch.
      writeManifest(MANIFEST_PATH, manifest);

      fs.renameSync(sourcePath, path.join(PUBLISHED_DIR, item.filename));
      publishedCount += 1;
    } catch (err) {
      // Leave the source file in incoming/ and keep the queue entry, so nothing
      // is lost and a retry picks this item back up.
      console.error(`Failed to publish ${item.filename} - left in incoming/ for retry:`);
      console.error(err);
      failed.push(item);
    }
  }

  writeManifest(MANIFEST_PATH, manifest);
  writeQueue(failed);

  if (publishedCount === 0) {
    console.log('No new approved photos to publish.');
    return;
  }

  if (process.env.GALLERY_SKIP_GIT === '1') {
    console.log(`Published ${publishedCount} photo(s) locally (git step skipped via GALLERY_SKIP_GIT).`);
    return;
  }

  const gitPaths = ['gallery-pipeline/gallery-data.json', 'img/Gallery'];
  execFileSync('git', ['add', ...gitPaths], { cwd: REPO_ROOT });
  // Scope the commit to the same paths we staged. `git add` only stages these
  // two, but `git commit` without a pathspec commits the WHOLE index - so any
  // unrelated files the owner happened to have staged would be swept into the
  // gallery commit. The trailing pathspec keeps those staged and untouched.
  execFileSync(
    'git',
    ['commit', '-m', `Add ${publishedCount} gallery photo${publishedCount === 1 ? '' : 's'}`, '--', ...gitPaths],
    { cwd: REPO_ROOT }
  );
  execFileSync('git', ['push', 'origin', 'main'], { cwd: REPO_ROOT });
  console.log(`Published and pushed ${publishedCount} photo(s).`);
}

publish().catch((err) => {
  console.error(err);
  process.exit(1);
});
