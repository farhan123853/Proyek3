<!DOCTYPE html>
<html lang="id" dir="ltr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Joglo Masayu — Villa Joglo Premium di Jawa</title>
  <meta name="description"
    content="Joglo Masayu menawarkan pengalaman menginap premium di villa joglo kayu jati autentik. Rasakan ketenangan dan keindahan arsitektur Jawa yang sesungguhnya." />
  <meta name="theme-color" content="#FAF3E3" />
  <link rel="icon" type="image/png" href="images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Lato:wght@300;400;700&family=Kaushan+Script&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>

<body id="top">

  <!-- Header injected by script.js -->
  <div id="site-header"></div>

  <!-- "Kembali" Button to top of homepage -->
  <button id="btnBackToTop" class="btn btn-back-top" type="button" aria-label="Kembali ke atas">
    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <path d="M8 13V3M3 8L8 3L13 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
        stroke-linejoin="round" />
    </svg>
    <span class="btn-back-top-label" data-i18n="nav.backToTop">Kembali</span>
  </button>

  <main>

    <!-- ===== HERO ===== -->
    <section id="hero" class="section-hero" aria-label="Hero">
      <div class="hero-inner container">

        <div class="hero-text reveal">
          <p class="eyebrow" data-i18n="hero.eyebrow">Villa joglo premium</p>
          <h1 class="hero-heading" data-i18n-html="hero.heading">Pulang ke rumah kayu,<br>dalam <em>tenang.</em></h1>
          <p class="hero-tagline" data-i18n="hero.tagline">Harmoni arsitektur Jawa, ketenangan alam, dan keramahtamahan
            tulus dalam satu tempat.</p>
          <div class="hero-actions">
            <a href="#villa" class="btn btn-ghost" data-i18n="hero.cta1">Lihat villa</a>
            <a href="#kontak" class="btn btn-ghost" data-i18n="hero.cta2">Hubungi kami</a>
          </div>
        </div>

        <div class="hero-slider-wrap reveal reveal-delay-1" aria-label="Foto villa" role="region">
          <div class="hero-slider" id="heroSlider" aria-live="polite">
            <!-- Slides injected by script.js -->
          </div>
          <button class="slider-arrow slider-arrow--prev" id="sliderPrev" aria-label="Foto sebelumnya" type="button">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M11 14L6 9L11 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <button class="slider-arrow slider-arrow--next" id="sliderNext" aria-label="Foto berikutnya" type="button">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
              <path d="M7 4L12 9L7 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
        </div>

      </div>
    </section>

    <!-- ===== TENTANG KAMI ===== -->
    <section id="tentang" class="section-tentang" aria-labelledby="tentang-heading">
      <div class="tentang-inner container">

        <div class="tentang-photo reveal">
          <div class="photo-frame">
            <img src="https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=720&q=80"
              alt="Interior villa joglo Masayu" width="720" height="900" loading="lazy" class="photo-cover" />
          </div>
        </div>

        <div class="tentang-content">
          <p class="eyebrow reveal" data-i18n="tentang.eyebrow">Tentang kami</p>
          <h2 id="tentang-heading" class="section-heading reveal" data-i18n-html="tentang.heading">Rumah kayu
            yang<br>terasa <em>pulang</em></h2>
          <p class="body-text reveal" data-i18n="tentang.p1">Joglo Masayu lahir dari kecintaan mendalam pada warisan
            arsitektur Jawa. Setiap tiang dan ukiran dipilih dengan teliti, membawa jiwa rumah joglo asli ke dalam
            pengalaman menginap yang nyaman dan bermakna.</p>
          <p class="body-text reveal" data-i18n="tentang.p2">Di sini, waktu melambat. Langit terbuka, udara segar, dan
            ketenangan menjadi teman sejati setiap tamu. Kami percaya bahwa menginap terbaik adalah yang membuat hati
            benar-benar pulang.</p>

          <div class="stats-row">
            <div class="stat reveal">
              <span class="stat-num" data-i18n="tentang.stat1num">3</span>
              <span class="stat-label" data-i18n="tentang.stat1label">Tipe villa</span>
            </div>
            <div class="stat reveal reveal-delay-1">
              <span class="stat-num" data-i18n="tentang.stat2num">100%</span>
              <span class="stat-label" data-i18n="tentang.stat2label">Kayu jati</span>
            </div>
            <div class="stat reveal reveal-delay-2">
              <span class="stat-num" data-i18n="tentang.stat3num">24 jam</span>
              <span class="stat-label" data-i18n="tentang.stat3label">Layanan</span>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- ===== VILLA ===== -->
    <section id="villa" class="section-villa" aria-labelledby="villa-heading">
      <div class="container">
        <div class="villa-header reveal">
          <div class="villa-header-text">
            <p class="eyebrow" data-i18n="villa.eyebrow">Villa kami</p>
            <h2 id="villa-heading" class="section-heading" data-i18n="villa.heading">Pilih joglo yang paling cocok</h2>
          </div>
          <a id="villaWhatsAppBtn" href="#" class="btn btn-ghost" target="_blank" rel="noopener"
            data-i18n="villa.cta">Tanya ketersediaan</a>
        </div>

        <div class="villa-layout">
          <div class="villa-list" id="villaList" role="list">
            <!-- Villa rows injected by script.js -->
          </div>
          <div class="villa-sticky-photo" id="villaStickyPhoto" aria-hidden="true">
            <div class="sticky-photo-inner" id="stickyPhotoInner">
              <!-- Sticky photo injected by script.js -->
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== GALERI ===== -->
    <section id="galeri" class="section-galeri" aria-labelledby="galeri-heading">
      <div class="container">
        <p class="eyebrow reveal" data-i18n="galeri.eyebrow">Galeri foto</p>
        <h2 id="galeri-heading" class="section-heading reveal" data-i18n="galeri.heading">Sudut-sudut Joglo Masayu</h2>
        <div class="gallery-grid" id="galleryGrid" role="list">
          <!-- Gallery items injected by script.js -->
        </div>
      </div>
    </section>

    <!-- ===== TESTIMONI ===== -->
    <section id="testimoni" class="section-testimoni" aria-labelledby="testimoni-heading">
      <div class="container">
        <div class="testimoni-inner reveal">
          <div class="testimoni-line" aria-hidden="true"></div>
          <blockquote class="testimoni-quote">
            <p class="quote-text" data-i18n="testimoni.quote">"Menginap di Joglo Masayu terasa seperti pulang ke rumah
              leluhur — tenang, hangat, dan penuh cerita. Kami selalu ingin kembali."</p>
            <footer class="quote-attribution">
              <cite class="quote-name" data-i18n="testimoni.name">Arinta & Baskara</cite>
              <span class="quote-origin" data-i18n="testimoni.origin">Jakarta, 2024</span>
            </footer>
          </blockquote>
        </div>
      </div>
    </section>

    <!-- ===== KONTAK ===== -->
    <section id="kontak" class="section-kontak" aria-labelledby="kontak-heading">
      <div class="container">
        <div class="kontak-header reveal">
          <p class="eyebrow" data-i18n="kontak.eyebrow">Hubungi kami</p>
          <h2 id="kontak-heading" class="section-heading" data-i18n="kontak.heading">Siap menginap bersama kami?</h2>
          <p class="body-text" data-i18n="kontak.sub">Kami dengan senang hati membantu Anda menemukan villa yang tepat
            dan menjawab setiap pertanyaan.</p>
        </div>

        <div class="kontak-grid reveal">
          <div class="kontak-info-card">
            <div>
              <h3 class="kontak-card-title" data-i18n="kontak.cardTitle">Informasi & Lokasi</h3>
              <p class="kontak-card-desc" data-i18n="kontak.cardDesc">Kunjungi atau hubungi tim kami untuk reservasi dan
                panduan rute menuju villa.</p>

              <div class="kontak-items">
                <div class="kontak-item">
                  <div class="kontak-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                      stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                      <circle cx="12" cy="10" r="3" />
                    </svg>
                  </div>
                  <div class="kontak-item-text">
                    <span class="kontak-item-label" data-i18n="kontak.alamatLabel">Alamat Villa</span>
                    <p class="kontak-item-val" data-i18n="kontak.alamatVal">Jl. Kaliurang Km 14, Sukoharjo, Ngaglik,
                      Sleman, D.I. Yogyakarta</p>
                  </div>
                </div>

                <div class="kontak-item">
                  <div class="kontak-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                      stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </svg>
                  </div>
                  <div class="kontak-item-text">
                    <span class="kontak-item-label" data-i18n="kontak.jamLabel">Waktu Operasional</span>
                    <p class="kontak-item-val" data-i18n="kontak.jamVal">Check-in: 14.00 WIB · Check-out: 12.00 WIB</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="kontak-actions">
              <a id="kontakWhatsAppBtn" href="#" class="btn btn-gold" target="_blank" rel="noopener"
                data-i18n="kontak.whatsapp">Chat WhatsApp</a>
              <a id="kontakInstaBtn" href="#" class="btn btn-ghost btn-instagram" target="_blank" rel="noopener">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                  stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                  <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                  <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                </svg>
                <span data-i18n="kontak.instagram">Instagram</span>
              </a>
              <a id="kontakMapsBtn" href="https://maps.google.com/?q=Villa+Joglo+Yogyakarta"
                class="btn btn-ghost btn-maps" target="_blank" rel="noopener">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                  stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6" />
                  <line x1="8" y1="2" x2="8" y2="18" />
                  <line x1="16" y1="6" x2="16" y2="22" />
                </svg>
                <span data-i18n="kontak.mapsLink">Buka Maps</span>
              </a>
            </div>
          </div>

          <div class="kontak-map-wrapper">
            <div class="map-frame">
              <iframe id="kontakMapIframe"
                src="https://maps.google.com/maps?q=Yogyakarta&t=&z=13&ie=UTF8&iwloc=&output=embed" width="100%"
                height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade" title="Peta Lokasi Villa Joglo Masayu">
              </iframe>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Footer injected by script.js -->
  <div id="site-footer"></div>

  <!-- Lightbox -->
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