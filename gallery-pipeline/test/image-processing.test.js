const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const sharp = require('sharp');
const { generateVariants } = require('../lib/image-processing');

const FIXTURE = path.join(__dirname, 'fixtures', 'test-photo.jpg');

test('generates thumb and large webp variants narrower than the requested caps', async () => {
  const outDir = fs.mkdtempSync(path.join(os.tmpdir(), 'gallery-img-'));
  const { thumbPath, largePath } = await generateVariants(FIXTURE, outDir, 'test-slug');

  const thumbMeta = await sharp(thumbPath).metadata();
  const largeMeta = await sharp(largePath).metadata();

  assert.equal(thumbMeta.format, 'webp');
  assert.equal(largeMeta.format, 'webp');
  assert.ok(thumbMeta.width <= 350);
  assert.ok(largeMeta.width <= 1600);
  assert.ok(largeMeta.width > thumbMeta.width);

  fs.rmSync(outDir, { recursive: true, force: true });
});
