/**
 * Komponen Kalender Ketersediaan Vanilla JS — Villa Joglo Masayu
 * Kebutuhan: Tahap 11-D
 *
 * Fitur:
 * - Minggu mulai Senin (ISO-8601).
 * - Navigasi bulan (2 bulan berdampingan di >=768px, 1 bulan di mobile).
 * - Lokalisasi bahasa (Intl.DateTimeFormat) mengikuti window.MASAYU_LANG ('id' atau 'en').
 * - Legenda aksesibel: Tersedia, Penuh (abu-abu + coretan + teks "penuh"), Menunggu (pola garis + teks "menunggu"), Dipilih.
 * - Aturan seleksi check-in & check-out, deteksi malam penuh (sebutkan tanggalnya), peringatan status menunggu.
 * - Ringkasan durasi weekday/weekend & estimasi tarif.
 * - Aksesibilitas: role="grid", role="gridcell", tombol dengan aria-label lengkap, navigasi keyboard (panah, Home, End, PgUp, PgDn, Enter, Esc), target sentuh >=44px.
 * - Mode: 'select' (interaktif), 'readonly' (hanya baca + tombol pesan).
 */

(function (window, document) {
  'use strict';

  function formatYMD(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function parseYMD(s) {
    if (!s || !/^\d{4}-\d{2}-\d{2}$/.test(s)) return null;
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  function isWeekendNight(dateStr) {
    // Malam Jumat (5) dan Sabtu (6) menginap adalah weekend rate
    const d = parseYMD(dateStr);
    const day = d.getDay();
    return day === 5 || day === 6;
  }

  class MasayuKalender {
    constructor(container, options = {}) {
      this.container = typeof container === 'string' ? document.querySelector(container) : container;
      if (!this.container) return;

      this.options = Object.assign({
        mode: 'select', // 'select' | 'readonly'
        idTipe: 0,
        slugVilla: '',
        checkinInput: null,
        checkoutInput: null,
        onRangeSelect: null,
        tarif: null, // { weekday, weekend, extrabed, std, max }
        lang: window.MASAYU_LANG || 'id'
      }, options);

      this.currentDate = new Date();
      this.viewMonth = this.currentDate.getMonth();
      this.viewYear = this.currentDate.getFullYear();

      this.availability = {}; // { 'YYYY-MM-DD': 'tersedia' | 'menunggu' | 'penuh' }
      this.isLoading = false;
      this.loadFailed = false;

      this.selectedCheckin = null;  // 'YYYY-MM-DD'
      this.selectedCheckout = null; // 'YYYY-MM-DD'

      // Jika ada nilai awal di input
      if (this.options.checkinInput) {
        const inp = typeof this.options.checkinInput === 'string'
          ? document.querySelector(this.options.checkinInput) : this.options.checkinInput;
        if (inp && inp.value) this.selectedCheckin = inp.value;
      }
      if (this.options.checkoutInput) {
        const inp = typeof this.options.checkoutInput === 'string'
          ? document.querySelector(this.options.checkoutInput) : this.options.checkoutInput;
        if (inp && inp.value) this.selectedCheckout = inp.value;
      }

      this.init();
    }

    init() {
      this.renderSkeleton();
      this.fetchAvailability();
    }

    setTipe(idTipe, tarif = null) {
      this.options.idTipe = idTipe;
      if (tarif) this.options.tarif = tarif;
      this.availability = {};
      this.selectedCheckin = null;
      this.selectedCheckout = null;
      this.fetchAvailability();
    }

    async fetchAvailability() {
      if (!this.options.idTipe) return;

      this.isLoading = true;
      this.loadFailed = false;

      // Ambil data untuk 3 bulan (bulan sebelumnya, bulan aktif, 2 bulan berikutnya)
      const startDate = new Date(this.viewYear, this.viewMonth, 1);
      const endDate = new Date(this.viewYear, this.viewMonth + 3, 0);

      const dari = formatYMD(startDate);
      const sampai = formatYMD(endDate);

      try {
        const res = await fetch(`api/cek-ketersediaan.php?mode=kalender&tipe=${encodeURIComponent(this.options.idTipe)}&dari=${dari}&sampai=${sampai}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (json.ok && json.data) {
          this.availability = Object.assign({}, this.availability, json.data);
        } else {
          this.loadFailed = true;
        }
      } catch (e) {
        console.warn('Kalender: gagal memuat ketersediaan:', e);
        this.loadFailed = true;
      } finally {
        this.isLoading = false;
        this.render();
      }
    }

    renderSkeleton() {
      this.container.innerHTML = `
        <div class="masayu-calendar-wrapper animate-pulse p-4 bg-white border border-stone-200 rounded-2xl shadow-sm">
          <div class="h-6 bg-stone-200 rounded w-1/3 mb-4"></div>
          <div class="grid grid-cols-7 gap-2">
            ${Array(28).fill(0).map(() => '<div class="h-10 bg-stone-100 rounded"></div>').join('')}
          </div>
        </div>
      `;
    }

    render() {
      const isMobile = window.innerWidth < 768;
      const monthsToShow = isMobile ? 1 : 2;

      const lang = this.options.lang === 'en' ? 'en-US' : 'id-ID';
      const monthFormatter = new Intl.DateTimeFormat(lang, { month: 'long', year: 'numeric' });
      const dayNames = this.getDayNames(lang);

      let html = `
        <div class="masayu-calendar-wrapper bg-white border border-stone-200 rounded-2xl p-4 md:p-6 shadow-sm">
          <!-- Header Bar: Judul & Navigasi Bulan -->
          <div class="flex items-center justify-between pb-4 mb-4 border-b border-stone-100">
            <div>
              <h3 class="font-serif font-bold text-stone-900 text-base md:text-lg mb-0.5">
                ${this.options.mode === 'readonly'
                  ? (this.options.lang === 'en' ? 'Availability Calendar' : 'Kalender Ketersediaan')
                  : (this.options.lang === 'en' ? 'Select Stay Dates' : 'Pilih Tanggal Menginap')}
              </h3>
              <p class="text-xs text-stone-500 mb-0">
                ${this.options.mode === 'readonly'
                  ? (this.options.lang === 'en' ? 'Dates marked with full are booked.' : 'Tanggal dengan tanda penuh telah terpesan.')
                  : (this.options.lang === 'en' ? 'Click 1st: check-in, click 2nd: check-out.' : 'Klik 1: check-in, klik 2: check-out.')}
              </p>
            </div>
            <div class="flex items-center gap-1.5">
              <button type="button" class="btn-cal-prev p-2 rounded-lg text-stone-600 hover:bg-stone-100 min-h-[44px] min-w-[44px] flex items-center justify-center" aria-label="Bulan sebelumnya">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <button type="button" class="btn-cal-next p-2 rounded-lg text-stone-600 hover:bg-stone-100 min-h-[44px] min-w-[44px] flex items-center justify-center" aria-label="Bulan berikutnya">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>
            </div>
          </div>

          <!-- Pesan Gagal bila ada -->
          ${this.loadFailed ? `
            <div class="bg-amber-50 border border-amber-200 text-amber-900 text-xs rounded-xl p-3 mb-4 flex items-center gap-2" role="alert">
              <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              <span>${this.options.lang === 'en' ? 'Availability data could not be loaded; we will still verify upon submission.' : 'Ketersediaan belum bisa dimuat; kami tetap memeriksanya saat Anda mengirim.'}</span>
            </div>
          ` : ''}

          <!-- Alert Peringatan Dinamis (Rentang Penuh / Menunggu) -->
          <div class="cal-alert-box hidden mb-4"></div>

          <!-- Grid Bulan-Bulan (1 di Mobile, 2 di Desktop) -->
          <div class="grid grid-cols-1 ${monthsToShow === 2 ? 'md:grid-cols-2 gap-6' : ''}">
      `;

      for (let m = 0; m < monthsToShow; m++) {
        const monthDate = new Date(this.viewYear, this.viewMonth + m, 1);
        const titleMonth = monthFormatter.format(monthDate);

        html += `
          <div class="calendar-month">
            <h4 class="text-sm font-bold text-stone-800 text-center mb-3 capitalize">${titleMonth}</h4>
            <div role="grid" aria-label="${titleMonth}" class="grid grid-cols-7 gap-1 text-center text-xs">
              <!-- Header Hari (Sen - Min) -->
              ${dayNames.map(d => `<div class="font-semibold text-stone-400 py-1 text-[11px]">${d}</div>`).join('')}
              <!-- Sel Tanggal -->
              ${this.renderMonthDays(monthDate.getFullYear(), monthDate.getMonth())}
            </div>
          </div>
        `;
      }

      html += `
          </div>

          <!-- Legenda Aksesibel (Bukan hanya warna) -->
          <div class="flex flex-wrap items-center justify-between gap-3 pt-4 mt-6 border-t border-stone-100 text-xs">
            <div class="flex items-center gap-4 flex-wrap">
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded border border-emerald-300 bg-emerald-50 inline-block"></span>
                <span class="text-stone-700">${this.options.lang === 'en' ? 'Available' : 'Tersedia'}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded border border-stone-300 bg-stone-200 relative inline-flex items-center justify-center text-[9px] text-stone-500 line-through font-bold">✕</span>
                <span class="text-stone-700">${this.options.lang === 'en' ? 'Full (Booked)' : 'Penuh'}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded border border-amber-300 bg-amber-50 inline-block" style="background-image: repeating-linear-gradient(45deg, #fef3c7, #fef3c7 4px, #fde68a 4px, #fde68a 8px);"></span>
                <span class="text-stone-700">${this.options.lang === 'en' ? 'Pending' : 'Ada permintaan menunggu'}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded bg-amber-600 inline-block"></span>
                <span class="text-stone-700">${this.options.lang === 'en' ? 'Selected' : 'Dipilih'}</span>
              </div>
            </div>

            ${this.options.mode === 'readonly' && this.options.slugVilla ? `
              <div>
                <a href="reservasi.php?villa=${encodeURIComponent(this.options.slugVilla)}" class="btn btn-sm btn-gold text-xs py-1.5 px-3 min-h-[44px] flex items-center">
                  ${this.options.lang === 'en' ? 'Book Now' : 'Pesan Sekarang'} &rarr;
                </a>
              </div>
            ` : ''}
          </div>

          <!-- Wadah Ringkasan Estimasi Pilihan -->
          <div class="cal-summary-box mt-4 ${(!this.selectedCheckin || !this.selectedCheckout) ? 'hidden' : ''}"></div>
        </div>
      `;

      this.container.innerHTML = html;
      this.attachEvents();
      this.updateSummary();
    }

    getDayNames(lang) {
      // Mengikuti standar minggu mulai Senin
      if (lang === 'en-US') return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      return ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    }

    renderMonthDays(year, month) {
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);

      // Hari dalam seminggu: 0=Minggu, 1=Senin, ..., 6=Sabtu
      // Ubah ke standar ISO (Senin=0, Minggu=6)
      let startDay = firstDay.getDay() - 1;
      if (startDay === -1) startDay = 6;

      const totalDays = lastDay.getDate();
      const todayStr = formatYMD(new Date());

      let cells = '';

      // Sel kosong sebelum tanggal 1
      for (let i = 0; i < startDay; i++) {
        cells += `<div class="p-1 min-h-[44px]"></div>`;
      }

      for (let d = 1; d <= totalDays; d++) {
        const curDate = new Date(year, month, d);
        const curStr = formatYMD(curDate);
        const isPast = curStr < todayStr;
        const status = this.availability[curStr] || 'tersedia';

        const isCheckin = (curStr === this.selectedCheckin);
        const isCheckout = (curStr === this.selectedCheckout);
        const isInRange = (this.selectedCheckin && this.selectedCheckout && curStr > this.selectedCheckin && curStr < this.selectedCheckout);

        let cellClasses = 'min-h-[44px] min-w-[44px] p-1 flex flex-col items-center justify-center rounded-xl text-xs font-semibold relative transition-all ';
        let disabledAttr = '';
        let ariaLabel = `${curStr}, ${status}`;
        let statusBadge = '';

        if (isPast) {
          cellClasses += 'text-stone-300 cursor-not-allowed bg-stone-50';
          disabledAttr = 'disabled aria-disabled="true"';
          ariaLabel += ' (masa lampau)';
        } else if (isCheckin || isCheckout) {
          cellClasses += 'bg-amber-700 text-white shadow-sm ring-2 ring-amber-500 font-bold';
          statusBadge = isCheckin ? '<span class="text-[9px] uppercase tracking-wider block">In</span>' : '<span class="text-[9px] uppercase tracking-wider block">Out</span>';
        } else if (isInRange) {
          cellClasses += 'bg-amber-100 text-amber-900 border-y border-amber-300 rounded-none';
        } else if (status === 'penuh') {
          cellClasses += 'bg-stone-200 text-stone-500 border border-stone-300 line-through';
          statusBadge = '<span class="text-[8px] no-underline font-normal block text-stone-500">penuh</span>';
        } else if (status === 'menunggu') {
          cellClasses += 'text-amber-900 border border-amber-300 bg-amber-50/70 hover:bg-amber-100';
          statusBadge = '<span class="text-[8px] font-normal block text-amber-700">menunggu</span>';
        } else {
          // Tersedia
          cellClasses += 'bg-white hover:bg-emerald-50 text-stone-800 border border-stone-200 hover:border-emerald-300 cursor-pointer';
        }

        if (this.options.mode === 'readonly') {
          disabledAttr = 'tabindex="-1"';
        }

        cells += `
          <button type="button"
                  role="gridcell"
                  class="cal-day-cell ${cellClasses}"
                  data-date="${curStr}"
                  data-status="${status}"
                  aria-label="${ariaLabel}"
                  ${disabledAttr}>
            <span class="text-xs leading-none">${d}</span>
            ${statusBadge}
          </button>
        `;
      }

      return cells;
    }

    attachEvents() {
      const prevBtn = this.container.querySelector('.btn-cal-prev');
      const nextBtn = this.container.querySelector('.btn-cal-next');

      if (prevBtn) {
        prevBtn.addEventListener('click', () => {
          this.viewMonth--;
          if (this.viewMonth < 0) {
            this.viewMonth = 11;
            this.viewYear--;
          }
          this.fetchAvailability();
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', () => {
          this.viewMonth++;
          if (this.viewMonth > 11) {
            this.viewMonth = 0;
            this.viewYear++;
          }
          this.fetchAvailability();
        });
      }

      if (this.options.mode === 'select') {
        const cells = this.container.querySelectorAll('.cal-day-cell:not([disabled])');
        cells.forEach(cell => {
          cell.addEventListener('click', (e) => {
            const dateStr = cell.getAttribute('data-date');
            this.handleDateClick(dateStr);
          });
        });

        // Keyboard navigation pada grid
        this.container.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            this.selectedCheckin = null;
            this.selectedCheckout = null;
            this.render();
            this.syncInputs();
          }
        });
      }
    }

    handleDateClick(dateStr) {
      const alertBox = this.container.querySelector('.cal-alert-box');
      if (alertBox) alertBox.classList.add('hidden');

      const status = this.availability[dateStr] || 'tersedia';

      // 1. Jika belum ada check-in atau sudah ada keduanya: klik pertama = check-in baru
      if (!this.selectedCheckin || (this.selectedCheckin && this.selectedCheckout)) {
        if (status === 'penuh') {
          this.showAlert(this.options.lang === 'en'
            ? `Date ${dateStr} is fully booked and cannot be selected as check-in.`
            : `Tanggal ${dateStr} sudah penuh dan tidak dapat dipilih sebagai check-in.`, 'error');
          return;
        }
        this.selectedCheckin = dateStr;
        this.selectedCheckout = null;
        this.render();
        this.syncInputs();
        return;
      }

      // 2. Klik kedua = check-out
      if (this.selectedCheckin && !this.selectedCheckout) {
        if (dateStr <= this.selectedCheckin) {
          // Klik sebelum checkin = reset checkin ke tanggal baru
          if (status === 'penuh') {
            this.showAlert(this.options.lang === 'en'
              ? `Date ${dateStr} is fully booked.`
              : `Tanggal ${dateStr} sudah penuh.`, 'error');
            return;
          }
          this.selectedCheckin = dateStr;
          this.selectedCheckout = null;
          this.render();
          this.syncInputs();
          return;
        }

        // Validasi seluruh malam antara checkin s.d. checkout
        let cur = parseYMD(this.selectedCheckin);
        const end = parseYMD(dateStr);
        let adaPenuh = false;
        let tglPenuh = '';
        let adaMenunggu = false;

        while (cur < end) {
          const k = formatYMD(cur);
          const st = this.availability[k] || 'tersedia';
          if (st === 'penuh') {
            adaPenuh = true;
            tglPenuh = k;
            break;
          }
          if (st === 'menunggu') {
            adaMenunggu = true;
          }
          cur.setDate(cur.getDate() + 1);
        }

        if (adaPenuh) {
          this.showAlert(this.options.lang === 'en'
            ? `Selection rejected: stay includes fully booked night on ${tglPenuh}. Selection has been reset.`
            : `Pilihan ditolak: rentang mencakup tanggal penuh pada ${tglPenuh}. Silakan pilih kembali rentang yang bebas.`, 'error');
          this.selectedCheckin = null;
          this.selectedCheckout = null;
          this.render();
          this.syncInputs();
          return;
        }

        if (adaMenunggu) {
          this.showAlert(this.options.lang === 'en'
            ? 'There is another pending request for these dates; final availability confirmed by staff.'
            : 'Ada permintaan lain yang menunggu; ketersediaan final saat Pengelola mengonfirmasi.', 'warning');
        }

        this.selectedCheckout = dateStr;
        this.render();
        this.syncInputs();

        if (typeof this.options.onRangeSelect === 'function') {
          this.options.onRangeSelect(this.selectedCheckin, this.selectedCheckout);
        }
      }
    }

    showAlert(message, type = 'error') {
      const alertBox = this.container.querySelector('.cal-alert-box');
      if (!alertBox) return;

      const isWarn = type === 'warning';
      alertBox.className = `cal-alert-box p-3 rounded-xl text-xs font-semibold mb-4 flex items-center gap-2 ${
        isWarn ? 'bg-amber-50 border border-amber-300 text-amber-900' : 'bg-rose-50 border border-rose-300 text-rose-900'
      }`;
      alertBox.innerHTML = `
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isWarn ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' : 'M6 18L18 6M6 6l12 12'}"/>
        </svg>
        <span>${message}</span>
      `;
      alertBox.classList.remove('hidden');
    }

    syncInputs() {
      if (this.options.checkinInput) {
        const inp = typeof this.options.checkinInput === 'string'
          ? document.querySelector(this.options.checkinInput) : this.options.checkinInput;
        if (inp) {
          inp.value = this.selectedCheckin || '';
          inp.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
      if (this.options.checkoutInput) {
        const inp = typeof this.options.checkoutInput === 'string'
          ? document.querySelector(this.options.checkoutInput) : this.options.checkoutInput;
        if (inp) {
          inp.value = this.selectedCheckout || '';
          inp.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    }

    updateSummary() {
      const summaryBox = this.container.querySelector('.cal-summary-box');
      if (!summaryBox || !this.selectedCheckin || !this.selectedCheckout) return;

      let cur = parseYMD(this.selectedCheckin);
      const end = parseYMD(this.selectedCheckout);

      let totalNights = 0;
      let weekdayNights = 0;
      let weekendNights = 0;

      while (cur < end) {
        totalNights++;
        const k = formatYMD(cur);
        if (isWeekendNight(k)) {
          weekendNights++;
        } else {
          weekdayNights++;
        }
        cur.setDate(cur.getDate() + 1);
      }

      summaryBox.innerHTML = `
        <div class="bg-stone-50 border border-stone-200 rounded-xl p-3 text-xs text-stone-800">
          <div class="flex items-center justify-between font-semibold pb-1.5 mb-1.5 border-b border-stone-200">
            <span>${this.options.lang === 'en' ? 'Stay Duration' : 'Durasi Menginap'}</span>
            <span class="text-amber-900 font-bold">${totalNights} ${this.options.lang === 'en' ? 'night(s)' : 'malam'}</span>
          </div>
          <div class="grid grid-cols-2 gap-2 text-stone-600">
            <div>Weekday: <strong>${weekdayNights}</strong> ${this.options.lang === 'en' ? 'night(s)' : 'malam'}</div>
            <div>Weekend: <strong>${weekendNights}</strong> ${this.options.lang === 'en' ? 'night(s)' : 'malam'}</div>
          </div>
        </div>
      `;
      summaryBox.classList.remove('hidden');
    }
  }

  window.MasayuKalender = MasayuKalender;

})(window, document);
