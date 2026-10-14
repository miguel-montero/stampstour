<?php
// includes/tours/maipo_options.php
// Content for the three shared Maipo Valley tour options. Prices are NOT
// here - they come from experiencias (exp_name) so admin price changes
// apply everywhere. public_name is the experiencias.nombre_publico the
// migration writes; admin reports categorize bookings by substrings of it
// (see the naming constraint in
// docs/superpowers/specs/2026-10-13-maipo-tour-options-design.md).

function maipo_options(): array
{
    $pickup = [
        'place' => 'Santiago',
        'title' => 'Hotel pickup',
        'duration' => '',
        'icon' => 'icon-location-outline',
        'text' => 'Pickup at your hotel or accommodation in Santiago Centro, Providencia, Las Condes, Vitacura or Recoleta, usually between 7:00 and 7:30 AM. Your exact pickup time is sent the night before.',
    ];
    $quirinca = [
        'place' => 'Isla de Maipo',
        'title' => 'Campo La Quirinca',
        'duration' => '1 hour 30 minutes',
        'icon' => 'icon-camera-alt',
        'text' => 'Start at a small family-run farm and winery. Walk the gardens and fields, learn how wine is made the traditional countryside way, and meet the farm animals, including alpacas and several breeds of chickens. Then relax in the tasting room with locally made wines and a tasting of Chilean pisco. Local artisan products are available to buy.',
    ];
    $santaEma = [
        'place' => 'Isla de Maipo',
        'title' => 'Viña Santa Ema',
        'duration' => '1 hour',
        'icon' => 'icon-wine',
        'text' => "Founded in 1956, Santa Ema makes wines shaped by the Maipo River and the valley's geography. Enjoy a guided tasting of three wines, including one of their blends.",
    ];
    $terraMater = [
        'place' => 'Isla de Maipo',
        'title' => 'Viña TerraMater',
        'duration' => '1 hour',
        'icon' => 'icon-wine',
        'text' => 'Visit TerraMater, a winery also known for its award-winning olive oil, which you can buy in the shop at cellar-door prices.',
    ];
    $ballek = [
        'place' => 'Isla de Maipo',
        'title' => 'Viña Ballek',
        'duration' => '1 hour',
        'icon' => 'icon-wine',
        'text' => 'End the day at a small family-run boutique winery on the banks of the Maipo River. Taste limited-production wines in a relaxed setting, often with the hosts themselves, and take time to enjoy the vineyards and countryside.',
    ];
    $undurraga = [
        'place' => 'Maipo Valley',
        'title' => 'Viña Undurraga',
        'duration' => '1 hour 30 minutes',
        'icon' => 'icon-wine',
        'text' => "Finish at one of Chile's most traditional wineries, with more than 130 years of history. An extended guided tour takes you through the gardens, vineyards, production area and barrel cellar, plus a pre-Columbian exhibit when it is open, and ends with a tasting of four premium wines.",
    ];
    $return = [
        'place' => 'Santiago',
        'title' => 'Return to your hotel',
        'duration' => '',
        'icon' => 'icon-location-outline',
        'text' => 'Drop-off back at your pickup point in Santiago, usually between 5:30 and 6:30 PM depending on traffic.',
    ];

    $includedCommon = [
        'Hotel pickup and drop-off (pickup time sent the night before)',
        'Professional English-speaking guide',
        'Air-conditioned vehicle',
        'Wine and pisco tasting at Campo La Quirinca',
        'Guided tasting of three wines at Viña Santa Ema',
        'Live coordination with your guide via WhatsApp',
    ];
    $notIncludedCommon = [
        'Extra drinks, wine bottles and souvenirs',
    ];
    $lunchNotIncluded = 'Lunch at a winery restaurant (pay at the restaurant or bring your own food)';

    return [
        'classic' => [
            'exp_name' => 'MAIPO_CLASSIC',
            'public_name' => 'Maipo Valley Wine Tour - Classic Route',
            'label' => 'Classic Route',
            'tagline' => 'Ends at boutique Viña Ballek. Lunch at a winery restaurant at your own cost.',
            'lunch_included' => false,
            'stops' => [$pickup, $quirinca, $santaEma, $terraMater, $ballek, $return],
            'included' => array_merge($includedCommon, ['Wine tasting at Viña Ballek']),
            'not_included' => array_merge([$lunchNotIncluded], $notIncludedCommon),
        ],
        'classic-lunch' => [
            'exp_name' => 'MAIPO_CLASSIC_LUNCH',
            'public_name' => 'Classic Maipo Valley Tastings & Lunch',
            'label' => 'Classic Route + Lunch',
            'tagline' => 'Same Classic itinerary, with lunch at a winery restaurant included.',
            'lunch_included' => true,
            'stops' => [$pickup, $quirinca, $santaEma, $terraMater, $ballek, $return],
            'included' => array_merge($includedCommon, ['Lunch at a winery restaurant', 'Wine tasting at Viña Ballek']),
            'not_included' => $notIncludedCommon,
        ],
        'premium' => [
            'exp_name' => 'Maipo',
            'public_name' => 'Maipo Valley Wine Tour - Premium Route with Undurraga',
            'label' => 'Premium Route',
            'tagline' => 'Ends with an extended guided tour of historic Viña Undurraga. Lunch at a winery restaurant at your own cost.',
            'lunch_included' => false,
            'stops' => [$pickup, $quirinca, $santaEma, $terraMater, $undurraga, $return],
            'included' => array_merge($includedCommon, ['Extended guided tour and tasting of four premium wines at Viña Undurraga']),
            'not_included' => array_merge([$lunchNotIncluded], $notIncludedCommon),
        ],
    ];
}

function maipo_default_option(): string
{
    return 'classic';
}

/** Valid slug for any raw ?option= value (string, array, null...) - never warns. */
function maipo_resolve_option($raw): string
{
    $slug = is_string($raw) ? strtolower(trim($raw)) : '';
    return array_key_exists($slug, maipo_options()) ? $slug : maipo_default_option();
}

/** @return array<string,string> slug => experiencias.nombre */
function maipo_option_exp_names(): array
{
    return array_map(fn(array $o) => $o['exp_name'], maipo_options());
}
