const fs = require('node:fs');

function readManifest(manifestPath) {
  if (!fs.existsSync(manifestPath)) return [];
  const raw = fs.readFileSync(manifestPath, 'utf8').trim();
  if (!raw) return [];
  return JSON.parse(raw);
}

function writeManifest(manifestPath, entries) {
  fs.writeFileSync(manifestPath, JSON.stringify(entries, null, 2) + '\n');
}

function findBySourceFile(entries, sourceFile) {
  return entries.find((entry) => entry.sourceFile === sourceFile);
}

function uniqueSlug(entries, baseSlug) {
  const existingIds = new Set(entries.map((entry) => entry.id));
  if (!existingIds.has(baseSlug)) return baseSlug;
  let n = 2;
  while (existingIds.has(`${baseSlug}-${n}`)) n += 1;
  return `${baseSlug}-${n}`;
}

module.exports = { readManifest, writeManifest, findBySourceFile, uniqueSlug };
