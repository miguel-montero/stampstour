const fs = require('node:fs');

function loadTags(tagsPath) {
  return JSON.parse(fs.readFileSync(tagsPath, 'utf8'));
}

function filterValidTags(candidateTags, validTags) {
  const byLower = new Map(validTags.map((t) => [t.toLowerCase(), t]));
  const result = [];
  for (const candidate of candidateTags) {
    const canonical = byLower.get(String(candidate).toLowerCase());
    if (canonical && !result.includes(canonical)) result.push(canonical);
    if (result.length === 5) break;
  }
  return result;
}

module.exports = { loadTags, filterValidTags };
