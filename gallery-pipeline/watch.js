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
