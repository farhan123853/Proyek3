<?php
/**
 * Header Layout — Villa Joglo Masayu
 * Mencetak <head>, <base href>, style, navigasi header,
 * dan variabel MASAYU_AUTH untuk integrasi script.js
 */

$pageTitle = $pageTitle ?? 'Villa Joglo Masayu';
$pageDesc  = $pageDesc  ?? 'Pengalaman menginap di villa joglo kayu jati autentik bernuansa arsitektur Jawa.';
$bodyClass = $bodyClass ?? 'page-villa';
?>
<!DOCTYPE html>
<html lang="<?= get_active_lang() ?>" dir="ltr">

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
  <link rel="stylesheet" href="assets/css/tailwind.css" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/app.css" />
  <link rel="stylesheet" href="assets/css/auth.css" />
</head>

<body class="<?= e($bodyClass) ?>">

  <!-- MASAYU_AUTH & MASAYU_FLASH: data autentikasi dan notifikasi toast -->
  <script>
  window.MASAYU_LANG = <?= json_encode(get_active_lang()) ?>;
  window.MASAYU_AUTH = <?= json_encode([
      'login'     => is_logged_in(),
      'nama'      => is_logged_in() ? current_user_name() : '',
      'peran'     => is_logged_in() ? current_role() : '',
      'dashboard' => is_logged_in() ? get_role_redirect_url(current_role()) : '',
      'csrf'      => csrf_token(),
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  window.MASAYU_FLASH = <?= json_encode(array_reduce(array_keys($flashes = flash_get()), function ($acc, $type) use ($flashes) {
      foreach ($flashes[$type] as $m) $acc[] = ['type' => $type, 'message' => $m];
      return $acc;
  }, []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>

  <!-- Header injected by script.js -->
  <div id="site-header"></div>

  <!-- Toast Notification Region -->
  <div id="toast-region" aria-live="polite" aria-atomic="false"></div>
