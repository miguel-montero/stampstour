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

// Express matches routes on the ENCODED path and decodes params afterwards, so
// `:filename` can arrive as a literal traversal string (e.g. '../../includes/
// mailer_config.php' via %2F). path.basename() strips any directory component,
// pinning every filesystem operation below to INCOMING_DIR itself.
//
// basename() alone is not enough: basename('..') is still '..', and
// path.join(INCOMING_DIR, '..') escapes one level up to gallery-pipeline/ - so
// '' , '.' and '..' are rejected outright. Everything that survives both checks
// is a plain name that cannot resolve outside INCOMING_DIR.
function safeFilename(rawName) {
  const filename = path.basename(String(rawName ?? ''));
  if (!filename || filename === '.' || filename === '..') return null;
  return filename;
}

// safeFilename() only sanitizes the name - it doesn't confirm what it points
// to. '_published' and '_rejected' both survive path.basename() untouched
// (they're real subdirectories of INCOMING_DIR), so any route that stops at
// fs.existsSync() would happily rename/read a whole directory as if it were a
// photo. isRegularFile() closes that gap: it's the same fs.statSync(...)
// .isFile() check listPendingFiles() already uses above, just tolerant of a
// missing path (ENOENT -> false) so callers can use it as their existence
// check too.
function isRegularFile(fullPath) {
  try {
    return fs.statSync(fullPath).isFile();
  } catch (err) {
    if (err.code === 'ENOENT') return false;
    throw err;
  }
}

app.post('/api/photos/:filename/reject', (req, res) => {
  const filename = safeFilename(req.params.filename);
  if (!filename) return res.status(404).json({ error: 'not found' });
  const source = path.join(INCOMING_DIR, filename);
  if (!isRegularFile(source)) return res.status(404).json({ error: 'not found' });
  fs.renameSync(source, path.join(REJECTED_DIR, filename));
  writeQueue(readQueue().filter((item) => item.filename !== filename));
  res.json({ ok: true });
});

app.post('/api/photos/:filename/suggest', async (req, res) => {
  const filename = safeFilename(req.params.filename);
  if (!filename) return res.status(404).json({ error: 'not found' });
  const source = path.join(INCOMING_DIR, filename);
  if (!isRegularFile(source)) return res.status(404).json({ error: 'not found' });
  const validTags = loadTags(TAGS_PATH);
  const imageBase64 = fs.readFileSync(source).toString('base64');
  const suggestion = await requestTitleAndTags({ imageBase64, validTags, ollamaHost: OLLAMA_HOST });
  res.json({ ...suggestion, tags: filterValidTags(suggestion.tags, validTags) });
});

app.post('/api/photos/:filename/approve', (req, res) => {
  const filename = safeFilename(req.params.filename);
  if (!filename) return res.status(404).json({ error: 'not found' });
  const { title, tags } = req.body;
  if (!isRegularFile(path.join(INCOMING_DIR, filename))) {
    return res.status(404).json({ error: 'not found' });
  }
  // Validate the FILTERED tags, not the raw request body: a body full of tags
  // that aren't in tags.json would otherwise pass the emptiness check and get
  // queued with cleanTags: [], publishing a photo no filter pill can reach.
  const validTags = loadTags(TAGS_PATH);
  const cleanTags = Array.isArray(tags) ? filterValidTags(tags, validTags) : [];
  if (!title || cleanTags.length === 0) {
    return res.status(400).json({ error: 'title and at least one valid tag are required' });
  }
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
// Bind to loopback only. This server is unauthenticated and /api/publish shells
// out (and can git-push), so it must not be reachable from the local network.
app.listen(PORT, '127.0.0.1', () => console.log(`Review server running at http://localhost:${PORT}`));
