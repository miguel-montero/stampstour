const test = require('node:test');
const assert = require('node:assert/strict');
const { slugify } = require('../lib/slugify');

test('slugifies a plain title', () => {
  assert.equal(
    slugify('Guide pointing out condors above the Andes'),
    'guide-pointing-out-condors-above-the-andes'
  );
});

test('strips accents', () => {
  assert.equal(slugify('Valparaíso sunset!'), 'valparaiso-sunset');
});

test('collapses repeated punctuation and trims dashes', () => {
  assert.equal(slugify('  Wine & Food -- tasting!!  '), 'wine-food-tasting');
});
