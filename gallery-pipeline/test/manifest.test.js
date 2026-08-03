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
