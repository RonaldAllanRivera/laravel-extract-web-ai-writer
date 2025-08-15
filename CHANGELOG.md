# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [0.1.1] - 2025-08-15

### Changed
- Extraction pipeline hardening in `ContentExtractor`:
  - Strip `<script>...</script>` blocks prior to text conversion; add fallback strip in `removeCtaPhrases()`.
  - BR/newline handling: convert `<br>` to newlines; add newlines after closing block tags (`p`, `div`, `li`, `section`, `article`, `h1–h6`, `tr`).
  - Whitespace normalization: remove tabs, convert NBSP to space, collapse multiple spaces, limit to a single blank line between paragraphs, trim trailing spaces per line.
  - Emoji/symbol cleanup: remove arrows, stars, checkmarks, and pictographs.
  - Line-level removals: menu/section labels (`overview`, `features`, `reviews`, `faq(s)`, `frequently asked questions`), “as seen on”, “viral on TikTok”.
  - Commercial noise: offers/promos/discounts (`offer|deal|promotion|sale|today only|limited time`, percent-off), CTAs (`buy/order/add to cart/checkout`).
  - Social proof: ratings/reviews lines (e.g., `4.8/5 ... verified reviews`, `(12,345 verified reviews)`, `verified buyer`).
  - Logistics: shipping/stock/dispatch/delivery/carriers (USPS/FedEx/UPS/DHL), tracking, warehouse.
  - Guarantees: money-back guarantee variations.
  - Dates: ISO (`YYYY-MM-DD`) and common month-name formats.
  - DOM pass also removes CTA-like nodes (`a`, `button`, submit inputs, `.btn`) matching purchase/discount/shipping patterns.
  - HTTP client: set desktop User-Agent header and 20s timeout for better parity with real browsers.

### Fixed
- Normalize whitespace when displaying `cleaned_text` in Filament views:
  - `PageResource@infolist`: render as HTML with `nl2br(e(...))` instead of `white-space: pre-wrap`.
  - Custom partial `view-cleaned.blade.php`: use `whitespace-pre-line` instead of `<pre>` to collapse extra spaces/tabs while keeping line breaks.

### Documentation
- README: document script stripping, whitespace normalization (tabs removal, NBSP to space, collapse spaces), regex cleaning (menus/offers/reviews/shipping/dates/guarantees/emojis), and UI rendering approach (nl2br + normal whitespace). Add UA/timeout note.
- CHANGELOG: add this entry.

## [0.1.0] - 2025-08-15

### Added
- README with features, architecture, getting started, and roadmap.
- Initial CHANGELOG following Keep a Changelog format.

### Changed
- Adopted Filament v4 Schemas for `form()` and `infolist()` APIs.
- Admin UX: table rows link directly to the View page via `recordUrl()`.

### Fixed
- Page detail (View) 500 error by removing unavailable `Section` component usage and simplifying the `infolist()` schema in `PageResource`.
- Extract Content page type mismatch by changing `ExtractContent::form()` signature to accept/return `Filament\Schemas\Schema`.

### Extraction Improvements
- Source-only extraction to avoid missing content hidden by client-side JS/UI (accordions, tabs, etc.).
- Restricted processing to `<body>` content; removed HTML/JS, templates/iframes/SVG, and navigation/footer chrome.
- Plaintext conversion now preserves line breaks and reduces over-aggressive whitespace collapsing.
- Continued CTA/footer phrase cleanup via `removeCtaPhrases()`.
