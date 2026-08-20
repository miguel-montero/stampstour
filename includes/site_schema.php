<?php
// includes/site_schema.php
// Renders sitewide TravelAgency (Organization) JSON-LD - the entity-level
// complement to the per-page Product schema in includes/tour_schema.php.
// Included once from head.php so it appears on every marketing page.

function render_site_organization_schema(): void
{
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'TravelAgency',
        'name' => "Stamp's Tour",
        'url' => 'https://stampstour.com/',
        'logo' => 'https://stampstour.com/img/logolargo.png',
        'description' => 'Small-group and private day tours from Santiago, Chile: Valparaíso & Viña del Mar, Maipo Valley wine country, the Andes, city tours, and cruise transfers.',
        'telephone' => '+56923993146',
        'email' => 'reservations@stampstour.com',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Santiago',
            'addressCountry' => 'CL',
        ],
        'sameAs' => [
            'https://www.instagram.com/stampstour/',
            'https://www.facebook.com/stampstour',
        ],
    ];
    ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php
}
