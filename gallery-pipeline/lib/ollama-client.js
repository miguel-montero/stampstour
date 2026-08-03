function buildPrompt(validTags) {
  return [
    'You are captioning a candid photo from a Chilean tour company for a website gallery.',
    'Reply with ONLY a JSON object, no markdown fences, no extra text, in this exact shape:',
    '{"title": "short descriptive title, 6-10 words", "tags": ["tag1", "tag2"]}',
    `Tags MUST be chosen only from this list (copy the spelling exactly): ${JSON.stringify(validTags)}`,
    'Pick up to 5 tags that fit the photo. If none fit well, return an empty tags array.',
  ].join('\n');
}

function parseOllamaResponse(rawText) {
  const cleaned = rawText.replace(/```json|```/g, '').trim();
  try {
    const parsed = JSON.parse(cleaned);
    return {
      title: typeof parsed.title === 'string' ? parsed.title.trim() : '',
      tags: Array.isArray(parsed.tags) ? parsed.tags : [],
    };
  } catch {
    return { title: '', tags: [] };
  }
}

async function requestTitleAndTags({ imageBase64, validTags, ollamaHost, fetchImpl = fetch }) {
  try {
    const response = await fetchImpl(`${ollamaHost}/api/generate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: 'llava',
        prompt: buildPrompt(validTags),
        images: [imageBase64],
        stream: false,
      }),
    });
    if (!response.ok) return { title: '', tags: [], error: true };
    const data = await response.json();
    return { ...parseOllamaResponse(data.response || ''), error: false };
  } catch {
    return { title: '', tags: [], error: true };
  }
}

module.exports = { buildPrompt, parseOllamaResponse, requestTitleAndTags };
