# Shared navigation

- `tools/render-header.py` is the single HTML template, with Czech/English and page-context link sets. Run `python3 tools/render-header.py` after changing it and commit the generated HTML. Deployment checks for drift.
- `header.css` owns all header styling. `style.css` imports it; the standalone 404 page loads it directly so its other styles stay independent.
- `header.js` owns hamburger state, keyboard dismissal and homepage language-section mapping. Navigation remains visible if JavaScript is unavailable.
- There is one navigation element on each page. At 800px and below it becomes a collapsible menu; at 801–1250px desktop links use a separate row. Wider layouts may wrap naturally instead of shrinking text or hiding links.
- The existing Czech cart uses the same `#count` element at every width. Admin logout retains its ID and visibility logic. Language links are generated only when the translated page exists.

## Verification

Run `python3 tools/render-header.py --check` and `node --check header.js`.
With Playwright and Chromium installed, run `node tests/header_test.cjs` (set `NODE_PATH` if Playwright is installed outside this repository). It checks every HTML page at 320, 375, 768, 800, 801, 1024, 1250, 1251, 1366 and 1920px; navigation destinations and fragments; overlap; Enter/Space/Tab/Escape; ARIA state; cart updates; language section mapping; and the no-JavaScript fallback.
Set `HEADER_TEST_URL=https://kupsiapku.cz` to test production, and optionally `HEADER_SCREENSHOTS` to save visual samples.
