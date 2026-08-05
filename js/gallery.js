document.addEventListener('DOMContentLoaded', function () {
  var BATCH_SIZE = 16;

  var dataScript = document.getElementById('gallery-photos-data');
  var grid = document.querySelector('.gallery-grid');
  if (!dataScript || !grid) return;

  var allPhotos = JSON.parse(dataScript.textContent || '[]');
  if (allPhotos.length === 0) return;

  var pills = document.querySelectorAll('.gallery-filter-pill');
  var activeTag = '';
  var revealedCount = 0;

  var sentinel = document.createElement('div');
  sentinel.setAttribute('aria-hidden', 'true');
  grid.insertAdjacentElement('afterend', sentinel);

  function matchesFilter(photo) {
    if (activeTag === '') return true;
    return (photo.tags || []).indexOf(activeTag) !== -1;
  }

  function currentMatching() {
    return allPhotos.filter(matchesFilter);
  }

  function buildItem(photo) {
    var item = document.createElement('div');
    item.className = 'gallery-item';
    item.setAttribute('data-tags', (photo.tags || []).join('|'));

    var link = document.createElement('a');
    link.href = '/' + photo.large;
    link.setAttribute('data-lightbox', 'gallery');
    link.className = 'gallery-item-link';
    if (photo.dateLabel) {
      link.setAttribute('data-title', 'Upload date: ' + photo.dateLabel);
    }

    var img = document.createElement('img');
    img.src = '/' + photo.thumb;
    img.loading = 'lazy';
    img.alt = 'Stamps Tour gallery photo';
    link.appendChild(img);
    item.appendChild(link);

    if (photo.dateLabel) {
      var caption = document.createElement('p');
      caption.className = 'gallery-item-date';
      caption.textContent = 'Upload date: ' + photo.dateLabel;
      item.appendChild(caption);
    }

    return item;
  }

  // Appends the next batch on top of what's already rendered - never
  // destroys existing items (that would re-request already-loaded images
  // and cause a visible flicker on every scroll-triggered reveal).
  function appendNextBatch() {
    var matching = currentMatching();
    var nextItems = matching.slice(revealedCount, revealedCount + BATCH_SIZE);
    nextItems.forEach(function (photo) {
      grid.appendChild(buildItem(photo));
    });
    revealedCount += nextItems.length;
    sentinel.style.display = matching.length > revealedCount ? 'block' : 'none';
  }

  function resetAndRenderFirstBatch() {
    grid.innerHTML = '';
    revealedCount = 0;
    appendNextBatch();
  }

  pills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      pills.forEach(function (p) { p.classList.remove('active'); });
      pill.classList.add('active');
      activeTag = pill.getAttribute('data-tag');
      resetAndRenderFirstBatch();
      window.scrollTo({ top: grid.offsetTop - 100, behavior: 'smooth' });
    });
  });

  var observer = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting) {
      appendNextBatch();
    }
  }, { rootMargin: '200px' });
  observer.observe(sentinel);

  resetAndRenderFirstBatch();
});
