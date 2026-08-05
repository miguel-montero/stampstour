// One-time backfill: regenerate thumbnails for already-published photos at
// the new smaller size/quality (docs/superpowers/specs/2026-08-05-gallery-
// client-side-rendering-design.md). Re-running is harmless (same output)
// but pointless once the live thumbnails are already at the new size - this
// is not meant to be part of the regular publish workflow.
const path = require('node:path');
const fs = require('node:fs');
const { readManifest } = require('./lib/manifest');
const { generateVariants } = require('./lib/image-processing');

const REPO_ROOT = path.join(__dirname, '..');
const PUBLISHED_DIR = path.join(__dirname, 'incoming', '_published');
const MANIFEST_PATH = path.join(__dirname, 'gallery-data.json');
const GALLERY_IMG_DIR = path.join(REPO_ROOT, 'img', 'Gallery');

async function regenerate() {
  const manifest = readManifest(MANIFEST_PATH);
  let regenerated = 0;
  let skipped = 0;

  for (const entry of manifest) {
    const sourcePath = path.join(PUBLISHED_DIR, entry.sourceFile);
    if (!fs.existsSync(sourcePath)) {
      console.warn(`Skipping ${entry.id}: source file ${entry.sourceFile} not found in incoming/_published/`);
      skipped++;
      continue;
    }
    await generateVariants(sourcePath, GALLERY_IMG_DIR, entry.id);
    regenerated++;
    console.log(`Regenerated: ${entry.id}`);
  }

  console.log(`Done: ${regenerated} regenerated, ${skipped} skipped.`);
}

regenerate().catch((err) => {
  console.error(err);
  process.exit(1);
});
