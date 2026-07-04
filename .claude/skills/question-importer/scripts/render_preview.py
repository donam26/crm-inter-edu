"""Render one question (JSON) via the KaTeX HTML harness and screenshot it with Playwright."""
import argparse, json, os, pathlib
from playwright.sync_api import sync_playwright

HERE = pathlib.Path(__file__).parent
TEMPLATE = (HERE / "preview-template.html").as_uri()


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--question", required=True, help="path to question JSON")
    ap.add_argument("--out", required=True)
    args = ap.parse_args()
    data = json.loads(pathlib.Path(args.question).read_text(encoding="utf-8"))

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(viewport={"width": 800, "height": 1200}, device_scale_factor=2)
        page.goto(TEMPLATE)
        page.wait_for_function("typeof window.renderQuestion === 'function'")
        page.evaluate("(d) => window.renderQuestion(d)", data)
        page.wait_for_function("window.__rendered === true")
        # Wait for any CDN <img> to finish loading so the screenshot isn't taken
        # mid-load (which leaves layout gaps / clipped figures).
        page.wait_for_function(
            "Array.from(document.images).every(i => i.complete && i.naturalHeight > 0)")
        page.wait_for_timeout(300)  # let KaTeX fonts settle
        page.locator("#q").screenshot(path=args.out)
        browser.close()
    print(args.out)


if __name__ == "__main__":
    main()
