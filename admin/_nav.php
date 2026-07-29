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
        </ul>
        <a href="/login.php?logout=1" class="btn btn-outline-danger btn-sm ms-auto">Logout</a>
      </div>
    </nav>
    <?php
}
