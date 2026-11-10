/**
 * Toast Notification System — Villa Joglo Masayu
 * Vanilla JS terpadu untuk notifikasi elegan tanpa merusak tata letak halaman.
 * Varian: sukses, info, peringatan, galat
 */

(function () {
  'use strict';

  const ICONS = {
    sukses: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#2F4F3E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.666 5l-10 10-4.166-4.167"/></svg>`,
    info: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#2980B9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M10 14V10M10 6h.01"/></svg>`,
    peringatan: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#E3A600" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2L1 18h18L10 2zM10 9v4M10 15h.01"/></svg>`,
    galat: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#C0392B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M13 7l-6 6M7 7l6 6"/></svg>`
  };

  function getOrCreateRegion() {
    let region = document.getElementById('toast-region');
    if (!region) {
      region = document.createElement('div');
      region.id = 'toast-region';
      region.setAttribute('aria-live', 'polite');
      region.setAttribute('aria-atomic', 'false');
      document.body.appendChild(region);
    }
    return region;
  }

  function showToast(message, options = {}) {
    if (!message) return;

    let type = (options.tipe || options.type || 'info').toLowerCase();
    if (type === 'success') type = 'sukses';
    if (type === 'danger' || type === 'error') type = 'galat';
    if (type === 'warning') type = 'peringatan';

    if (!ICONS[type]) type = 'info';

    const duration = options.durasi || options.duration || (type === 'galat' ? 6000 : 4000);

    const region = getOrCreateRegion();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', type === 'galat' ? 'alert' : 'status');

    const iconWrap = document.createElement('span');
    iconWrap.className = 'toast-icon';
    iconWrap.innerHTML = ICONS[type];

    const textWrap = document.createElement('div');
    textWrap.className = 'toast-message';
    textWrap.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Tutup notifikasi');
    closeBtn.innerHTML = `&times;`;

    toast.appendChild(iconWrap);
    toast.appendChild(textWrap);
    toast.appendChild(closeBtn);

    region.appendChild(toast);

    let dismissTimer = null;
    let remainingTime = duration;
    let startTime = Date.now();

    function startTimer() {
      startTime = Date.now();
      dismissTimer = setTimeout(() => {
        dismissToast(toast);
      }, remainingTime);
    }

    function pauseTimer() {
      clearTimeout(dismissTimer);
      remainingTime -= Date.now() - startTime;
    }

    toast.addEventListener('mouseenter', pauseTimer);
    toast.addEventListener('mouseleave', () => {
      if (remainingTime > 0) startTimer();
    });

    closeBtn.addEventListener('click', () => {
      clearTimeout(dismissTimer);
      dismissToast(toast);
    });

    startTimer();
    return toast;
  }

  function dismissToast(toast) {
    if (!toast || toast.classList.contains('is-dismissing')) return;
    toast.classList.add('is-dismissing');
    toast.style.animation = 'toastSlideOut 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
    setTimeout(() => {
      if (toast.parentElement) {
        toast.parentElement.removeChild(toast);
      }
    }, 300);
  }

  // Ekspor global API
  window.showToast = showToast;

  // Otomatis render flash messages dari backend saat DOM siap
  document.addEventListener('DOMContentLoaded', () => {
    if (Array.isArray(window.MASAYU_FLASH) && window.MASAYU_FLASH.length > 0) {
      window.MASAYU_FLASH.forEach((item, index) => {
        setTimeout(() => {
          showToast(item.message || item.pesan, { tipe: item.type || item.tipe });
        }, index * 200);
      });
    }

    // Bersihkan query string penanda notifikasi lama dari URL (misal ?keluar=1)
    if (window.history && window.history.replaceState) {
      const url = new URL(window.location.href);
      if (url.searchParams.has('keluar') || url.searchParams.has('pesan') || url.searchParams.has('status_msg')) {
        url.searchParams.delete('keluar');
        url.searchParams.delete('pesan');
        url.searchParams.delete('status_msg');
        window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ''));
      }
    }
  });
})();

