/* js/maipo-options.js
 * Option selector for the Maipo tour page (Classic / Classic + Lunch / Premium).
 * Keeps both selectors, the visible itinerary + "what's included" blocks,
 * the hidden activity_name field and the URL (?option=) in sync, then tells
 * tours.js to recalculate the total via the 'maipo:optionchange' event.
 * Data comes from window.MAIPO_OPTIONS / window.MAIPO_SELECTED (set by the
 * page). Loaded after tours.js so its listener is registered first.
 */
document.addEventListener('DOMContentLoaded', function () {
  var options = window.MAIPO_OPTIONS || {};
  var radios = document.querySelectorAll('[data-tour-options] input[type="radio"]');
  var activity = document.getElementById('activity_name');

  function select(slug, fromUser) {
    if (!options[slug]) return;
    window.MAIPO_SELECTED = slug;
    if (activity) activity.value = options[slug].exp;

    radios.forEach(function (r) { r.checked = (r.value === slug); });
    document.querySelectorAll('[data-option]').forEach(function (el) {
      el.hidden = el.getAttribute('data-option') !== slug;
    });

    if (fromUser && window.history && history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.set('option', slug);
      history.replaceState(null, '', url);
    }

    document.dispatchEvent(new CustomEvent('maipo:optionchange', { detail: { option: slug } }));

    if (fromUser && typeof window.gtag === 'function') {
      window.gtag('event', 'select_item', {
        item_list_name: 'Maipo tour options',
        items: [{ item_id: options[slug].exp, item_name: slug, price: options[slug].adult }]
      });
    }
  }

  radios.forEach(function (r) {
    r.addEventListener('change', function () {
      if (r.checked) select(r.value, true);
    });
  });

  select(window.MAIPO_SELECTED, false);
});
