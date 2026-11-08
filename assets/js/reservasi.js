/**
 * Interaksi Formulir Reservasi & Kalkulasi Tarif — Villa Joglo Masayu
 * Memeriksa ketersediaan tanggal dan menghitung estimasi tarif secara real-time
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const villaSelect  = document.getElementById('id_tipe');
  const checkinInput = document.getElementById('tanggal_checkin');
  const checkoutInput= document.getElementById('tanggal_checkout');
  const tamuInput    = document.getElementById('jumlah_tamu');
  const previewBox   = document.getElementById('reservasiPreviewBox');
  const statusMsgBox = document.getElementById('ketersediaanStatusBox');

  if (!villaSelect || !checkinInput || !checkoutInput || !tamuInput) return;

  // Set min date hari ini
  const today = new Date().toISOString().split('T')[0];
  checkinInput.min = today;
  checkinInput.addEventListener('change', () => {
    if (checkinInput.value) {
      // Minimal checkout adalah checkin + 1 hari
      const d = new Date(checkinInput.value);
      d.setDate(d.getDate() + 1);
      checkoutInput.min = d.toISOString().split('T')[0];
      if (checkoutInput.value && checkoutInput.value <= checkinInput.value) {
        checkoutInput.value = checkoutInput.min;
      }
    }
    cekKetersediaanDanTarif();
  });

  checkoutInput.addEventListener('change', cekKetersediaanDanTarif);
  villaSelect.addEventListener('change', () => {
    updateMaxTamu();
    cekKetersediaanDanTarif();
  });
  tamuInput.addEventListener('input', cekKetersediaanDanTarif);

  function updateMaxTamu() {
    const selectedOption = villaSelect.options[villaSelect.selectedIndex];
    if (selectedOption && selectedOption.dataset.max) {
      tamuInput.max = selectedOption.dataset.max;
      const hint = document.getElementById('tamuHint');
      if (hint) {
        hint.textContent = `Kapasitas standar: ${selectedOption.dataset.std || '-'} tamu, maksimal dengan extra bed: ${selectedOption.dataset.max} tamu.`;
      }
    }
  }

  let debounceTimer = null;
  function cekKetersediaanDanTarif() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
      const idTipe = villaSelect.value;
      const checkin = checkinInput.value;
      const checkout = checkoutInput.value;
      const tamu = tamuInput.value || 1;

      if (!idTipe || !checkin || !checkout) {
        if (previewBox) previewBox.style.display = 'none';
        if (statusMsgBox) statusMsgBox.innerHTML = '';
        return;
      }

      try {
        const url = `api/cek-ketersediaan.php?id_tipe=${encodeURIComponent(idTipe)}&checkin=${encodeURIComponent(checkin)}&checkout=${encodeURIComponent(checkout)}&jumlah_tamu=${encodeURIComponent(tamu)}`;
        const res = await fetch(url);
        const data = await res.json();

        renderPreview(data);
      } catch (err) {
        console.warn('Gagal memeriksa ketersediaan:', err);
      }
    }, 250);
  }

  function renderPreview(data) {
    if (!statusMsgBox || !previewBox) return;

    statusMsgBox.innerHTML = '';
    const alertDiv = document.createElement('div');

    if (!data.tersedia) {
      alertDiv.className = 'alert alert-danger';
      alertDiv.textContent = data.pesan || 'Tanggal yang dipilih tidak tersedia.';
      statusMsgBox.appendChild(alertDiv);
      previewBox.style.display = 'none';
      return;
    }

    alertDiv.className = 'alert alert-success';
    alertDiv.textContent = data.pesan || 'Villa tersedia pada rentang tanggal tersebut.';
    statusMsgBox.appendChild(alertDiv);

    if (data.rincian) {
      const r = data.rincian;
      previewBox.style.display = 'block';

      setElementText('previewTotalMalam', `${r.total_malam} malam (${r.malam_weekday} weekday, ${r.malam_weekend} weekend)`);
      setElementText('previewSubtotalWeekday', formatRupiah(r.subtotal_weekday));
      setElementText('previewSubtotalWeekend', formatRupiah(r.subtotal_weekend));
      
      const extraBedRow = document.getElementById('previewExtraBedRow');
      if (extraBedRow) {
        if (r.extra_bed > 0) {
          extraBedRow.style.display = 'flex';
          setElementText('previewExtraBedInfo', `${r.extra_bed} extra bed x ${r.total_malam} malam`);
          setElementText('previewSubtotalExtra', formatRupiah(r.total_extra_bed));
        } else {
          extraBedRow.style.display = 'none';
        }
      }

      setElementText('previewEstimasiTotal', formatRupiah(r.estimasi_total));
    }
  }

  function setElementText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function formatRupiah(angka) {
    return 'Rp ' + Number(angka).toLocaleString('id-ID');
  }

  // Trigger pertama kali jika sudah ada nilai awal
  updateMaxTamu();
  if (checkinInput.value && checkoutInput.value) {
    cekKetersediaanDanTarif();
  }
});

