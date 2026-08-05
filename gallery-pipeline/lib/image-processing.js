const path = require('node:path');
const fs = require('node:fs');
const sharp = require('sharp');

async function generateVariants(sourcePath, outDir, slug) {
  fs.mkdirSync(outDir, { recursive: true });
  const thumbPath = path.join(outDir, `${slug}-thumb.webp`);
  const largePath = path.join(outDir, `${slug}-large.webp`);

  await sharp(sourcePath)
    .resize({ width: 350, withoutEnlargement: true })
    .webp({ quality: 72 })
    .toFile(thumbPath);

  await sharp(sourcePath)
    .resize({ width: 1600, withoutEnlargement: true })
    .webp({ quality: 80 })
    .toFile(largePath);

  return { thumbPath, largePath };
}

module.exports = { generateVariants };
