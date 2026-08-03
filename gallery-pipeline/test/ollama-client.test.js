const test = require('node:test');
const assert = require('node:assert/strict');
const { buildPrompt, parseOllamaResponse, requestTitleAndTags } = require('../lib/ollama-client');

test('buildPrompt embeds the exact valid tag list as JSON', () => {
  const prompt = buildPrompt(['Wildlife', 'Wine']);
  assert.match(prompt, /\["Wildlife","Wine"\]/);
});

test('parseOllamaResponse parses plain JSON', () => {
  const result = parseOllamaResponse('{"title": "A condor over the Andes", "tags": ["Wildlife"]}');
  assert.deepEqual(result, { title: 'A condor over the Andes', tags: ['Wildlife'] });
});

test('parseOllamaResponse strips markdown code fences', () => {
  const result = parseOllamaResponse('```json\n{"title": "T", "tags": []}\n```');
  assert.deepEqual(result, { title: 'T', tags: [] });
});

test('parseOllamaResponse returns empty defaults on malformed input', () => {
  assert.deepEqual(parseOllamaResponse('not json at all'), { title: '', tags: [] });
});

test('requestTitleAndTags sends the image and prompt, returns the parsed result', async () => {
  let capturedBody;
  const fakeFetch = async (url, opts) => {
    capturedBody = JSON.parse(opts.body);
    return { ok: true, json: async () => ({ response: '{"title": "T", "tags": ["Wine"]}' }) };
  };
  const result = await requestTitleAndTags({
    imageBase64: 'BASE64DATA',
    validTags: ['Wine'],
    ollamaHost: 'http://localhost:11434',
    fetchImpl: fakeFetch,
  });
  assert.equal(capturedBody.model, 'llava');
  assert.deepEqual(capturedBody.images, ['BASE64DATA']);
  assert.deepEqual(result, { title: 'T', tags: ['Wine'], error: false });
});

test('requestTitleAndTags returns an error result when fetch throws', async () => {
  const fakeFetch = async () => { throw new Error('connection refused'); };
  const result = await requestTitleAndTags({
    imageBase64: 'X',
    validTags: [],
    ollamaHost: 'http://localhost:11434',
    fetchImpl: fakeFetch,
  });
  assert.deepEqual(result, { title: '', tags: [], error: true });
});
