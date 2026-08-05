document.addEventListener('DOMContentLoaded', function () {
  var BATCH_SIZE = 16; // roughly 2-3 rows on most screen widths

  var pills = document.querySelectorAll('.gallery-filter-pill');
  var allItems = Array.prototype.slice.call(document.querySelectorAll('.gallery-item'));
  var grid = document.querySelector('.gallery-grid');
  var activeTag = '';
  var revealedCount = BATCH_SIZE;

  if (!grid || allItems.length === 0) return;

  var sentinel = document.createElement('div');
  sentinel.setAttribute('aria-hidden', 'true');
  grid.insertAdjacentElement('afterend', sentinel);

  function matchesFilter(item) {
    if (activeTag === '') return true;
    var itemTags = (item.getAttribute('data-tags') || '').split('|');
    return itemTags.indexOf(activeTag) !== -1;
  }

  // A single "hidden" class does double duty: an item is hidden either
  // because it doesn't match the active tag filter, or because scrolling
  // hasn't revealed it yet - so filtering and infinite scroll compose
  // correctly instead of fighting over two separate hide mechanisms.
  function render() {
    var matching = allItems.filter(matchesFilter);
    matching.forEach(function (item, index) {
      item.classList.toggle('gallery-hidden', index >= revealedCount);
    });
    allItems.forEach(function (item) {
      if (!matchesFilter(item)) item.classList.add('gallery-hidden');
    });
    sentinel.style.display = matching.length > revealedCount ? 'block' : 'none';
  }

  pills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      pills.forEach(function (p) { p.classList.remove('active'); });
      pill.classList.add('active');
      activeTag = pill.getAttribute('data-tag');
      revealedCount = BATCH_SIZE;
      render();
      window.scrollTo({ top: grid.offsetTop - 100, behavior: 'smooth' });
    });
  });

  var observer = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting) {
      revealedCount += BATCH_SIZE;
      render();
    }
  }, { rootMargin: '200px' });
  observer.observe(sentinel);

  render();
});
