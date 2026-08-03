<?php
declare(strict_types=1);

require __DIR__ . '/draft-parser.php';

$failures = 0;
$checks = 0;

function stamp_check(string $label, $actual, $expected): void {
    global $failures, $checks;
    $checks++;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    } else {
        echo "PASS: $label\n";
    }
}

// stamp_parse_draft_file
$tmp = tempnam(sys_get_temp_dir(), 'draft');
file_put_contents($tmp, "---\ntitle: Test Title\nslug: test-title\n---\n\n## Heading\n\nSome text.\n");
$parsed = stamp_parse_draft_file($tmp);
stamp_check('parse: title field', $parsed['fields']['title'], 'Test Title');
stamp_check('parse: slug field', $parsed['fields']['slug'], 'test-title');
stamp_check('parse: body', $parsed['body'], "## Heading\n\nSome text.");
unlink($tmp);

// stamp_markdown_to_html
$html = stamp_markdown_to_html("## Heading\n\nFirst paragraph line one\nline two.\n\n### Sub\n\nAnother paragraph.\n\n<img src=\"/img/x.jpg\">\n");
stamp_check(
    'markdown_to_html',
    $html,
    "<h2>Heading</h2>\n<p>First paragraph line one line two.</p>\n<h3>Sub</h3>\n<p>Another paragraph.</p>\n<img src=\"/img/x.jpg\">"
);

// stamp_extract_image_paths
$paths = stamp_extract_image_paths(
    '/img/blog/x/hero.jpg',
    '<p>hi</p><img src="/img/blog/x/one.jpg"><img src=\'/img/blog/x/two.jpg\'>'
);
stamp_check('extract_image_paths', $paths, ['/img/blog/x/hero.jpg', '/img/blog/x/one.jpg', '/img/blog/x/two.jpg']);

$pathsEmpty = stamp_extract_image_paths('', '<p>no images</p>');
stamp_check('extract_image_paths: none', $pathsEmpty, []);

// stamp_missing_image_paths
$dir = sys_get_temp_dir() . '/stamp_test_' . uniqid();
mkdir($dir);
mkdir($dir . '/img');
file_put_contents($dir . '/img/exists.jpg', 'x');
$missing = stamp_missing_image_paths(['/img/exists.jpg', '/img/missing.jpg'], $dir);
stamp_check('missing_image_paths', $missing, ['/img/missing.jpg']);
unlink($dir . '/img/exists.jpg');
rmdir($dir . '/img');
rmdir($dir);

// stamp_missing_required_fields
$missingFields = stamp_missing_required_fields(['title' => 'T', 'slug' => 's'], '');
stamp_check('missing_required_fields', $missingFields, ['excerpt', 'meta_title', 'meta_description', 'content']);

$noMissing = stamp_missing_required_fields(
    ['title' => 'T', 'slug' => 's', 'excerpt' => 'e', 'meta_title' => 'mt', 'meta_description' => 'md'],
    'body text'
);
stamp_check('missing_required_fields: none missing', $noMissing, []);

echo "\n$checks checks, $failures failed.\n";
exit($failures > 0 ? 1 : 0);
