/* js/lab.js
 * Behaviour for the "lab" UI components (includes/ui/). Vanilla, deferred.
 * - Gallery: [data-lab-gallery-item] links open in <dialog data-lab-lightbox>
 *   with prev/next buttons and arrow keys; Esc closes (native <dialog>).
 *   Without <dialog> support the links just open the image file.
 * - Sticky bar [data-sticky-cta]: hidden while its data-target (#book) is on
 *   screen; label/price follow 'maipo:optionchange' (js/maipo-options.js),
 *   using the bar's data-labels / data-prices JSON.
 * Smooth scrolling for #book links comes from CSS (scroll-behavior), with
 * prefers-reduced-motion handled in css/src/lab-base.css.
 * Load BEFORE js/maipo-options.js so the optionchange listener exists first.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function parse(json) {
    try { return JSON.parse(json || '{}') || {}; } catch (e) { return {}; }
  }

  // ---- gallery lightbox ----
  (function () {
    var dialog = document.querySelector('[data-lab-lightbox]');
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-lab-gallery-item]'));
    if (!dialog || !items.length || typeof dialog.showModal !== 'function') return;
    var img = dialog.querySelector('[data-lab-lightbox-img]');
    var caption = dialog.querySelector('[data-lab-lightbox-caption]');
    var current = 0;

    function show(i) {
      current = (i + items.length) % items.length;
      var item = items[current];
      var thumb = item.querySelector('img');
      img.src = item.getAttribute('href');
      img.alt = thumb ? thumb.alt : '';
      caption.textContent = item.getAttribute('data-caption') || '';
    }

    items.forEach(function (item, i) {
      item.addEventListener('click', function (e) {
        e.preventDefault();
        show(i);
        dialog.showModal();
      });
    });
    dialog.querySelector('[data-lab-lightbox-prev]').addEventListener('click', function () { show(current - 1); });
    dialog.querySelector('[data-lab-lightbox-next]').addEventListener('click', function () { show(current + 1); });
    dialog.querySelector('[data-lab-lightbox-close]').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });
    // a click on the dark backdrop (the dialog box itself, not its content) closes
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
  })();

  // ---- sticky CTA bar ----
  (function () {
    var bar = document.querySelector('[data-sticky-cta]');
    if (!bar) return;
    var labels = parse(bar.getAttribute('data-labels'));
    var prices = parse(bar.getAttribute('data-prices'));
    var labelEl = bar.querySelector('[data-sticky-label]');
    var priceEl = bar.querySelector('[data-sticky-price]');

    document.addEventListener('maipo:optionchange', function (e) {
      var slug = e.detail && e.detail.option;
      if (!slug || prices[slug] == null) return;
      if (labelEl) labelEl.textContent = labels[slug] || '';
      if (priceEl) priceEl.textContent = '$' + Number(prices[slug]).toLocaleString('en-US', { maximumFractionDigits: 0 });
    });

    var target = document.querySelector(bar.getAttribute('data-target') || '#book');
    if (target && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        bar.hidden = entries[0].isIntersecting;
      }).observe(target);
    }
  })();
});
