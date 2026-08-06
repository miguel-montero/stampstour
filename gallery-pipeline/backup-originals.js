// Copies every original photo (both not-yet-published and already-published)
// into a separate git repo dedicated to backup, so full-resolution originals
// are versioned without bloating the site's deploy repo. Independent of
// bulk-publish.js/publish.js - run this first as a safety net, then run
// whichever publish script generates the site's WebP derivatives.
const path = require('node:path');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');

const INCOMING_DIR = path.join(__dirname, 'incoming');
const PUBLISHED_DIR = path.join(INCOMING_DIR, '_published');
const BACKUP_REPO_DIR = path.join(__dirname, '..', '..', 'stampstour-photo-backups');
const IMAGE_EXT = /\.(jpe?g|png|heic|heif)$/i;

function listImageFiles(dir) {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir).filter((name) => {
    const full = path.join(dir, name);
    return fs.statSync(full).isFile() && IMAGE_EXT.test(name);
  });
}

function backup() {
  if (!fs.existsSync(BACKUP_REPO_DIR)) {
    console.error(`Backup repo not found at ${BACKUP_REPO_DIR} - clone it first.`);
    process.exit(1);
  }

  const sources = [INCOMING_DIR, PUBLISHED_DIR];
  let copied = 0;
  let skipped = 0;

  for (const dir of sources) {
    for (const filename of listImageFiles(dir)) {
      const destPath = path.join(BACKUP_REPO_DIR, filename);
      if (fs.existsSync(destPath)) {
        skipped++;
        continue;
      }
      fs.copyFileSync(path.join(dir, filename), destPath);
      copied++;
    }
  }

  console.log(`Copied ${copied} new photo(s), skipped ${skipped} already-backed-up.`);

  if (copied === 0) {
    console.log('Nothing new to commit.');
    return;
  }

  execFileSync('git', ['add', '.'], { cwd: BACKUP_REPO_DIR });
  execFileSync(
    'git',
    ['commit', '-m', `Backup ${copied} photo${copied === 1 ? '' : 's'}`],
    { cwd: BACKUP_REPO_DIR }
  );
  execFileSync('git', ['push', 'origin'], { cwd: BACKUP_REPO_DIR, stdio: 'inherit' });
  console.log(`Committed and pushed ${copied} photo(s) to the backup repo.`);
}

backup();
