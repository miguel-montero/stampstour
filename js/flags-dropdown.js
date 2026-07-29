// /js/flags-dropdown.js
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);
  const cc      = $('#cc');
  const menu    = $('#menu');
  const flag    = $('#flag');
  const phone   = $('#phone_booking');
  const hidCode = $('#dial_code');
  const hidIso2 = $('#iso2');
  if (!cc || !menu || !flag) return;

  const flagOf = iso2 =>
    (iso2 || '').toUpperCase().replace(/./g, ch => String.fromCodePoint(127397 + ch.charCodeAt(0)));

  // Make dropdown roomy
  menu.classList.remove('w-100');
  menu.style.minWidth  = '24rem';
  menu.style.maxHeight = '50vh';
  menu.style.overflowY = 'auto';

  // State guards to prevent reopen after choosing
  let suppressOpen = false;
  let suppressInputRender = false;

  // Helpers
  const show = on => {
    menu.classList.toggle('show', on);
    cc.setAttribute('aria-expanded', on ? 'true' : 'false');
  };

  function enablePhoneIfPossible() {
    if (phone) phone.disabled = !cc.value.trim();
  }

  function choose(iso2, dial) {
    suppressOpen = true;
    suppressInputRender = true;          // skip the next input render
    cc.value = dial;
    flag.textContent = flagOf(iso2);
    if (hidCode) hidCode.value = dial;
    if (hidIso2) hidIso2.value = iso2;
    enablePhoneIfPossible();
    show(false);                         // close now
    cc.focus({ preventScroll: true });   // return focus without reopening
    setTimeout(() => { suppressOpen = false; }, 200);
  }

  // Load data
  fetch('assets/dial-codes.min.json', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(list => {
      const digits = s => (s || '').replace(/\D/g, '');
      const sorted = [...list].sort((a,b) => {
        const ad = digits(a.dial_code), bd = digits(b.dial_code);
        return ad.length === bd.length ? (parseInt(ad||'0',10)-parseInt(bd||'0',10)) : (ad.length-bd.length);
      });

      const filter = q => {
        q = (q || '').trim().toLowerCase();
        if (!q) return sorted; // all results
        const qd = q.replace(/\D/g,'');
        return sorted.filter(c => {
          const name = (c.name||'').toLowerCase();
          const iso2 = (c.iso2||'').toLowerCase();
          const dial = (c.dial_code||'').toLowerCase();
          const dd   = dial.replace(/\D/g,'');
          return name.includes(q) || iso2.includes(q) || dial.includes(q) || (qd && dd.startsWith(qd));
        });
      };

      function render(items) {
        menu.innerHTML = '';
        if (!items.length) { show(false); return; }
        for (const c of items) {
          if (!c?.iso2 || !c?.dial_code || !c?.name) continue;
          const li = document.createElement('li');
          li.innerHTML = `
            <button type="button" class="dropdown-item d-flex align-items-center" role="option">
              <span class="me-2">${flagOf(c.iso2)}</span>
              <strong class="me-2">${c.dial_code}</strong>
              <span class="text-muted">${c.name}</span>
            </button>`;
          li.firstElementChild.addEventListener('click', () => choose(c.iso2, c.dial_code));
          menu.appendChild(li);
        }
        show(true);
      }

      // Openers (guarded)
      const openWithCurrent = () => { if (!suppressOpen) render(filter(cc.value)); };
      cc.addEventListener('pointerdown', openWithCurrent);
      cc.addEventListener('focus', openWithCurrent);
      cc.addEventListener('click', openWithCurrent);

      // Live filter (skip once after choose)
      cc.addEventListener('input', e => {
        enablePhoneIfPossible();
        if (suppressInputRender) { suppressInputRender = false; return; }
        render(filter(e.target.value));
      });

      // Keyboard
      cc.addEventListener('keydown', e => {
        const items = menu.querySelectorAll('.dropdown-item');
        if (e.key === 'Enter' && items.length) { e.preventDefault(); items[0].click(); }
        if (e.key === 'Escape') show(false);
        if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); items[0].focus(); }
      });
      menu.addEventListener('keydown', e => {
        const items = [...menu.querySelectorAll('.dropdown-item')];
        const i = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); (items[Math.min(i+1, items.length-1)] || items[0]).focus(); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); (items[Math.max(i-1, 0)] || items[0]).focus(); }
        if (e.key === 'Escape') { show(false); cc.focus(); }
      });

      // Close only when clicking outside the area-code wrapper
      const wrapper = cc.closest('.dropdown') || cc.parentElement;
      document.addEventListener('click', e => {
        if (!wrapper.contains(e.target)) show(false);
      });
    })
    .catch(err => console.error('Failed to load assets/dial-codes.min.json', err));
});

// Keep phone disabled until code present (initial state and typing)
document.addEventListener('DOMContentLoaded', () => {
  const c = document.getElementById('cc'), p = document.getElementById('phone_booking');
  if (!c || !p) return;
  const sync = () => { p.disabled = !c.value.trim(); };
  c.addEventListener('input', sync);
  sync();
});
