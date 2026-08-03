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
