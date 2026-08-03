<?php
declare(strict_types=1);

function stamp_parse_draft_file(string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Could not read draft file: $path");
    }
    if (!preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $raw, $m)) {
        throw new RuntimeException("Draft file is missing the frontmatter (--- ... ---) block: $path");
    }
    $frontmatter = $m[1];
    $body = trim($m[2]);

    $fields = [];
    foreach (preg_split('/\r?\n/', $frontmatter) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        $fields[$key] = $value;
    }

    return ['fields' => $fields, 'body' => $body];
}

function stamp_markdown_to_html(string $markdown): string {
    $lines = preg_split('/\r?\n/', $markdown);
    $html = [];
    $paragraph = [];

    $flushParagraph = function () use (&$paragraph, &$html) {
        if (!empty($paragraph)) {
            $html[] = '<p>' . implode(' ', $paragraph) . '</p>';
            $paragraph = [];
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $flushParagraph();
            continue;
        }
        if (preg_match('/^###\s+(.*)$/', $trimmed, $m)) {
            $flushParagraph();
            $html[] = '<h3>' . $m[1] . '</h3>';
            continue;
        }
        if (preg_match('/^##\s+(.*)$/', $trimmed, $m)) {
            $flushParagraph();
            $html[] = '<h2>' . $m[1] . '</h2>';
            continue;
        }
        if ($trimmed[0] === '<') {
            $flushParagraph();
            $html[] = $trimmed;
            continue;
        }
        $paragraph[] = $trimmed;
    }
    $flushParagraph();

    return implode("\n", $html);
}

function stamp_extract_image_paths(string $featuredImage, string $bodyHtml): array {
    $paths = [];
    $featuredImage = trim($featuredImage);
    if ($featuredImage !== '') {
        $paths[] = $featuredImage;
    }
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $bodyHtml, $m)) {
        foreach ($m[1] as $src) {
            $paths[] = $src;
        }
    }
    return array_values(array_unique($paths));
}

function stamp_missing_image_paths(array $paths, string $siteRoot): array {
    $missing = [];
    foreach ($paths as $path) {
        $rel = ltrim($path, '/');
        $full = rtrim($siteRoot, '/') . '/' . $rel;
        if (!is_file($full)) {
            $missing[] = $path;
        }
    }
    return $missing;
}

function stamp_missing_required_fields(array $fields, string $body): array {
    $required = ['title', 'slug', 'excerpt', 'meta_title', 'meta_description'];
    $missing = [];
    foreach ($required as $key) {
        if (trim($fields[$key] ?? '') === '') {
            $missing[] = $key;
        }
    }
    if (trim($body) === '') {
        $missing[] = 'content';
    }
    return $missing;
}
