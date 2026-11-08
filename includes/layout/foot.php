<?php
/**
 * Footer Layout — Villa Joglo Masayu
 * Mencetak container footer, modal lightbox, dan skrip JavaScript
 */
?>
  <!-- Footer injected by script.js -->
  <div id="site-footer"></div>

  <!-- Lightbox (Shared) -->
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
          <path d="M13 16L7 10L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
      <img src="" alt="" id="lightboxImg" class="lightbox-img" />
      <button class="lightbox-arrow lightbox-arrow--next" id="lightboxNext" aria-label="Foto berikutnya" type="button">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
    </div>
  </div>

  <script src="assets/js/script.js" defer></script>
  <?php if (!empty($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $scriptPath): ?>
      <script src="<?= e($scriptPath) ?>" defer></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>

</html>

