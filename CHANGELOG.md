# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [0.1.4] - 2025-08-15

### Changed
- Increase AI output token defaults in `config/ai.php`: `max_output_tokens` 2200, `input_token_budget` 8000.
- Per-layout defaults: `layouts.interstitial.max_output_tokens` 2200, `layouts.advertorial.max_output_tokens` 2200.

### Fixed
- Interstitial table rendering: convert single-asterisk emphasis `*text*` to `<em>` in `AiContentFormatter::toHtmlTable()` so lines like `*Feel the difference with every session!*` render correctly.

### Documentation
- README: add AI generation settings, cache clear commands, and export guidance (mysqldump/HeidiSQL).
- `.env.example`: update token defaults and set `AI_LAYOUT_INTERSTITIAL_MAX_TOKENS=2200`.

## [0.1.3] - 2025-08-15

### Added
- Filament bulk actions on `PageResource`:
  - "Re-clean selected" (re-run cleaner on stored text, no network).
  - "Refetch & re-clean selected" (download again, then clean; updates `cleaned_text` and may update `meta.title`).
- Header help icon opens a modal explaining when to use each bulk action; actions include tooltips.
- Artisan command `pages:reclean {--refetch}` to batch re-clean all pages from CLI.
 - Fetch metadata capture for pages: `last_fetched_at`, `http_status`, `content_length`, `fetch_error` (migration + model casts).
 - ContentExtractor now returns `http_status` and `content_length` on success; exceptions carry HTTP status code on non-2xx.
 - Pages admin UI shows HTTP badge and Fetched timestamp; adds a "Fetch failed" filter; detail view shows metadata and error.
 - CLI `pages:reclean --refetch` and bulk refetch action persist success/failure metadata (status, length, error, timestamp).

### Changed
- Error surfacing improvements:
  - HTTP badge includes a tooltip with status, bytes, fetched time, or the recorded error message.
  - Bulk action notifications use danger styling when any errors occur and include error counts.
  - Extractor exceptions are clearer: include HTTP reason phrase (e.g., 404 Not Found), network error messages, non-HTML content-type, and oversized page size in bytes.

### Fixed
- Resolve Filament v4 namespace and API for bulk actions (`Filament\\Actions\\BulkAction`) and widget property signatures (`$view` non-static, `$sort` static).

### Documentation
- README updates for bulk actions, help modal, fetch metadata fields/columns/filters, and CLI behavior.

## [0.1.2] - 2025-08-15

### Added
- Unit tests for text conversion and CTA cleanup in `tests/Unit/ContentExtractorTest.php`.

### Changed
- Duplicate-safe storage for pages:
  - Submission upserts by URL in `app/Filament/Pages/ExtractContent.php` (updates `cleaned_text` and `meta.title`, preserves `page_type`).
  - DB migration adds unique index on `pages.url` and deduplicates existing rows by keeping the earliest id.
- HTTP fetch guardrails in `app/Services/ContentExtractor.php`:
  - Follow redirects; require 2xx status.
  - Enforce HTML/XHTML `Content-Type` and 3MB max response size.

### Documentation
- README updated to reflect duplicate-safe storage, HTTP guardrails, and unit test instructions.

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
