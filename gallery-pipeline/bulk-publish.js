// Simple no-frills publish: everything sitting directly in incoming/ gets
// resized and added to the gallery, no review server, no AI, no tags.
// Just drop photos in incoming/ and run: node bulk-publish.js
const path = require('node:path');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { readManifest, writeManifest, findBySourceFile, uniqueSlug } = require('./lib/manifest');
const { slugify } = require('./lib/slugify');
const { generateVariants } = require('./lib/image-processing');

const REPO_ROOT = path.join(__dirname, '..');
const INCOMING_DIR = path.join(__dirname, 'incoming');
const PUBLISHED_DIR = path.join(INCOMING_DIR, '_published');
const MANIFEST_PATH = path.join(__dirname, 'gallery-data.json');
const GALLERY_IMG_DIR = path.join(REPO_ROOT, 'img', 'Gallery');
const IMAGE_EXT = /\.(jpe?g|png|heic|heif)$/i;

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// WhatsApp exports filenames in (at least) two conventions depending on how
// the photo left the app - individually shared photos look like
// "PHOTO-2026-06-29-11-46-52.jpg" (or " (1).jpg" for duplicates), while a
// full "Export Chat" with media looks like
// "WhatsApp Image 2026-08-06 at 00.03.43.jpeg" (or " (1).jpeg"). Both are
// normalized to the same {year, month, day, hour, minute, dup} shape so
// titleFromFilename/dateFromFilename don't care which one produced a file.
function parsedTimestamp(filename) {
  const stem = filename.replace(IMAGE_EXT, '');

  const photoMatch = stem.match(/^PHOTO-(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})(?:\s*\((\d+)\))?$/i);
  if (photoMatch) {
    const [, year, month, day, hour, minute, , dup] = photoMatch;
    return { year, month, day, hour, minute, dup };
  }

  const exportMatch = stem.match(/^WhatsApp (?:Image|Video) (\d{4})-(\d{2})-(\d{2}) at (\d{2})\.(\d{2})\.(\d{2})(?:\s*\((\d+)\))?$/i);
  if (exportMatch) {
    const [, year, month, day, hour, minute, , dup] = exportMatch;
    return { year, month, day, hour, minute, dup };
  }

  return null;
}

function titleFromFilename(filename) {
  const stem = filename.replace(IMAGE_EXT, '');
  const match = parsedTimestamp(filename);
  if (match) {
    const { year, month, day, hour, minute, dup } = match;
    const monthName = MONTHS[Number(month) - 1] || month;
    const dupSuffix = dup ? ` (${dup})` : '';
    return `Photo from ${monthName} ${Number(day)}, ${year} ${hour}:${minute}${dupSuffix}`;
  }
  return stem.replace(/[-_]+/g, ' ').trim() || 'Photo';
}

// The actual date the photo was taken/sent, parsed from the filename.
// Falls back to today (the publish date) when a filename doesn't match
// either known WhatsApp export pattern - e.g. photos added some other way.
// This is what "newest first" on the gallery page should sort by, not the
// date the publish script happened to run.
function dateFromFilename(filename) {
  const match = parsedTimestamp(filename);
  if (!match) return new Date().toISOString().slice(0, 10);
  return `${match.year}-${match.month}-${match.day}`;
}

function listIncomingFiles() {
  return fs.readdirSync(INCOMING_DIR).filter((name) => {
    const full = path.join(INCOMING_DIR, name);
    return fs.statSync(full).isFile() && IMAGE_EXT.test(name);
  });
}

async function bulkPublish() {
  fs.mkdirSync(INCOMING_DIR, { recursive: true });
  fs.mkdirSync(PUBLISHED_DIR, { recursive: true });

  let manifest = readManifest(MANIFEST_PATH);
  let publishedCount = 0;
  const failed = [];

  for (const filename of listIncomingFiles()) {
    try {
      const sourcePath = path.join(INCOMING_DIR, filename);
      if (findBySourceFile(manifest, filename)) {
        // Already published in a previous run; just file the leftover away.
        fs.renameSync(sourcePath, path.join(PUBLISHED_DIR, filename));
        continue;
      }

      const title = titleFromFilename(filename);
      const baseSlug = slugify(title) || 'photo';
      const id = uniqueSlug(manifest, baseSlug);
      await generateVariants(sourcePath, GALLERY_IMG_DIR, id);

      manifest = [
        ...manifest,
        {
          id,
          title,
          tags: [],
          thumb: `img/Gallery/${id}-thumb.webp`,
          large: `img/Gallery/${id}-large.webp`,
          sourceFile: filename,
          dateAdded: dateFromFilename(filename),
        },
      ];

      // Persist before moving the source, same crash-safety reasoning as
      // publish.js: if the process dies right after this line, the photo is
      // already recorded and the source is still in incoming/, so the next
      // run's findBySourceFile check above simply files it away.
      writeManifest(MANIFEST_PATH, manifest);
      fs.renameSync(sourcePath, path.join(PUBLISHED_DIR, filename));
      publishedCount += 1;
      console.log(`Published: ${filename} -> ${id}`);
    } catch (err) {
      console.error(`Failed to publish ${filename} - left in incoming/ for retry:`);
      console.error(err);
      failed.push(filename);
    }
  }

  if (publishedCount === 0) {
    console.log('No new photos to publish.');
    if (failed.length) console.log(`${failed.length} file(s) failed - see errors above.`);
    return;
  }

  if (process.env.GALLERY_SKIP_GIT === '1') {
    console.log(`Published ${publishedCount} photo(s) locally (git step skipped via GALLERY_SKIP_GIT).`);
    return;
  }

  const gitPaths = ['gallery-pipeline/gallery-data.json', 'img/Gallery'];
  execFileSync('git', ['add', ...gitPaths], { cwd: REPO_ROOT });
  execFileSync(
    'git',
    ['commit', '-m', `Add ${publishedCount} gallery photo${publishedCount === 1 ? '' : 's'}`, '--', ...gitPaths],
    { cwd: REPO_ROOT }
  );
  execFileSync('git', ['push', 'origin', 'main'], { cwd: REPO_ROOT });
  console.log(`Published and pushed ${publishedCount} photo(s).`);
  if (failed.length) console.log(`${failed.length} file(s) failed - see errors above, left in incoming/.`);
}

bulkPublish().catch((err) => {
  console.error(err);
  process.exit(1);
});
