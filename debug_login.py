from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:8000"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={"width": 1440, "height": 900})
    page.goto(f"{BASE_URL}/login.php")
    page.fill('input[name="email"]', 'budi@gmail.com')
    page.fill('input[name="password"]', 'Pelanggan2026!')
    with page.expect_navigation():
        page.click('button[type="submit"]')
    print("Current URL after login:", page.url)
    auth_data = page.evaluate("() => window.MASAYU_AUTH")
    print("MASAYU_AUTH:", auth_data)
    user_chip_count = page.locator('#userChip').count()
    print("userChip count:", user_chip_count)
    browser.close()

