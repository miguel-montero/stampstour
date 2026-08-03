const fs = require('node:fs');

function loadState(statePath) {
  if (!fs.existsSync(statePath)) return { copied: {} };
  const raw = fs.readFileSync(statePath, 'utf8').trim();
  if (!raw) return { copied: {} };
  return JSON.parse(raw);
}

function saveState(statePath, state) {
  fs.writeFileSync(statePath, JSON.stringify(state, null, 2) + '\n');
}

function hasCopied(state, filename) {
  return Boolean(state.copied && state.copied[filename]);
}

function markCopied(state, filename) {
  return { ...state, copied: { ...state.copied, [filename]: true } };
}

module.exports = { loadState, saveState, hasCopied, markCopied };
