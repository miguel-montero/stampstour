<?php
// includes/tour_schema.php
// Renders Product JSON-LD structured data for a tour/transfer page - gives
// search engines and AI answer engines a machine-readable name/price fact
// instead of requiring them to parse it out of prose. $data keys:
// name, description, image (root-relative or absolute), url, price (float|null),
// sameAs (optional array of external URLs for this exact product, e.g. its
// Tripadvisor listing).

function render_tour_product_schema(array $data): void
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        return;
    }

    $image = (string)($data['image'] ?? '');
    if ($image !== '' && stripos($image, 'http') !== 0) {
        $image = 'https://stampstour.com/' . ltrim($image, '/');
    }

    $url = (string)($data['url'] ?? '');

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $name,
        'description' => (string)($data['description'] ?? ''),
        'image' => $image,
        'url' => $url,
        'brand' => [
            '@type' => 'Organization',
            'name' => "Stamp's Tour",
        ],
    ];

    $sameAs = array_filter((array)($data['sameAs'] ?? []));
    if (!empty($sameAs)) {
        $schema['sameAs'] = array_values($sameAs);
    }

    $price = $data['price'] ?? null;
    if ($price !== null) {
        $schema['offers'] = [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => 'USD',
            'price' => number_format((float)$price, 2, '.', ''),
            'availability' => 'https://schema.org/InStock',
            'priceValidUntil' => date('Y-m-d', strtotime('+6 months')),
        ];
    }
    ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php
}
