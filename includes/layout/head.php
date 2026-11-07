<?php
/**
 * Header Layout — Villa Joglo Masayu
 * Mencetak <head>, <base href>, style, navigasi header, dan bar status pengguna
 */

$pageTitle = $pageTitle ?? 'Villa Joglo Masayu';
$pageDesc  = $pageDesc  ?? 'Pengalaman menginap di villa joglo kayu jati autentik bernuansa arsitektur Jawa.';
$bodyClass = $bodyClass ?? 'page-villa';
?>
<!DOCTYPE html>
<html lang="id" dir="ltr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDesc) ?>" />
  <meta name="theme-color" content="#FAF3E3" />
  
  <!-- Base href untuk memastikan path relatif valid di root maupun subfolder dashboard/ -->
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

<body class="<?= e($bodyClass) ?>">

  <!-- Header injected by script.js -->
  <div id="site-header"></div>

  <!-- Bar Status Pengguna Login -->
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

  <!-- Flash Messages Alert -->
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
