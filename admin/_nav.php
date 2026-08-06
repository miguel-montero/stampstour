<?php
function stamp_admin_nav(string $active = ''): void {
    $links = [
        'reservations' => ['label' => 'Reservations Calendar', 'href' => '/admin.php'],
        'dashboard' => ['label' => 'Dashboard', 'href' => '/admin/dashboard.php'],
        'check' => ['label' => 'Check Reservations', 'href' => '/admin/check.php'],
        'consolidate-day' => ['label' => 'Consolidate Day', 'href' => '/admin/consolidate-day.php'],
        'consolidate-month' => ['label' => 'Consolidate Month', 'href' => '/admin/consolidate-month.php'],
        'closing' => ['label' => 'Closing', 'href' => '/admin/closing.php'],
        'preferentials' => ['label' => 'Preferentials', 'href' => '/admin/preferentials.php'],
        'private-booking' => ['label' => 'Private Booking', 'href' => '/admin/private-booking.php'],
        'blog' => ['label' => 'Blog', 'href' => '/admin/blog.php'],
    ];
    // Utility pages that aren't part of day-to-day booking operations live
    // under an "Admin Tools" dropdown instead of the flat top-level list.
    $toolsLinks = [
        'gallery' => ['label' => 'Gallery Upload', 'href' => '/admin/gallery-upload.php'],
        'getnet-reconcile' => ['label' => 'Getnet Reconciliation', 'href' => '/admin/getnet-reconcile.php'],
    ];
    $toolsActive = array_key_exists($active, $toolsLinks);
    ?>
    <nav class="navbar navbar-expand navbar-dark bg-dark px-3 mb-3">
      <div class="container-fluid flex-wrap">
        <span class="navbar-brand mb-0 h1">Stamp&#39;s Tour Admin</span>
        <ul class="navbar-nav flex-row flex-wrap">
          <?php foreach ($links as $key => $l): ?>
            <li class="nav-item">
              <a class="nav-link px-2<?= $key === $active ? ' active fw-bold text-white' : '' ?>" href="<?= htmlspecialchars($l['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($l['label'], ENT_QUOTES, 'UTF-8') ?></a>
            </li>
          <?php endforeach; ?>
          <li class="nav-item dropdown">
            <a class="nav-link px-2 dropdown-toggle<?= $toolsActive ? ' active fw-bold text-white' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Admin Tools</a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <?php foreach ($toolsLinks as $key => $l): ?>
                <li><a class="dropdown-item<?= $key === $active ? ' active' : '' ?>" href="<?= htmlspecialchars($l['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($l['label'], ENT_QUOTES, 'UTF-8') ?></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
        </ul>
        <a href="/login.php?logout=1" class="btn btn-outline-danger btn-sm ms-auto">Logout</a>
      </div>
    </nav>
    <?php // Not every admin page that includes this nav loads Bootstrap's JS
    // bundle itself (only preferentials.php currently does) - the dropdown
    // above needs it, so it's guaranteed to load here regardless of caller. ?>
    <script src="/js/bootstrap.bundle.min.js"></script>
    <?php
}
