import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:8000"

def test_toast_variants():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # 1. Uji 4 Varian Toast di Desktop & Mobile
        for width in [1440, 390, 320]:
            context = browser.new_context(viewport={"width": width, "height": 800})
            page = context.new_page()
            page.goto(f"{BASE_URL}/index.php", wait_until="load")

            # Panggil 4 varian toast via window.showToast
            page.evaluate("""() => {
                window.showToast('Pemesanan villa berhasil disimpan!', { tipe: 'sukses' });
                window.showToast('Koneksi sistem stabil.', { tipe: 'info' });
                window.showToast('Unit terbatas untuk akhir pekan ini.', { tipe: 'peringatan' });
                window.showToast('Tanggal check-out tidak valid.', { tipe: 'galat' });
            }""")

            page.wait_for_selector('.toast-sukses')
            page.wait_for_selector('.toast-info')
            page.wait_for_selector('.toast-peringatan')
            page.wait_for_selector('.toast-galat')

            toasts = page.locator('.toast').all()
            assert len(toasts) == 4, f"Harus ada 4 toast, ditemukan {len(toasts)} pada lebar {width}"

            # Pastikan toast berada di bawah header (top offset > 64px)
            toast_box = toasts[0].bounding_box()
            assert toast_box['y'] >= 64, f"Toast tidak boleh menabrak header (y={toast_box['y']})"

            # Klik tombol close toast pertama
            close_btn = toasts[0].locator('.toast-close')
            close_btn.click()
            time.sleep(0.4)
            remaining_toasts = page.locator('.toast').all()
            assert len(remaining_toasts) == 3, "Toast pertama harus hilang setelah ditutup"

            context.close()

        # 2. Uji Integrasi Flash Message Logout & Bilingual
        # A. Logout dalam bahasa Indonesia
        context_id = browser.new_context(viewport={"width": 1440, "height": 900})
        page_id = context_id.new_page()
        page_id.goto(f"{BASE_URL}/login.php?lang=id", wait_until="load")
        page_id.fill('input[name="email"]', 'budi@gmail.com')
        page_id.fill('input[name="password"]', 'Pelanggan2026!')
        with page_id.expect_navigation():
            page_id.click('button[type="submit"]')

        page_id.goto(f"{BASE_URL}/index.php", wait_until="load")
        page_id.wait_for_selector('#userChip', timeout=10000)
        page_id.click('#userChip')
        page_id.wait_for_selector('#logoutForm', state="visible")
        with page_id.expect_navigation():
            page_id.click('#logoutForm button[type="submit"]')

        assert "keluar=" not in page_id.url, f"URL harus bersih dari query param notifikasi: {page_id.url}"
        page_id.wait_for_selector('.toast-info', timeout=5000)
        toast_text_id = page_id.locator('.toast-info .toast-message').inner_text()
        print("Toast logout ID:", toast_text_id)
        assert "berhasil keluar" in toast_text_id.lower()
        context_id.close()

        # B. Logout dalam bahasa Inggris
        context_en = browser.new_context(viewport={"width": 1440, "height": 900})
        page_en = context_en.new_page()
        page_en.goto(f"{BASE_URL}/login.php?lang=en", wait_until="load")
        page_en.fill('input[name="email"]', 'budi@gmail.com')
        page_en.fill('input[name="password"]', 'Pelanggan2026!')
        with page_en.expect_navigation():
            page_en.click('button[type="submit"]')

        page_en.goto(f"{BASE_URL}/index.php", wait_until="load")
        page_en.wait_for_selector('#userChip', timeout=10000)
        page_en.click('#userChip')
        page_en.wait_for_selector('#logoutForm', state="visible")
        with page_en.expect_navigation():
            page_en.click('#logoutForm button[type="submit"]')

        assert "keluar=" not in page_en.url, f"URL harus bersih dari query param notifikasi: {page_en.url}"
        page_en.wait_for_selector('.toast-info', timeout=5000)
        toast_text_en = page_en.locator('.toast-info .toast-message').inner_text()
        print("Toast logout EN:", toast_text_en)
        assert "signed out" in toast_text_en.lower()
        context_en.close()

        browser.close()
        print("SEMUA PENGUJIAN TAHAP 3 TOAST NOTIFIKASI LULUS 100%!")

if __name__ == '__main__':
    test_toast_variants()
