<!DOCTYPE html>
<html lang="id" dir="ltr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Villa — Joglo Masayu</title>
  <meta name="description"
    content="Detail villa joglo premium di Joglo Masayu. Temukan villa yang sesuai dengan kebutuhan Anda." />
  <meta name="theme-color" content="#FAF3E3" />
  <link rel="icon" type="image/png" href="images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Lato:wght@300;400;700&family=Kaushan+Script&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>

<body class="page-villa">

  <!-- Header injected by script.js -->
  <div id="site-header"></div>

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

  <script src="script.js" defer></script>
</body>

</html>