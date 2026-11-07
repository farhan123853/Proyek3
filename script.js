'use strict';

const CONFIG = {
  whatsappNumber: '6281234567890', // ← Replace with your real WhatsApp number (no + or spaces)
  instagramUrl: 'https://www.instagram.com/joglomasayu/', // ← Replace with real Instagram URL
  mapsUrl: 'https://maps.google.com/?q=Villa+Joglo+Yogyakarta',
  mapsEmbedUrl: 'https://maps.google.com/maps?q=Yogyakarta&t=&z=13&ie=UTF8&iwloc=&output=embed',

  heroImages: [
    'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=900&q=80', // hero-1
    'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=900&q=80', // hero-2
    'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=900&q=80', // hero-3
    'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=900&q=80', // hero-4
    // Add images/hero-1.jpg, hero-2.jpg etc. here when real photos are ready
  ],

  galleryImages: [
    { src: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', alt: 'Kolam renang villa joglo', span: 'tall' },
    { src: 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&q=80', alt: 'Interior kamar tidur joglo', span: '' },
    { src: 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&q=80', alt: 'Ruang tamu terbuka', span: '' },
    { src: 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80', alt: 'Dapur joglo tradisional', span: '' },
    { src: 'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=800&q=80', alt: 'Teras villa dengan pemandangan', span: '' },
    { src: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80', alt: 'Detail interior kayu jati joglo', span: '' },
    { src: 'https://images.unsplash.com/photo-1578898887932-dce23a595ad4?w=800&q=80', alt: 'Kamar mandi outdoor', span: '' },
    { src: 'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=800&q=80', alt: 'Sarapan pagi di joglo', span: 'wide' },
    // Add images/gallery-1.jpg etc. here when real photos are ready
  ],
};

/* ══════════════════════════════════════════════════════════
   2. TRANSLATIONS
   ══════════════════════════════════════════════════════════ */

const TRANSLATIONS = {
  id: {
    // Meta
    'meta.title.home': 'Joglo Masayu — Villa Joglo Premium di Jawa',
    'meta.desc.home': 'Joglo Masayu menawarkan pengalaman menginap premium di villa joglo kayu jati autentik. Rasakan ketenangan dan keindahan arsitektur Jawa yang sesungguhnya.',
    'meta.title.villa': 'Villa — Joglo Masayu',
    'meta.desc.villa': 'Detail villa joglo premium di Joglo Masayu.',

    // Header
    'nav.tentang': 'Tentang',
    'nav.villa': 'Villa',
    'nav.galeri': 'Galeri',
    'nav.kontak': 'Kontak',
    'nav.pesan': 'Pesan Sekarang',
    'nav.backToTop': 'Kembali',
    'nav.backToTop.aria': 'Kembali ke atas',

    // Hero
    'hero.eyebrow': 'Villa joglo premium',
    'hero.heading': 'Pulang ke rumah kayu,<br>dalam <em>tenang.</em>',
    'hero.tagline': 'Harmoni arsitektur Jawa, ketenangan alam, dan keramahtamahan tulus dalam satu tempat.',
    'hero.cta1': 'Lihat villa',
    'hero.cta2': 'Hubungi kami',

    // Tentang
    'tentang.eyebrow': 'Tentang kami',
    'tentang.heading': 'Rumah kayu yang<br>terasa <em>pulang</em>',
    'tentang.p1': 'Joglo Masayu lahir dari kecintaan mendalam pada warisan arsitektur Jawa. Setiap tiang dan ukiran dipilih dengan teliti, membawa jiwa rumah joglo asli ke dalam pengalaman menginap yang nyaman dan bermakna.',
    'tentang.p2': 'Di sini, waktu melambat. Langit terbuka, udara segar, dan ketenangan menjadi teman sejati setiap tamu. Kami percaya bahwa menginap terbaik adalah yang membuat hati benar-benar pulang.',
    'tentang.stat1num': '3',
    'tentang.stat1label': 'Tipe villa',
    'tentang.stat2num': '100%',
    'tentang.stat2label': 'Kayu jati',
    'tentang.stat3num': '24 jam',
    'tentang.stat3label': 'Layanan',

    // Villa list
    'villa.eyebrow': 'Villa kami',
    'villa.heading': 'Pilih joglo yang paling cocok',
    'villa.cta': 'Tanya ketersediaan',
    'villa.from': 'mulai dari',

    // Gallery
    'galeri.eyebrow': 'Galeri foto',
    'galeri.heading': 'Sudut-sudut Joglo Masayu',

    // Testimoni
    'testimoni.quote': '"Menginap di Joglo Masayu terasa seperti pulang ke rumah leluhur — tenang, hangat, dan penuh cerita. Kami selalu ingin kembali."',
    'testimoni.name': 'Arinta & Baskara',
    'testimoni.origin': 'Jakarta, 2024',

    // Kontak
    'kontak.eyebrow': 'Hubungi kami',
    'kontak.heading': 'Siap menginap bersama kami?',
    'kontak.sub': 'Kami dengan senang hati membantu Anda menemukan villa yang tepat dan menjawab setiap pertanyaan.',
    'kontak.whatsapp': 'Chat WhatsApp',
    'kontak.instagram': 'Instagram',
    'kontak.cardTitle': 'Informasi & Lokasi',
    'kontak.cardDesc': 'Kunjungi atau hubungi tim kami untuk reservasi dan panduan rute menuju villa.',
    'kontak.alamatLabel': 'Alamat Villa',
    'kontak.alamatVal': 'Jl. Kaliurang Km 14, Sukoharjo, Ngaglik, Sleman, D.I. Yogyakarta',
    'kontak.jamLabel': 'Waktu Operasional',
    'kontak.jamVal': 'Check-in: 14.00 WIB · Check-out: 12.00 WIB',
    'kontak.mapsLink': 'Buka Maps',

    // Footer
    'footer.desc': 'Villa joglo premium dengan arsitektur Jawa autentik di lingkungan yang tenang dan asri.',
    'footer.menu': 'Menu',
    'footer.contact': 'Kontak',
    'footer.copyright': '© 2024 Joglo Masayu. Semua hak dilindungi.',

    // Villa detail
    'villa.back': 'Kembali',
    'villa.facilities': 'Fasilitas villa',
    'villa.weekday': 'Senin – Kamis',
    'villa.weekend': 'Jum\'at – Minggu',
    'villa.extrabed': 'Extra bed',
    'villa.capacity': 'Kapasitas',
    'villa.capacity.std': 'tamu standar',
    'villa.capacity.max': 'maks. dengan extra bed',
    'villa.price.note': 'Harga dapat berubah pada periode tertentu (misal: saat high season).',
    'villa.book': 'Pesan sekarang',
    'villa.ask': 'Tanya via WhatsApp',
    'villa.other': 'Villa lainnya',
    'villa.from.price': 'Mulai dari',
    'villa.notfound.h': 'Villa tidak ditemukan',
    'villa.notfound.p': 'Maaf, villa yang Anda cari tidak tersedia. Silakan kembali ke daftar villa kami.',
    'villa.notfound.btn': 'Kembali ke villa',
    'villa.wa.book': 'Halo, saya ingin memesan {villa}. Mohon informasi ketersediaan dan detailnya. Terima kasih.',
    'villa.wa.ask': 'Halo, saya ingin menanyakan informasi tentang {villa}. Terima kasih.',
  },

  en: {
    // Meta
    'meta.title.home': 'Joglo Masayu — Premium Joglo Villas in Java',
    'meta.desc.home': 'Joglo Masayu offers a premium stay in authentic teak joglo villas. Experience the calm and beauty of true Javanese architecture.',
    'meta.title.villa': 'Villa — Joglo Masayu',
    'meta.desc.villa': 'Premium joglo villa details at Joglo Masayu.',

    // Header
    'nav.tentang': 'About',
    'nav.villa': 'Villas',
    'nav.galeri': 'Gallery',
    'nav.kontak': 'Contact',
    'nav.pesan': 'Book Now',
    'nav.backToTop': 'Back',
    'nav.backToTop.aria': 'Back to top',

    // Hero
    'hero.eyebrow': 'Premium joglo villa',
    'hero.heading': 'Come home to teak wood,<br>find <em>stillness.</em>',
    'hero.tagline': 'Javanese architecture, the calm of nature, and sincere hospitality — all in one place.',
    'hero.cta1': 'See villas',
    'hero.cta2': 'Get in touch',

    // Tentang
    'tentang.eyebrow': 'About us',
    'tentang.heading': 'A wooden home that<br>feels like <em>returning</em>',
    'tentang.p1': 'Joglo Masayu was born from a deep love for Javanese architectural heritage. Every pillar and carving is chosen with care, bringing the soul of a true joglo home into a comfortable and meaningful stay.',
    'tentang.p2': 'Here, time slows down. Open skies, fresh air, and quiet become your companions. We believe the finest stay is one that truly brings you home.',
    'tentang.stat1num': '3',
    'tentang.stat1label': 'Villa types',
    'tentang.stat2num': '100%',
    'tentang.stat2label': 'Teak wood',
    'tentang.stat3num': '24 hrs',
    'tentang.stat3label': 'Service',

    // Villa list
    'villa.eyebrow': 'Our villas',
    'villa.heading': 'Find your perfect joglo',
    'villa.cta': 'Check availability',
    'villa.from': 'from',

    // Gallery
    'galeri.eyebrow': 'Photo gallery',
    'galeri.heading': 'Corners of Joglo Masayu',

    // Testimoni
    'testimoni.quote': '"Staying at Joglo Masayu felt like coming home to our ancestors\' house — peaceful, warm, and full of stories. We always want to return."',
    'testimoni.name': 'Arinta & Baskara',
    'testimoni.origin': 'Jakarta, 2024',

    // Kontak
    'kontak.eyebrow': 'Get in touch',
    'kontak.heading': 'Ready to stay with us?',
    'kontak.sub': 'We\'d love to help you find the perfect villa and answer any questions you have.',
    'kontak.whatsapp': 'Chat on WhatsApp',
    'kontak.instagram': 'Instagram',
    'kontak.cardTitle': 'Information & Location',
    'kontak.cardDesc': 'Visit or contact our team for reservations and directions to the villa.',
    'kontak.alamatLabel': 'Villa Address',
    'kontak.alamatVal': 'Jl. Kaliurang Km 14, Sukoharjo, Ngaglik, Sleman, D.I. Yogyakarta',
    'kontak.jamLabel': 'Operating Hours',
    'kontak.jamVal': 'Check-in: 2:00 PM · Check-out: 12:00 PM',
    'kontak.mapsLink': 'Open Maps',

    // Footer
    'footer.desc': 'Premium joglo villas with authentic Javanese architecture in a tranquil, natural setting.',
    'footer.menu': 'Menu',
    'footer.contact': 'Contact',
    'footer.copyright': '© 2024 Joglo Masayu. All rights reserved.',

    // Villa detail
    'villa.back': 'Back',
    'villa.facilities': 'Villa facilities',
    'villa.weekday': 'Mon – Thu',
    'villa.weekend': 'Fri – Sun',
    'villa.extrabed': 'Extra bed',
    'villa.capacity': 'Capacity',
    'villa.capacity.std': 'standard guests',
    'villa.capacity.max': 'max. with extra bed',
    'villa.price.note': 'Rates may vary during certain periods (e.g. peak season).',
    'villa.book': 'Book now',
    'villa.ask': 'Ask via WhatsApp',
    'villa.other': 'Other villas',
    'villa.from.price': 'From',
    'villa.notfound.h': 'Villa not found',
    'villa.notfound.p': 'Sorry, the villa you\'re looking for isn\'t available. Please return to our villa list.',
    'villa.notfound.btn': 'Back to villas',
    'villa.wa.book': 'Hello, I\'d like to book {villa}. Could you please share availability and details? Thank you.',
    'villa.wa.ask': 'Hello, I\'d like to learn more about {villa}. Thank you.',
  },
};

/* ══════════════════════════════════════════════════════════
   3. VILLAS DATA
   ══════════════════════════════════════════════════════════ */

const VILLAS = [
  {
    id: 'joglo-wulung',
    slug: 'joglo-wulung',
    num: '01',
    name: 'Joglo Wulung',
    code: 'Villa 01',
    photos: [
      'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200&q=80',
      'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200&q=80',
      'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80',
      'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=1200&q=80',
      // Replace with: images/villa-wulung-1.jpg, villa-wulung-2.jpg, etc.
    ],
    price: {
      weekday: { id: 'Rp 1.500.000', en: 'IDR 1,500,000' },
      weekend: { id: 'Rp 2.000.000', en: 'IDR 2,000,000' },
      extrabed: { id: 'Rp 350.000', en: 'IDR 350,000' },
    },
    priceFrom: { id: 'Rp 1.500.000', en: 'IDR 1,500,000' },
    capacity: { std: 2, max: 3 },
    meta: { id: '1 kamar · kolam renang · sarapan', en: '1 bedroom · pool · breakfast' },
    tagline: {
      id: 'Keintiman dan ketenangan di bawah atap joglo tertua kami.',
      en: 'Intimacy and quiet beneath our oldest joglo roof.',
    },
    description: {
      id: 'Joglo Wulung adalah villa paling intim di Masayu. Dibangun dari kayu jati tua berusia lebih dari 80 tahun, villa ini menghadirkan nuansa rumah leluhur yang hangat dan autentik. Satu kamar tidur dengan ranjang king, kamar mandi semi-terbuka, teras dengan pemandangan sawah, dan kolam renang pribadi menjadikan Wulung tempat sempurna untuk bulan madu atau retreat dua orang.',
      en: 'Joglo Wulung is our most intimate villa. Built from 80-year-old teak, it carries the warmth of an ancestral home. One king bedroom, a semi-open bathroom, a terrace overlooking the rice fields, and a private pool make Wulung the perfect retreat for two.',
    },
    facilities: {
      id: ['1 kamar tidur king', 'Kolam renang pribadi', 'Kamar mandi semi-terbuka', 'Teras & hammock', 'AC & kipas langit-langit', 'Wi-Fi', 'Sarapan untuk 2 orang', 'Pembuat kopi', 'Minibar', 'Handuk & amenities'],
      en: ['1 king bedroom', 'Private pool', 'Semi-open bathroom', 'Terrace & hammock', 'AC & ceiling fan', 'Wi-Fi', 'Breakfast for 2', 'Coffee maker', 'Minibar', 'Towels & amenities'],
    },
  },

  {
    id: 'joglo-saren',
    slug: 'joglo-saren',
    num: '02',
    name: 'Joglo Saren',
    code: 'Villa 02',
    photos: [
      'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=1200&q=80',
      'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80',
      'https://images.unsplash.com/photo-1578898887932-dce23a595ad4?w=1200&q=80',
      'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=1200&q=80',
      // Replace with: images/villa-saren-1.jpg, etc.
    ],
    price: {
      weekday: { id: 'Rp 2.200.000', en: 'IDR 2,200,000' },
      weekend: { id: 'Rp 2.900.000', en: 'IDR 2,900,000' },
      extrabed: { id: 'Rp 350.000', en: 'IDR 350,000' },
    },
    priceFrom: { id: 'Rp 2.200.000', en: 'IDR 2,200,000' },
    capacity: { std: 4, max: 5 },
    meta: { id: '2 kamar · ruang keluarga · kolam renang', en: '2 bedrooms · living room · pool' },
    tagline: {
      id: 'Ruang untuk berkumpul, terhubung, dan menikmati bersama.',
      en: 'Space to gather, connect, and enjoy life together.',
    },
    description: {
      id: 'Joglo Saren dirancang untuk keluarga atau teman yang ingin berbagi keindahan Masayu bersama. Dua kamar tidur, ruang keluarga yang lapang dengan sofa rendah khas Jawa, dapur kecil, dan kolam renang yang luas menghadap taman menciptakan suasana yang hangat dan inklusif.',
      en: 'Joglo Saren is designed for families or friends who want to share the beauty of Masayu together. Two bedrooms, a spacious living room with low Javanese sofas, a small kitchen, and a generous pool facing the garden create a warm and inclusive atmosphere.',
    },
    facilities: {
      id: ['2 kamar tidur (1 king, 1 twin)', 'Ruang keluarga', 'Kolam renang bersama', 'Dapur kecil', 'Teras luas', 'AC & kipas angin', 'Wi-Fi', 'Sarapan untuk 4 orang', 'Pembuat kopi & teh', 'Handuk & amenities'],
      en: ['2 bedrooms (1 king, 1 twin)', 'Living room', 'Shared pool', 'Small kitchen', 'Spacious terrace', 'AC & fans', 'Wi-Fi', 'Breakfast for 4', 'Coffee & tea maker', 'Towels & amenities'],
    },
  },

  {
    id: 'joglo-agung',
    slug: 'joglo-agung',
    num: '03',
    name: 'Joglo Agung',
    code: 'Villa 03',
    photos: [
      'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=1200&q=80',
      'https://images.unsplash.com/photo-1551882547-ff40c4fe1fa7?w=1200&q=80',
      'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200&q=80',
      'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200&q=80',
      // Replace with: images/villa-agung-1.jpg, etc.
    ],
    price: {
      weekday: { id: 'Rp 3.800.000', en: 'IDR 3,800,000' },
      weekend: { id: 'Rp 4.800.000', en: 'IDR 4,800,000' },
      extrabed: { id: 'Rp 400.000', en: 'IDR 400,000' },
    },
    priceFrom: { id: 'Rp 3.800.000', en: 'IDR 3,800,000' },
    capacity: { std: 6, max: 8 },
    meta: { id: '3 kamar · pendopo · kolam renang · dapur penuh', en: '3 bedrooms · pendopo · pool · full kitchen' },
    tagline: {
      id: 'Kemegahan joglo Jawa untuk momen paling istimewa dalam hidupmu.',
      en: 'The grandeur of Javanese joglo for life\'s most special moments.',
    },
    description: {
      id: 'Joglo Agung adalah mahkota Masayu. Dengan pendopo besar yang dapat menampung pertemuan keluarga atau resepsi kecil, tiga kamar tidur mewah, dapur penuh, dan kolam renang panoramik yang menghadap sawah dan perbukitan, villa ini menawarkan pengalaman yang tak tertandingi. Setiap detail, dari ornamen ukiran hingga furniture antik, dipilih untuk menceritakan keagungan budaya Jawa.',
      en: 'Joglo Agung is the crown of Masayu. With a grand pendopo that can host family gatherings or intimate receptions, three luxurious bedrooms, a full kitchen, and a panoramic pool overlooking rice fields and rolling hills, this villa offers an unparalleled experience. Every detail — from carved ornaments to antique furniture — tells the grandeur of Javanese culture.',
    },
    facilities: {
      id: ['3 kamar tidur (2 king, 1 twin)', 'Pendopo besar', 'Kolam renang panoramik', 'Dapur penuh', 'Ruang makan outdoor', 'AC & kipas plafon', 'Wi-Fi', 'Sarapan untuk 6 orang', 'Layanan kamar', 'Concierge pribadi', 'Handuk & amenities premium'],
      en: ['3 bedrooms (2 king, 1 twin)', 'Grand pendopo', 'Panoramic pool', 'Full kitchen', 'Outdoor dining', 'AC & ceiling fans', 'Wi-Fi', 'Breakfast for 6', 'Room service', 'Personal concierge', 'Premium towels & amenities'],
    },
  },
];

/* ══════════════════════════════════════════════════════════
   4. SHARED UTILITIES
   ══════════════════════════════════════════════════════════ */

/* ─── Language State ─────────────────────────────────────── */
let currentLang = 'id';

function getStoredLang() {
  try {
    return localStorage.getItem('jm_lang') || null;
  } catch {
    return null;
  }
}

function setStoredLang(lang) {
  try {
    localStorage.setItem('jm_lang', lang);
  } catch { /* silent */ }
}

function t(key) {
  return (TRANSLATIONS[currentLang] && TRANSLATIONS[currentLang][key]) || TRANSLATIONS.id[key] || key;
}

function applyLanguage(lang) {
  currentLang = lang;
  setStoredLang(lang);
  document.documentElement.lang = lang;

  // Update meta title and description
  const isVillaPage = document.body.classList.contains('page-villa');
  document.title = isVillaPage ? t('meta.title.villa') : t('meta.title.home');
  const metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc) metaDesc.content = isVillaPage ? t('meta.desc.villa') : t('meta.desc.home');

  // Apply data-i18n (text content)
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    el.textContent = t(key);
  });

  // Apply data-i18n-html (innerHTML — used for headings with em tags and <br>)
  document.querySelectorAll('[data-i18n-html]').forEach(el => {
    const key = el.getAttribute('data-i18n-html');
    el.innerHTML = t(key);
  });

  // Update lang buttons
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.lang === lang);
  });

  // Re-render dynamic sections if they exist
  if (typeof renderVillaList === 'function' && document.getElementById('villaList')) {
    renderVillaList();
  }
  if (typeof renderGallery === 'function' && document.getElementById('galleryGrid')) {
    renderGallery();
  }
  if (typeof renderVillaDetail === 'function' && document.body.classList.contains('page-villa')) {
    renderVillaDetail();
  }

  // Update WhatsApp buttons
  updateWhatsAppLinks();

  // Update back-to-top aria-label
  const bttBtn = document.getElementById('btnBackToTop');
  if (bttBtn) {
    bttBtn.setAttribute('aria-label', t('nav.backToTop.aria'));
  }
}

function updateWhatsAppLinks() {
  const waUrl = `https://wa.me/${CONFIG.whatsappNumber}`;
  // Header Pesan button
  const headerPesan = document.getElementById('headerPesanBtn');
  if (headerPesan) headerPesan.href = waUrl;
  // Mobile Pesan
  const mobilePesan = document.getElementById('mobilePesanBtn');
  if (mobilePesan) mobilePesan.href = waUrl;
  // Kontak section
  const kontakWa = document.getElementById('kontakWhatsAppBtn');
  if (kontakWa) kontakWa.href = waUrl;
  // Villa list inquiry
  const villaWa = document.getElementById('villaWhatsAppBtn');
  if (villaWa) villaWa.href = waUrl;
  // Instagram
  const instaBtn = document.getElementById('kontakInstaBtn');
  if (instaBtn) instaBtn.href = CONFIG.instagramUrl;
  // Footer contacts
  const footerWa = document.getElementById('footerWaBtn');
  if (footerWa) footerWa.href = waUrl;
  const footerInsta = document.getElementById('footerInstaBtn');
  if (footerInsta) footerInsta.href = CONFIG.instagramUrl;
  // Google Maps
  const mapsBtn = document.getElementById('kontakMapsBtn');
  if (mapsBtn && CONFIG.mapsUrl) mapsBtn.href = CONFIG.mapsUrl;
  const mapIframe = document.getElementById('kontakMapIframe');
  if (mapIframe && CONFIG.mapsEmbedUrl) mapIframe.src = CONFIG.mapsEmbedUrl;
}

/* ─── Header Injection ───────────────────────────────────── */
function injectHeader() {
  const placeholder = document.getElementById('site-header');
  if (!placeholder) return;

  const isVillaPage = document.body.classList.contains('page-villa');
  const navBase = isVillaPage ? 'index.html' : '';

  const navLinks = [
    { key: 'nav.tentang', href: `${navBase}#tentang`, id: 'nav-tentang' },
    { key: 'nav.villa', href: `${navBase}#villa`, id: 'nav-villa' },
    { key: 'nav.galeri', href: `${navBase}#galeri`, id: 'nav-galeri' },
    { key: 'nav.kontak', href: `${navBase}#kontak`, id: 'nav-kontak' },
  ];

  const navHTML = navLinks.map(l =>
    `<li><a href="${l.href}" id="${l.id}" data-i18n="${l.key}">${t(l.key)}</a></li>`
  ).join('');

  placeholder.innerHTML = `
    <header class="site-header ${isVillaPage ? 'is-scrolled' : 'is-transparent'}" id="siteHeader" role="banner">
      <div class="header-inner">
        <a href="index.html" class="header-logo" aria-label="Joglo Masayu">
          <img src="images/logo.png" alt="" class="header-logo-icon" width="auto" height="50" />
          <span class="header-logo-text">Joglo Masayu</span>
        </a>

        <nav aria-label="Navigasi utama">
          <ul class="header-nav" id="headerNav">
            ${navHTML}
          </ul>
        </nav>

        <div class="header-right">
          <div class="lang-switch" role="group" aria-label="Pilih bahasa / Select language">
            <button class="lang-btn ${currentLang === 'id' ? 'is-active' : ''}" data-lang="id" type="button">ID</button>
            <span class="lang-switch-divider" aria-hidden="true"></span>
            <button class="lang-btn ${currentLang === 'en' ? 'is-active' : ''}" data-lang="en" type="button">EN</button>
          </div>
          <a href="https://wa.me/${CONFIG.whatsappNumber}" id="headerPesanBtn" class="btn btn-ghost" target="_blank" rel="noopener" data-i18n="nav.pesan">${t('nav.pesan')}</a>
          <button class="hamburger" id="hamburgerBtn" aria-expanded="false" aria-label="Buka menu navigasi" type="button">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
          </button>
        </div>
      </div>
    </header>

    <!-- Mobile nav overlay -->
    <nav class="mobile-nav" id="mobileNav" aria-label="Navigasi mobile" aria-hidden="true">
      <ul class="mobile-nav-links">
        ${navLinks.map(l =>
    `<li><a href="${l.href}" data-i18n="${l.key}" class="mobile-nav-link">${t(l.key)}</a></li>`
  ).join('')}
      </ul>
      <a href="https://wa.me/${CONFIG.whatsappNumber}" id="mobilePesanBtn" class="btn btn-ghost" target="_blank" rel="noopener" data-i18n="nav.pesan">${t('nav.pesan')}</a>
    </nav>
  `;
}

/* ─── Footer Injection ───────────────────────────────────── */
function injectFooter() {
  const placeholder = document.getElementById('site-footer');
  if (!placeholder) return;

  placeholder.innerHTML = `
    <footer class="site-footer" role="contentinfo">
      <div class="container">
        <div class="footer-inner">
          <div class="footer-brand">
            <a href="index.html" class="footer-logo" aria-label="Joglo Masayu — beranda">
              <img src="images/logo.png" alt="" class="footer-logo-icon" width="auto" height="72" />
              <span class="footer-brand-text">Joglo Masayu</span>
            </a>
            <p class="footer-desc" data-i18n="footer.desc">${t('footer.desc')}</p>
          </div>

          <div>
            <p class="footer-col-title" data-i18n="footer.menu">${t('footer.menu')}</p>
            <ul class="footer-links">
              <li><a href="index.html#tentang" data-i18n="nav.tentang">${t('nav.tentang')}</a></li>
              <li><a href="index.html#villa"   data-i18n="nav.villa">${t('nav.villa')}</a></li>
              <li><a href="index.html#galeri"  data-i18n="nav.galeri">${t('nav.galeri')}</a></li>
              <li><a href="index.html#kontak"  data-i18n="nav.kontak">${t('nav.kontak')}</a></li>
            </ul>
          </div>

          <div>
            <p class="footer-col-title" data-i18n="footer.contact">${t('footer.contact')}</p>
            <a href="https://wa.me/${CONFIG.whatsappNumber}" id="footerWaBtn" class="footer-contact-item" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
              </svg>
              WhatsApp
            </a>
            <a href="${CONFIG.instagramUrl}" id="footerInstaBtn" class="footer-contact-item" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
              </svg>
              Instagram
            </a>
          </div>
        </div>

        <div class="footer-bottom">
          <span class="footer-copyright" data-i18n="footer.copyright">${t('footer.copyright')}</span>
        </div>
      </div>
    </footer>
  `;
}

/* ─── Language Switch ─────────────────────────────────────── */
function initLangSwitch() {
  document.addEventListener('click', e => {
    const btn = e.target.closest('.lang-btn');
    if (!btn) return;
    const lang = btn.dataset.lang;
    if (lang && lang !== currentLang) {
      applyLanguage(lang);
    }
  });
}

/* ─── Sticky Header (homepage only) ──────────────────────── */
function initStickyHeader() {
  const header = document.getElementById('siteHeader');
  if (!header) return;
  if (document.body.classList.contains('page-villa')) return;

  const threshold = 60;

  function update() {
    if (window.scrollY > threshold) {
      header.classList.add('is-scrolled');
      header.classList.remove('is-transparent');
    } else {
      header.classList.remove('is-scrolled');
      header.classList.add('is-transparent');
    }
  }

  update();
  window.addEventListener('scroll', update, { passive: true });
}

/* ─── Scroll Spy ──────────────────────────────────────────── */
function initScrollSpy() {
  const sections = document.querySelectorAll('section[id]');
  if (!sections.length) return;

  const navLinks = document.querySelectorAll('#headerNav a[href^="#"], #headerNav a[href*="#"]');
  if (!navLinks.length) return;

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id;
        navLinks.forEach(a => {
          const href = a.getAttribute('href');
          a.classList.toggle('is-active', href === `#${id}` || href.endsWith(`#${id}`));
        });
      }
    });
  }, { rootMargin: '-30% 0px -60% 0px' });

  sections.forEach(s => observer.observe(s));
}

/* ─── Mobile Menu ─────────────────────────────────────────── */
function initMobileMenu() {
  const hamburger = document.getElementById('hamburgerBtn');
  const mobileNav = document.getElementById('mobileNav');
  if (!hamburger || !mobileNav) return;

  function openMenu() {
    hamburger.setAttribute('aria-expanded', 'true');
    hamburger.setAttribute('aria-label', 'Tutup menu navigasi');
    mobileNav.classList.add('is-open');
    mobileNav.removeAttribute('aria-hidden');
    document.body.style.overflow = 'hidden';
    // Focus first link
    const firstLink = mobileNav.querySelector('a');
    if (firstLink) firstLink.focus();
  }

  function closeMenu() {
    hamburger.setAttribute('aria-expanded', 'false');
    hamburger.setAttribute('aria-label', 'Buka menu navigasi');
    mobileNav.classList.remove('is-open');
    mobileNav.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    hamburger.focus();
  }

  hamburger.addEventListener('click', () => {
    const isOpen = hamburger.getAttribute('aria-expanded') === 'true';
    isOpen ? closeMenu() : openMenu();
  });

  // Close on link click
  mobileNav.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', closeMenu);
  });

  // Close on Esc
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) {
      closeMenu();
    }
  });
}

/* ─── Scroll Reveal ───────────────────────────────────────── */
function initScrollReveal() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
}

// Re-observe newly added reveal elements (for dynamically injected content)
function observeNewReveal(container) {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    container.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  container.querySelectorAll('.reveal').forEach(el => observer.observe(el));
}

/* ─── Lightbox ────────────────────────────────────────────── */
let lightboxImages = [];
let lightboxIndex = 0;

function openLightbox(images, startIndex) {
  lightboxImages = images;
  lightboxIndex = startIndex;
  const lb = document.getElementById('lightbox');
  const img = document.getElementById('lightboxImg');
  if (!lb || !img) return;

  img.src = lightboxImages[lightboxIndex].src;
  img.alt = lightboxImages[lightboxIndex].alt || '';
  lb.hidden = false;
  document.body.style.overflow = 'hidden';

  // Focus close button
  const closeBtn = document.getElementById('lightboxClose');
  if (closeBtn) closeBtn.focus();
}

function closeLightbox() {
  const lb = document.getElementById('lightbox');
  if (!lb) return;
  lb.hidden = true;
  document.body.style.overflow = '';
  lightboxImages = [];
  lightboxIndex = 0;
}

function lightboxNav(dir) {
  if (!lightboxImages.length) return;
  lightboxIndex = (lightboxIndex + dir + lightboxImages.length) % lightboxImages.length;
  const img = document.getElementById('lightboxImg');
  if (img) {
    img.src = lightboxImages[lightboxIndex].src;
    img.alt = lightboxImages[lightboxIndex].alt || '';
  }
}

function initLightbox() {
  const lb = document.getElementById('lightbox');
  const overlay = lb && lb.querySelector('.lightbox-overlay');
  const closeBtn = document.getElementById('lightboxClose');
  const prevBtn = document.getElementById('lightboxPrev');
  const nextBtn = document.getElementById('lightboxNext');

  if (!lb) return;

  overlay && overlay.addEventListener('click', closeLightbox);
  closeBtn && closeBtn.addEventListener('click', closeLightbox);
  prevBtn && prevBtn.addEventListener('click', () => lightboxNav(-1));
  nextBtn && nextBtn.addEventListener('click', () => lightboxNav(1));

  document.addEventListener('keydown', e => {
    if (lb.hidden) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxNav(-1);
    if (e.key === 'ArrowRight') lightboxNav(1);
  });

  // Focus trap
  lb.addEventListener('keydown', e => {
    if (e.key !== 'Tab' || lb.hidden) return;
    const focusable = lb.querySelectorAll('button, [tabindex="0"]');
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey) {
      if (document.activeElement === first) { e.preventDefault(); last.focus(); }
    } else {
      if (document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
}

/* ─── Back to Top (Homepage only) ────────────────────────── */
function initBackToTop() {
  const btn = document.getElementById('btnBackToTop');
  if (!btn) return;
  if (document.body.classList.contains('page-villa')) {
    btn.remove();
    return;
  }

  function updateVisibility() {
    if (window.scrollY > 300) {
      btn.classList.add('is-visible');
    } else {
      btn.classList.remove('is-visible');
    }
  }

  window.addEventListener('scroll', updateVisibility, { passive: true });
  updateVisibility();

  btn.addEventListener('click', e => {
    e.preventDefault();
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({
      top: 0,
      behavior: prefersReduced ? 'auto' : 'smooth'
    });

    if (window.history && window.history.pushState) {
      window.history.pushState(null, '', '#top');
    } else {
      window.location.hash = '#top';
    }

    const topTarget = document.getElementById('top') || document.getElementById('siteHeader') || document.body;
    if (topTarget) {
      topTarget.setAttribute('tabindex', '-1');
      topTarget.focus({ preventScroll: true });
    }
  });
}

/* ══════════════════════════════════════════════════════════
   5. HOMEPAGE
   ══════════════════════════════════════════════════════════ */

function initHomepage() {
  const heroSlider = document.getElementById('heroSlider');
  if (!heroSlider) return; // not homepage

  initHeroSlider();
  renderVillaList();
  initVillaHover();
  renderGallery();
  initGalleryLightbox();
  initBackToTop();
}

/* ─── Hero Slider ─────────────────────────────────────────── */
let sliderIndex = 0;
let sliderTimer = null;
let sliderPaused = false;
const SLIDER_DELAY = 3500;

function buildSlides() {
  const slider = document.getElementById('heroSlider');
  if (!slider) return;
  slider.innerHTML = '';

  CONFIG.heroImages.forEach((src, i) => {
    const slide = document.createElement('div');
    slide.className = 'slider-slide' + (i === 0 ? ' is-active' : '');
    slide.setAttribute('role', 'group');
    slide.setAttribute('aria-label', `Slide ${i + 1} dari ${CONFIG.heroImages.length}`);
    const img = document.createElement('img');
    img.src = src;
    img.alt = `Foto villa joglo Masayu ${i + 1}`;
    img.width = 900;
    img.height = 1125;
    if (i > 0) img.loading = 'lazy';
    slide.appendChild(img);
    slider.appendChild(slide);
  });

  updateCounter();
}

function getSlides() {
  return document.querySelectorAll('#heroSlider .slider-slide');
}

function updateCounter() {
  const counter = document.getElementById('sliderCounter');
  if (!counter) return;
  const total = CONFIG.heroImages.length;
  counter.textContent = `${String(sliderIndex + 1).padStart(2, '0')} / ${String(total).padStart(2, '0')}`;
}

function goToSlide(nextIndex) {
  const slides = getSlides();
  if (!slides.length) return;
  const total = slides.length;
  const current = sliderIndex;
  nextIndex = ((nextIndex % total) + total) % total;

  if (nextIndex === current) return;

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (reduced) {
    slides[current].classList.remove('is-active');
    slides[nextIndex].classList.add('is-active');
  } else {
    // Prepare incoming slide: position at right, no transition
    slides[nextIndex].classList.add('is-entering');
    slides[nextIndex].classList.remove('is-active', 'is-leaving');

    // Force reflow
    void slides[nextIndex].offsetWidth;

    // Start transition
    slides[current].classList.add('is-leaving');
    slides[current].classList.remove('is-active');
    slides[nextIndex].classList.remove('is-entering');
    slides[nextIndex].classList.add('is-active');

    // Cleanup leaving slide after transition
    slides[current].addEventListener('transitionend', function handler() {
      slides[current].classList.remove('is-leaving');
      slides[current].removeEventListener('transitionend', handler);
    });
  }

  sliderIndex = nextIndex;
  updateCounter();
}

function sliderNext() { goToSlide(sliderIndex + 1); }
function sliderPrev() { goToSlide(sliderIndex - 1); }

function startSliderTimer() {
  clearInterval(sliderTimer);
  sliderTimer = setInterval(() => {
    if (!sliderPaused) sliderNext();
  }, SLIDER_DELAY);
}

function initHeroSlider() {
  buildSlides();

  const wrap = document.querySelector('.hero-slider-wrap');
  const prevBtn = document.getElementById('sliderPrev');
  const nextBtn = document.getElementById('sliderNext');

  if (!wrap) return;

  prevBtn && prevBtn.addEventListener('click', () => { sliderPrev(); if (!sliderPaused) { clearInterval(sliderTimer); startSliderTimer(); } });
  nextBtn && nextBtn.addEventListener('click', () => { sliderNext(); if (!sliderPaused) { clearInterval(sliderTimer); startSliderTimer(); } });

  // Hover pause
  wrap.addEventListener('mouseenter', () => { sliderPaused = true; });
  wrap.addEventListener('mouseleave', () => { sliderPaused = false; });

  // Touch swipe
  let touchStartX = null;
  wrap.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
  wrap.addEventListener('touchend', e => {
    if (touchStartX === null) return;
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) {
      diff > 0 ? sliderNext() : sliderPrev();
    }
    touchStartX = null;
  }, { passive: true });

  // Touch device: always show arrows
  if ('ontouchstart' in window) {
    document.body.classList.add('touch-device');
  }

  startSliderTimer();
}

/* ─── Villa List ──────────────────────────────────────────── */
function renderVillaList() {
  const list = document.getElementById('villaList');
  if (!list) return;

  list.innerHTML = '';

  VILLAS.forEach((villa, i) => {
    const row = document.createElement('a');
    row.href = `villa.html?id=${villa.slug}`;
    row.className = 'villa-row reveal' + (i > 0 ? ` reveal-delay-${Math.min(i, 3)}` : '');
    row.setAttribute('role', 'listitem');
    row.setAttribute('aria-label', `${villa.name} — ${villa.meta[currentLang]}`);
    row.dataset.villaIndex = i;

    row.innerHTML = `
      <span class="villa-row-num">${villa.num}</span>
      <div class="villa-row-body">
        <span class="villa-row-name">${villa.name}</span>
        <img class="villa-row-thumb" src="${villa.photos[0]}" alt="${villa.name}" width="56" height="56" loading="lazy"/>
        <span class="villa-row-meta">${villa.meta[currentLang]}</span>
      </div>
      <div class="villa-row-right">
        <div class="villa-row-price">
          <small>${t('villa.from')}</small>
          ${villa.priceFrom[currentLang]}
        </div>
        <svg class="villa-row-arrow" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    `;

    list.appendChild(row);
  });

  // Re-observe new reveal elements
  observeNewReveal(list);
}

/* ─── Villa Sticky Photo ──────────────────────────────────── */
function initVillaHover() {
  // Inject sticky photos
  const stickyInner = document.getElementById('stickyPhotoInner');
  if (!stickyInner) return;

  stickyInner.innerHTML = '';
  VILLAS.forEach((villa, i) => {
    const img = document.createElement('img');
    img.src = villa.photos[0];
    img.alt = villa.name;
    img.className = 'sticky-photo-img' + (i === 0 ? ' is-active' : '');
    img.width = 800;
    img.height = 1000;
    img.loading = 'lazy';
    stickyInner.appendChild(img);
  });

  // Hover / focus handlers on villa rows
  document.getElementById('villaList').addEventListener('focusin', handleVillaInteraction);
  document.getElementById('villaList').addEventListener('mouseover', handleVillaInteraction);
}

function handleVillaInteraction(e) {
  const row = e.target.closest('.villa-row');
  if (!row) return;
  const idx = parseInt(row.dataset.villaIndex, 10);
  if (isNaN(idx)) return;

  const imgs = document.querySelectorAll('.sticky-photo-img');
  imgs.forEach((img, i) => img.classList.toggle('is-active', i === idx));
}

/* ─── Gallery ─────────────────────────────────────────────── */
function renderGallery() {
  const grid = document.getElementById('galleryGrid');
  if (!grid) return;

  grid.innerHTML = '';

  CONFIG.galleryImages.forEach((item, i) => {
    const div = document.createElement('div');
    let spanClass = '';
    if (item.span === 'tall') spanClass = 'gallery-item--tall';
    else if (item.span === 'wide') spanClass = 'gallery-item--wide';

    div.className = `gallery-item ${spanClass} reveal`.trim();
    if (i > 0) div.classList.add(`reveal-delay-${Math.min(i % 4, 3)}`);
    div.setAttribute('role', 'listitem');
    div.setAttribute('tabindex', '0');
    div.setAttribute('aria-label', item.alt);
    div.dataset.galleryIndex = i;

    const img = document.createElement('img');
    img.src = item.src;
    img.alt = item.alt;
    img.width = 800;
    img.height = 800;
    if (i > 3) img.loading = 'lazy';

    div.appendChild(img);
    grid.appendChild(div);
  });

  observeNewReveal(grid);
}

function initGalleryLightbox() {
  const grid = document.getElementById('galleryGrid');
  if (!grid) return;

  const images = CONFIG.galleryImages.map(g => ({ src: g.src, alt: g.alt }));

  grid.addEventListener('click', e => {
    const item = e.target.closest('.gallery-item');
    if (!item) return;
    const idx = parseInt(item.dataset.galleryIndex, 10);
    openLightbox(images, isNaN(idx) ? 0 : idx);
  });

  grid.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      const item = e.target.closest('.gallery-item');
      if (!item) return;
      e.preventDefault();
      const idx = parseInt(item.dataset.galleryIndex, 10);
      openLightbox(images, isNaN(idx) ? 0 : idx);
    }
  });
}

/* ══════════════════════════════════════════════════════════
   6. VILLA DETAIL PAGE
   ══════════════════════════════════════════════════════════ */

let currentVillaPhotos = [];
let currentMainPhotoIndex = 0;

function initVillaDetail() {
  const detailContent = document.getElementById('villaDetailContent');
  if (!detailContent) return; // not villa page

  initBackButton();
  renderVillaDetail();
}

function initBackButton() {
  const backBtn = document.getElementById('backBtn');
  if (!backBtn) return;

  backBtn.addEventListener('click', () => {
    // If referrer is same-origin homepage, go back; otherwise navigate
    const ref = document.referrer;
    const sameOrigin = ref && new URL(ref).origin === window.location.origin && ref.includes('index.html');
    if (sameOrigin) {
      history.back();
    } else {
      window.location.href = 'index.html#villa';
    }
  });
}

function getVillaIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id') || '';
}

function renderVillaDetail() {
  const container = document.getElementById('villaDetailContent');
  if (!container) return;

  const id = getVillaIdFromURL();
  const villa = VILLAS.find(v => v.id === id || v.slug === id);

  if (!villa) {
    renderVillaNotFound(container);
    return;
  }

  // Update page title
  document.title = `${villa.name} — Joglo Masayu`;
  const metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc) metaDesc.content = villa.description[currentLang].slice(0, 160);

  currentVillaPhotos = villa.photos.map(src => ({ src, alt: villa.name }));
  currentMainPhotoIndex = 0;

  const waMsg = encodeURIComponent(t('villa.wa.book').replace('{villa}', villa.name));
  const waAsk = encodeURIComponent(t('villa.wa.ask').replace('{villa}', villa.name));
  const waUrl = `https://wa.me/${CONFIG.whatsappNumber}?text=`;

  // Build facilities chips
  const chips = villa.facilities[currentLang].map(f =>
    `<span class="chip">${f}</span>`
  ).join('');

  // Build thumbnails
  const thumbsHTML = villa.photos.slice(0, 4).map((src, i) =>
    `<button class="villa-thumb ${i === 0 ? 'is-active' : ''}" data-thumb-index="${i}" type="button" aria-label="Foto ${i + 1}">
      <img src="${src}" alt="${villa.name} foto ${i + 1}" width="200" height="200" loading="${i > 0 ? 'lazy' : 'eager'}"/>
    </button>`
  ).join('');

  // Other villas
  const otherVillas = VILLAS.filter(v => v.id !== villa.id);
  const otherCardsHTML = otherVillas.map(v =>
    `<a href="villa.html?id=${v.slug}" class="villa-card">
      <div class="villa-card-photo">
        <img src="${v.photos[0]}" alt="${v.name}" width="600" height="400" loading="lazy"/>
      </div>
      <div class="villa-card-body">
        <h3 class="villa-card-name">${v.name}</h3>
        <p class="villa-card-price">${t('villa.from.price')} ${v.priceFrom[currentLang]}</p>
      </div>
    </a>`
  ).join('');

  container.innerHTML = `
    <!-- Loading bar removed -->

    <section class="villa-detail-section">
      <div class="villa-detail-grid">

        <!-- LEFT: Photos -->
        <div class="villa-detail-photos">
          <div class="villa-main-photo-wrap" id="villaMainPhotoWrap" tabindex="0" role="button" aria-label="Buka lightbox foto">
            <img
              id="villaMainPhoto"
              class="villa-main-photo"
              src="${villa.photos[0]}"
              alt="${villa.name}"
              width="1200" height="900"
              loading="eager"
            />
          </div>
          <div class="villa-thumbs" id="villaThumbs">
            ${thumbsHTML}
          </div>
        </div>

        <!-- RIGHT: Info -->
        <div class="villa-detail-info">
          <p class="eyebrow">${villa.code}</p>
          <h1 class="villa-detail-name">${villa.name}</h1>
          <em class="villa-detail-tagline">${villa.tagline[currentLang]}</em>
          <p class="villa-detail-desc">${villa.description[currentLang]}</p>

          <span class="villa-facilities-label" data-i18n="villa.facilities">${t('villa.facilities')}</span>
          <div class="villa-chips">
            ${chips}
          </div>

          <!-- Price box -->
          <div class="price-box">
            <div class="price-box-row">
              <span class="price-box-label" data-i18n="villa.weekday">${t('villa.weekday')}</span>
              <span class="price-box-value">${villa.price.weekday[currentLang]}</span>
            </div>
            <div class="price-box-row">
              <span class="price-box-label" data-i18n="villa.weekend">${t('villa.weekend')}</span>
              <span class="price-box-value">${villa.price.weekend[currentLang]}</span>
            </div>
            <div class="price-box-row">
              <span class="price-box-label" data-i18n="villa.extrabed">${t('villa.extrabed')}</span>
              <span class="price-box-value">${villa.price.extrabed[currentLang]}</span>
            </div>
            <div class="price-box-row">
              <span class="price-box-label" data-i18n="villa.capacity">${t('villa.capacity')}</span>
              <span class="price-box-value is-capacity">${villa.capacity.std} ${t('villa.capacity.std')} / ${villa.capacity.max} ${t('villa.capacity.max')}</span>
            </div>
            <p class="price-box-note" data-i18n="villa.price.note">${t('villa.price.note')}</p>
          </div>

          <div class="villa-cta-row">
            <a href="${waUrl}${waMsg}" class="btn btn-ghost" target="_blank" rel="noopener" data-i18n="villa.book">${t('villa.book')}</a>
            <a href="${waUrl}${waAsk}" class="btn btn-ghost" target="_blank" rel="noopener" data-i18n="villa.ask">${t('villa.ask')}</a>
          </div>
        </div>

      </div>
    </section>

    <!-- Other villas -->
    <section class="other-villas-section">
      <div class="container">
        <p class="eyebrow" data-i18n="villa.other">${t('villa.other')}</p>
        <div class="other-villas-grid">
          ${otherCardsHTML}
        </div>
      </div>
    </section>
  `;

  // Remove old loading placeholder
  const loading = document.getElementById('villaDetailLoading');
  if (loading) loading.remove();

  initVillaPhotoInteractions(villa);
  observeNewReveal(container);
}

function renderVillaNotFound(container) {
  container.innerHTML = `
    <div class="villa-not-found">
      <h2 data-i18n="villa.notfound.h">${t('villa.notfound.h')}</h2>
      <p data-i18n="villa.notfound.p">${t('villa.notfound.p')}</p>
      <a href="index.html#villa" class="btn btn-ghost" data-i18n="villa.notfound.btn">${t('villa.notfound.btn')}</a>
    </div>
  `;
}

function initVillaPhotoInteractions(villa) {
  const mainWrap = document.getElementById('villaMainPhotoWrap');
  const mainPhoto = document.getElementById('villaMainPhoto');
  const thumbsContainer = document.getElementById('villaThumbs');

  // Thumbnails
  if (thumbsContainer) {
    thumbsContainer.addEventListener('click', e => {
      const btn = e.target.closest('.villa-thumb');
      if (!btn) return;
      const idx = parseInt(btn.dataset.thumbIndex, 10);
      setMainPhoto(idx, villa);
      thumbsContainer.querySelectorAll('.villa-thumb').forEach((t, i) => t.classList.toggle('is-active', i === idx));
    });
  }

  // Open lightbox on main photo click or Enter/Space
  if (mainWrap) {
    mainWrap.addEventListener('click', () => {
      openLightbox(currentVillaPhotos, currentMainPhotoIndex);
    });
    mainWrap.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openLightbox(currentVillaPhotos, currentMainPhotoIndex);
      }
    });

    // Touch swipe gesture for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    mainWrap.addEventListener('touchstart', e => {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    mainWrap.addEventListener('touchend', e => {
      touchEndX = e.changedTouches[0].screenX;
      const diff = touchStartX - touchEndX;
      const total = villa.photos.length;
      if (Math.abs(diff) > 40) {
        if (diff > 0) {
          // Swipe left -> next photo
          const nextIdx = (currentMainPhotoIndex + 1) % total;
          setMainPhoto(nextIdx, villa);
          thumbsContainer && thumbsContainer.querySelectorAll('.villa-thumb').forEach((t, i) => t.classList.toggle('is-active', i === nextIdx));
        } else {
          // Swipe right -> prev photo
          const prevIdx = (currentMainPhotoIndex - 1 + total) % total;
          setMainPhoto(prevIdx, villa);
          thumbsContainer && thumbsContainer.querySelectorAll('.villa-thumb').forEach((t, i) => t.classList.toggle('is-active', i === prevIdx));
        }
      }
    }, { passive: true });
  }
}

function setMainPhoto(index, villa) {
  const mainPhoto = document.getElementById('villaMainPhoto');
  if (!mainPhoto) return;
  currentMainPhotoIndex = index;
  // Cross-fade using CSS class (no inline styles)
  mainPhoto.classList.add('is-fading');
  setTimeout(() => {
    mainPhoto.src = villa.photos[index];
    mainPhoto.alt = `${villa.name} foto ${index + 1}`;
    mainPhoto.classList.remove('is-fading');
  }, 220);
}

/* ══════════════════════════════════════════════════════════
   INIT — runs on both pages
   ══════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

  // Detect language
  const stored = getStoredLang();
  if (stored) {
    currentLang = stored;
  } else if (navigator.language && navigator.language.toLowerCase().startsWith('en')) {
    currentLang = 'en';
  } else {
    currentLang = 'id';
  }

  // Inject structure
  injectHeader();
  injectFooter();

  // Apply language (fills all data-i18n elements)
  applyLanguage(currentLang);

  // Init shared behaviors
  initLangSwitch();
  initStickyHeader();
  initScrollSpy();
  initMobileMenu();
  initScrollReveal();
  initLightbox();

  // Init page-specific
  initHomepage();
  initVillaDetail();

  // Hide any logo img that fails to load (prevent broken-image icon / alt text bleed)
  document.querySelectorAll('.header-logo-icon, .footer-logo-icon').forEach(img => {
    if (img.complete && img.naturalWidth === 0) {
      img.style.display = 'none';
    } else {
      img.addEventListener('error', function () {
        this.style.display = 'none';
      }, { once: true });
    }
  });
});
