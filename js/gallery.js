document.addEventListener('DOMContentLoaded', function () {
  var pills = document.querySelectorAll('.gallery-filter-pill');
  var items = document.querySelectorAll('.gallery-item');

  pills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      pills.forEach(function (p) { p.classList.remove('active'); });
      pill.classList.add('active');

      var tag = pill.getAttribute('data-tag');
      items.forEach(function (item) {
        var itemTags = (item.getAttribute('data-tags') || '').split('|');
        var matches = tag === '' || itemTags.indexOf(tag) !== -1;
        item.classList.toggle('gallery-hidden', !matches);
      });
    });
  });
});
