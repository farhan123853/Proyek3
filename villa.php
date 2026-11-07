<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Villa — Joglo Masayu';
$pageDesc  = 'Detail villa joglo premium di Joglo Masayu. Temukan villa yang sesuai dengan kebutuhan Anda.';
?>
<!DOCTYPE html>
<html lang="id" dir="ltr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDesc) ?>" />
  <meta name="theme-color" content="#FAF3E3" />
  <base href="<?= BASE_URL ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Lato:wght@300;400;700&family=Kaushan+Script&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/app.css" />
</head>

<body class="page-villa">

  <!-- Header injected by script.js -->
  <div id="site-header"></div>

  <!-- Bar Status Pengguna Login & Flash Messages -->
  <?php if (is_logged_in()): ?>
    <div class="user-status-bar">
      <div class="container user-status-inner">
        <div class="user-status-info">
          <span class="user-status-greeting">Halo, <strong><?= e(current_user_name()) ?></strong></span>
          <span class="user-role-badge badge-<?= e(current_role()) ?>"><?= strtoupper(e(current_role())) ?></span>
        </div>
        <div class="user-status-links">
          <?php if (current_role() === 'pelanggan'): ?>
            <a href="reservasi-saya.php" class="user-status-link">Reservasi Saya</a>
            <a href="reservasi.php" class="user-status-link">Pesan Villa</a>
          <?php elseif (current_role() === 'pengelola'): ?>
            <a href="dashboard/pengelola.php" class="user-status-link">Dashboard Pengelola</a>
          <?php elseif (current_role() === 'pemilik'): ?>
            <a href="dashboard/pemilik.php" class="user-status-link">Dashboard Pemilik</a>
          <?php elseif (current_role() === 'admin'): ?>
            <a href="dashboard/admin.php" class="user-status-link">Dashboard Admin</a>
          <?php endif; ?>
          <a href="logout.php" class="user-status-logout">Keluar</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="container flash-container">
    <?php foreach (flash_get() as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert alert-<?= e($type) ?>" role="alert">
          <span><?= e($msg) ?></span>
          <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <!-- Sub-bar: back button only -->
  <div class="sub-bar" id="subBar">
    <div class="container">
      <button class="btn btn-ghost btn-back" id="backBtn" type="button">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
        <span data-i18n="villa.back">Kembali</span>
      </button>
    </div>
  </div>

  <main id="villaDetailMain">

    <!-- Detail content injected by script.js -->
    <div id="villaDetailContent" class="container">
      <!-- Loading state -->
      <div class="villa-detail-loading" id="villaDetailLoading" aria-live="polite">
        <div class="loading-bar"></div>
      </div>
    </div>

    <!-- Widget Cuaca Lokasi Villa -->
    <div class="container" style="margin-top: 1.5rem; margin-bottom: 2.5rem;">
      <div id="weatherWidgetContainer"></div>
    </div>

  </main>

  <!-- Footer injected by script.js -->
  <div id="site-footer"></div>

  <!-- Lightbox (shared) -->
  <div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="Lightbox foto" hidden>
    <div class="lightbox-overlay"></div>
    <div class="lightbox-content">
      <button class="lightbox-close" id="lightboxClose" aria-label="Tutup lightbox" type="button">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
        </svg>
      </button>
      <button class="lightbox-arrow lightbox-arrow--prev" id="lightboxPrev" aria-label="Foto sebelumnya" type="button">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M13 16L7 10L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>
      <img src="" alt="" id="lightboxImg" class="lightbox-img" />
      <button class="lightbox-arrow lightbox-arrow--next" id="lightboxNext" aria-label="Foto berikutnya" type="button">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>
    </div>
  </div>

  <script src="assets/js/script.js" defer></script>
  <script src="assets/js/cuaca.js" defer></script>
</body>

</html>