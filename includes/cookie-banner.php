<?php /* includes/cookie-banner.php
 * Shared GDPR-strict cookie consent banner (Accept / Reject only).
 * No parameters. Include once per page, immediately after includes/header.php.
 * Vanilla JS - no jQuery dependency (jQuery loads at inconsistent points
 * across the different page templates; this needs to be interactive early).
 * Depends on the Consent Mode v2 default/update wiring in includes/head.php.
 */
?>
<div id="cookie-consent-banner" class="cookie-consent-banner" hidden>
  <div class="container">
    <div class="cookie-consent-inner">
      <p class="cookie-consent-text">
        We use cookies for site analytics (Google Analytics) and our live-chat widget.
        We won't set them unless you click Accept. See our
        <a href="privacy.php">Privacy Policy</a>.
      </p>
      <div class="cookie-consent-actions">
        <button type="button" id="cookie-consent-reject" class="btn_1 outline">Reject</button>
        <button type="button" id="cookie-consent-accept" class="btn_1">Accept</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var STORAGE_KEY = 'stamp_cookie_consent';
  var banner = document.getElementById('cookie-consent-banner');
  if (!banner) return;

  function getStored() { try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; } }
  function setStored(v) { try { localStorage.setItem(STORAGE_KEY, v); } catch (e) {} }
  function updateConsent(granted) {
    if (typeof window.gtag !== 'function') return;
    var v = granted ? 'granted' : 'denied';
    window.gtag('consent', 'update', {
      'ad_storage': v, 'ad_user_data': v, 'ad_personalization': v, 'analytics_storage': v
    });
  }
  function show() { banner.hidden = false; }
  function hide() { banner.hidden = true; }

  var acceptBtn = document.getElementById('cookie-consent-accept');
  var rejectBtn = document.getElementById('cookie-consent-reject');

  acceptBtn.addEventListener('click', function () {
    setStored('granted');
    updateConsent(true);
    hide();
    if (typeof window.__initOpenWidget === 'function') window.__initOpenWidget();
  });

  rejectBtn.addEventListener('click', function () {
    setStored('denied');
    updateConsent(false);
    hide();
  });

  // "Manage cookies" footer link (includes/footer.php) reopens the banner.
  // Delegated on document since footer.php renders later in the DOM than
  // this include, so the link may not exist yet when this script runs.
  document.addEventListener('click', function (e) {
    var link = e.target.closest && e.target.closest('#cookie-consent-manage');
    if (link) { e.preventDefault(); show(); }
  });

  var stored = getStored();
  if (stored !== 'granted' && stored !== 'denied') show(); // no choice made yet
})();
</script>
