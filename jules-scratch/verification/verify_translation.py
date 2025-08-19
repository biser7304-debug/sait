from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        page.goto("http://localhost:8000/index.php", wait_until="load")
        page.wait_for_load_state("networkidle")
        page.screenshot(path="jules-scratch/verification/verification.png")
    except Exception as e:
        print(f"An error occurred: {e}")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
