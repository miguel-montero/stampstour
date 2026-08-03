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
