<?php
/**
 * Halaman Informasi Prakiraan Cuaca — Villa Joglo Masayu
 * Kebutuhan: F-02, F-03
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Prakiraan Cuaca — Villa Joglo Masayu';
$pageDesc  = 'Informasi prakiraan cuaca di sekitar kawasan Villa Joglo Masayu, Ngaglik, Sleman dari BMKG.';
$bodyClass = 'page-villa';
$extraScripts = ['assets/js/cuaca.js'];

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 2.5rem); padding-bottom: 5rem;">
  <div style="max-width: 860px; margin: 0 auto;">
    
    <div style="margin-bottom: 2rem;">
      <p class="eyebrow">Informasi Cuaca</p>
      <h1 class="section-heading">Kondisi Cuaca Sekitar Villa</h1>
      <p class="body-text">
        Untuk kenyamanan rencana liburan dan menginap Anda di Villa Joglo Masayu, berikut adalah informasi prakiraan cuaca terkini di wilayah Sukoharjo, Ngaglik, Sleman. Data diperbarui secara berkala langsung dari Badan Meteorologi, Klimatologi, dan Geofisika (BMKG).
      </p>
    </div>

    <!-- Kontainer Widget Cuaca -->
    <div id="weatherWidgetContainer"></div>

    <div class="form-card" style="margin-top: 2rem;">
      <h3 style="font-family: 'Playfair Display', serif; font-size: 1.35rem; color: var(--teak); margin-bottom: 0.75rem;">Tentang Sumber Data</h3>
      <p style="font-size: 14.5px; color: var(--wood); line-height: 1.7; margin-bottom: 1.25rem;">
        Data cuaca diambil melalui API Terbuka BMKG (Prakiraan Cuaca Tingkat IV). Suhu, kelembapan, arah, dan kecepatan angin diproyeksikan untuk rentang 3 hari ke depan dalam interval 3 jam.
      </p>
      <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <a href="reservasi.php" class="btn btn-gold">Pesan Villa Sekarang</a>
        <a href="index.php" class="btn btn-ghost">Kembali ke Beranda</a>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
