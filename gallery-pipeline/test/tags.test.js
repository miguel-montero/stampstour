const test = require('node:test');
const assert = require('node:assert/strict');
const { filterValidTags } = require('../lib/tags');

const VALID = ['Wildlife', 'Wine', 'Food', 'Portillo & Andes'];

test('keeps only tags present in the valid list, case-insensitively', () => {
  assert.deepEqual(
    filterValidTags(['wildlife', 'Made Up Tag', 'WINE'], VALID),
    ['Wildlife', 'Wine']
  );
});

test('deduplicates and caps at 5 tags', () => {
  const many = ['Wildlife', 'wildlife', 'Wine', 'Food', 'Portillo & Andes', 'Wine'];
  const result = filterValidTags(many, VALID);
  assert.equal(result.length, 4);
  assert.deepEqual(result, ['Wildlife', 'Wine', 'Food', 'Portillo & Andes']);
});

test('returns an empty array when nothing matches', () => {
  assert.deepEqual(filterValidTags(['Nonsense'], VALID), []);
});
