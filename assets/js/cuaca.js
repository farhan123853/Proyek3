/**
 * Widget Prakiraan Cuaca BMKG — Villa Joglo Masayu
 * Kebutuhan: F-02, F-03, T-13, T-14
 * Keamanan: Merender seluruh data dinamis menggunakan textContent (mencegah XSS)
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('weatherWidgetContainer');
  if (!container) return;

  fetchWeather(container);
});

async function fetchWeather(container) {
  // Render loading state menggunakan elemen DOM aman
  container.innerHTML = '';
  const loadingDiv = document.createElement('div');
  loadingDiv.className = 'weather-fallback';
  loadingDiv.textContent = 'Memuat informasi prakiraan cuaca dari BMKG...';
  container.appendChild(loadingDiv);

  try {
    const res = await fetch('api/cuaca.php');
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const json = await res.json();
    renderWeather(container, json);
  } catch (err) {
    renderFallback(container, 'Informasi cuaca sementara tidak dapat dimuat. Layanan reservasi dan informasi villa tetap berfungsi normal.');
  }
}

function renderWeather(container, data) {
  container.innerHTML = '';

  const widget = document.createElement('div');
  widget.className = 'weather-widget';

  // Header Widget
  const header = document.createElement('div');
  header.className = 'weather-header';

  const titleWrap = document.createElement('div');
  const title = document.createElement('h3');
  title.className = 'weather-title';
  title.textContent = 'Prakiraan Cuaca Sekitar Villa';
  
  const loc = document.createElement('div');
  loc.className = 'weather-source';
  loc.textContent = 'Lokasi: ' + (data.lokasi || 'Sukoharjo, Ngaglik, Sleman');

  titleWrap.appendChild(title);
  titleWrap.appendChild(loc);

  const sourceTag = document.createElement('div');
  sourceTag.className = 'weather-source';
  sourceTag.textContent = 'Sumber Data: ';
  const sourceBold = document.createElement('strong');
  sourceBold.textContent = 'BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)';
  sourceTag.appendChild(sourceBold);

  header.appendChild(titleWrap);
  header.appendChild(sourceTag);
  widget.appendChild(header);

  // Jika status error atau tidak ada slot
  if (data.status === 'error' || !data.data || data.data.length === 0) {
    const fallbackBox = document.createElement('div');
    fallbackBox.className = 'weather-fallback';
    fallbackBox.textContent = data.pesan || 'Layanan cuaca sementara tidak dapat dihubungi. Informasi dan reservasi villa tetap dapat digunakan dengan lancar.';
    widget.appendChild(fallbackBox);
    container.appendChild(widget);
    return;
  }

  // Grid Cuaca
  const grid = document.createElement('div');
  grid.className = 'weather-grid';

  data.data.forEach(item => {
    const slot = document.createElement('div');
    slot.className = 'weather-slot';

    // Waktu
    const timeEl = document.createElement('div');
    timeEl.className = 'weather-slot-time';
    timeEl.textContent = formatWeatherTime(item.waktu);

    // Suhu
    const tempEl = document.createElement('div');
    tempEl.className = 'weather-slot-temp';
    tempEl.textContent = item.suhu ? `${item.suhu}°C` : '-';

    // Deskripsi Cuaca
    const descEl = document.createElement('div');
    descEl.className = 'weather-slot-desc';
    descEl.textContent = item.cuaca || '-';

    // Meta (Kelembapan & Angin)
    const metaEl = document.createElement('div');
    metaEl.className = 'weather-slot-meta';
    const parts = [];
    if (item.kelembapan) parts.push(`Lembap: ${item.kelembapan}%`);
    if (item.kecepatan_angin) parts.push(`Angin: ${item.kecepatan_angin} km/j`);
    metaEl.textContent = parts.join(' · ');

    slot.appendChild(timeEl);
    slot.appendChild(tempEl);
    slot.appendChild(descEl);
    if (parts.length > 0) {
      slot.appendChild(metaEl);
    }

    grid.appendChild(slot);
  });

  widget.appendChild(grid);
  container.appendChild(widget);
}

function renderFallback(container, message) {
  container.innerHTML = '';
  const widget = document.createElement('div');
  widget.className = 'weather-widget';

  const header = document.createElement('div');
  header.className = 'weather-header';
  const title = document.createElement('h3');
  title.className = 'weather-title';
  title.textContent = 'Informasi Cuaca';
  const source = document.createElement('div');
  source.className = 'weather-source';
  source.textContent = 'Sumber: BMKG';
  header.appendChild(title);
  header.appendChild(source);

  const fallbackBox = document.createElement('div');
  fallbackBox.className = 'weather-fallback';
  fallbackBox.textContent = message;

  widget.appendChild(header);
  widget.appendChild(fallbackBox);
  container.appendChild(widget);
}

function formatWeatherTime(dateTimeStr) {
  if (!dateTimeStr) return '-';
  try {
    // Format YYYY-MM-DD HH:mm:ss atau YYYYMMDDHHmm
    const parts = dateTimeStr.split(' ');
    if (parts.length >= 2) {
      const timeParts = parts[1].split(':');
      const dateParts = parts[0].split('-');
      const tgl = dateParts.length === 3 ? `${dateParts[2]}/${dateParts[1]}` : '';
      return `${tgl} ${timeParts[0]}:${timeParts[1]} WIB`;
    }
    return dateTimeStr;
  } catch (e) {
    return dateTimeStr;
  }
}

