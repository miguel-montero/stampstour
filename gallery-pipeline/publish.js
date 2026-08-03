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
