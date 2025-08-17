# WebText Distiller

A Laravel + Filament admin app that:

- Extracts content from marketing/landing/interstitial pages by reading the page source (inside `<body>`), stripping HTML/JS.
- Cleans the text (removes shipping/order/discount/footer/CTA patterns).
- Stores the cleaned content to MySQL for easy review and copy/export.
- Prepares content for AI-driven rewriting (interstitial or advertorial layouts).

Built for fast operator workflows in an admin panel, with copy-friendly views and modern DX.

---

## Features

- **Source-first extraction**: Reads HTML source only (no client JS required) to avoid missing text hidden behind accordions/UI.
- **Noise removal**: Drops scripts, styles, nav/footer, templates, SVG/iframes; trims obvious CTA elements.
- **Text cleaning**: Converts to plaintext, strips `<script>` blocks, preserves paragraphs/line breaks, removes tabs and collapses extra spaces; conservative CTA phrase cleanup.
- **Filament v4 Admin**: Tables for quick review; record view shows copyable content with line breaks (nl2br) and normal whitespace (no pre-wrap).
- **Ready for AI**: Data model and flow designed to plug prompts for interstitial/advertorial generation.
- **Duplicate-safe storage**: URL upsert prevents duplicate `pages` records; unique index on `pages.url`.
- **Admin bulk actions**: "Re-clean selected" and "Refetch & re-clean selected" for fast batch processing.
- **Help via header icon**: Click the “?” icon to open a modal explaining when to use each bulk action; actions also include tooltips.
- **Fetch metadata tracking**: Capture `HTTP` status, `Last Fetched` timestamp, `Content Length`, and any `Fetch Error`. Pages list shows an HTTP badge and fetched time; includes a "Fetch failed" filter. Bulk refetch and CLI persist success/failure details.
- **Collapsible Original Content**: In the Page view, the original fetched text appears in a collapsible box (default collapsed) styled like the AI Interstitial box. Text inside uses black color for readability.

## Tech Stack

- Laravel 12, PHP 8.2+
- Livewire 3, Filament 4 (Panels, Tables, Schemas, Infolists)
- MySQL (Laragon), Guzzle (HTTP), Symfony DomCrawler + HTML5

## Architecture Overview

- `app/Services/ContentExtractor.php`
  - Fetches HTML via Guzzle
  - Uses desktop UA header and a 20s timeout; follows redirects
  - Limits to `<body>`, removes UI chrome; strips `<script>...</script>` before text conversion
  - Converts to plain text and applies `removeCtaPhrases()` (keeps FAQ `Q:`/`A:` lines; fallback `<script>` removal)
  - Returns fetch metadata: `http_status` and `content_length`
- Guardrails: require 2xx status, `Content-Type` HTML/XHTML, and <= 3MB body size
- `app/Filament/Pages/ExtractContent.php`
  - Simple form to submit a URL; upserts a `Page` by URL (updates `cleaned_text` and `meta.title`, preserves `page_type`)
  - Saves fetch metadata on success: `last_fetched_at`, `http_status`, `content_length`, clears `fetch_error`
- `app/Filament/Resources/PageResource.php`
  - List view with row click -> View page
  - Infolist shows URL, meta, and copyable `cleaned_text`
  - Table columns include `HTTP` badge and `Fetched` timestamp; filter "Fetch failed"
- `app/Models/Page.php` with casts and fillables
 - DB: unique index on `pages.url` (migration deduplicates existing rows by keeping earliest id)

## Getting Started (Windows/Laragon)

1. **Install dependencies**
   - `composer install`
   - `npm install`
2. **Environment**
   - Copy `.env.example` to `.env`
   - Set DB credentials for your Laragon MySQL
   - `php artisan key:generate`
3. **Database**
   - `php artisan migrate`
4. **Run (concurrent dev)**
   - `composer dev`
   - This starts: PHP server, queue listener, and Vite dev server
5. **Open Admin**
   - Visit `http://127.0.0.1:8000/admin`
   - Extract content at `Admin > Extract Content`

Tip: If you change Filament classes or services, clear caches: `php artisan optimize:clear`.

## Usage

- Go to `Admin > Extract Content`, paste a URL, submit.
- Open `Admin > Pages`, click a row to view.
- Copy cleaned text from the View page.

#### Page view layout

On the `Pages > View` screen, content is organized for quick review:

- **AI Interstitial (latest)** — rendered as a highlighted table inside a yellow/orange box.
- **AI Advertorial (latest)** — same highlighted layout.
- **Original Content (fetched)** — collapsible details/summary in a matching yellow/orange box, collapsed by default to reduce clutter; expanding reveals the original fetched text in a white panel with black text.

### AI generation settings

- Defaults are configured in `config/ai.php` and can be overridden via `.env`.
- Current defaults:
  - `AI_MAX_OUTPUT_TOKENS=2200`
  - `AI_INPUT_TOKEN_BUDGET=8000`
- Optional `.env` overrides:

```dotenv
AI_MAX_OUTPUT_TOKENS=2200
AI_INPUT_TOKEN_BUDGET=8000
AI_LAYOUT_INTERSTITIAL_MAX_TOKENS=2200
```

After changing config or `.env`, clear caches:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

### AI output formatting (Section 10 - Features)

- Feature titles in bold followed by a description on the next line are merged into a single, compact list item.
- Works with or without a leading bullet and even if a blank line exists between the title and description.
- Example:

```
**Heat Therapy**
Provides gentle warmth to relax and soothe.

* **Massage Function**
Mimics professional massage techniques for relief.
```

Renders as:

• Heat Therapy Provides gentle warmth to relax and soothe.
• Massage Function Mimics professional massage techniques for relief.

### Admin bulk actions (Pages list)

- **Re-clean selected**
  - Re-runs the cleaner on the already stored text.
  - No network calls. Fast and safe.
  - Use after changing cleaning rules or to normalize spacing/boilerplate removal.

- **Refetch & re-clean selected**
  - Downloads the page again, then applies the cleaner.
  - Updates `cleaned_text` and may update `meta.title`.
  - Slower; obeys HTTP guardrails (2xx only, HTML/XHTML content types, size limits, redirects followed).
  - Persists fetch metadata on success or failure (status, length, error snippet, fetched timestamp).

 Tip: Click the help icon (“?”) in the header to open a modal summarizing these options. Tooltips are available on hover.
 Notifications show error counts and use danger styling if any errors occurred.

### CLI utilities

- Re-clean existing records (no refetch):
  
  ```bash
  php artisan pages:reclean
  ```
  
 - Refetch from the network and re-clean:
  
  ```bash
  php artisan pages:reclean --refetch
  ```
  - Records fetch metadata on success and failure (`last_fetched_at`, `http_status`, `content_length`, `fetch_error`).

### Exporting generated contents (SQL)

- Use mysqldump to avoid GUI truncation when exporting long `LONGTEXT` values:

```bash
mysqldump -u <user> -p --default-character-set=utf8mb4 --skip-extended-insert --no-tablespaces <database> generated_contents > generated_contents-full.sql
```

- If using HeidiSQL, use “Export database as SQL” and ensure no text-length limits are enabled. Avoid copying rows from the grid, which may show truncated previews.
### Pages list tips

- Use the `HTTP` badge color to quickly spot failures (red for non-2xx, gray for unknown).
- Apply the "Fetch failed" filter to target rows with recorded errors or non-2xx statuses.
 - Hover the `HTTP` badge to view details (status, bytes, fetched time, or the error message when failed).

## Cleaning rules

- Remove entire nodes likely to be chrome/noise in DOM stage: `script`, `style`, `noscript`, `template`, `svg`, `iframe`, `form`, and common layout containers (`header`, `nav`, `aside`, `footer`, `.header`, `.nav`, `.navbar`, `.menu`, `.sidebar`, `.breadcrumb`, `.footer`, `.subscribe`, `.newsletter`, `.cookie`, `.banner`, ARIA roles banner/navigation/contentinfo).
- Drop CTA-like nodes during DOM pass for `a`, `button`, submit inputs, and `.btn` whose text matches: `order now`, `buy now`, `add to cart`, `checkout`, `discount`, `coupon`, `shipping`, `free shipping`, `limited time`, `save <n>%`.
- Text pipeline:
  - Strip any remaining `<script>...</script>` blocks.
  - Convert `<br>` to newlines; add newlines after closing block tags (`p`, `div`, `li`, `section`, `article`, `h1–h6`, `tr`).
  - `strip_tags`, decode HTML entities, normalize NBSP to space.
  - Remove all tabs; collapse multiple spaces; ensure at most one blank line between paragraphs.
  - Trim trailing spaces per line.
- Phrase/line removals in `removeCtaPhrases()`:
  - Menus/section labels: `overview`, `features`, `reviews`, `faq(s)`, `frequently asked questions`, “as seen on”, “viral on TikTok”.
  - Offers/promotions: `offer|deal|exclusive offer|early bird|promotion|promo|sale|today only|limited time`, percentage-off patterns, and `buy/order/add to cart/checkout` lines.
  - Ratings/reviews: lines like `4.8/5 12,345 verified reviews`, `(12,345 verified reviews)`, `verified buyer`.
  - Shipping/stock/logistics: `ship/shipping/stock level/low stock/backorder/dispatch/deliver/arrives/USPS/FedEx/UPS/DHL/tracking/warehouse`.
  - Guarantees: `money-back guarantee`, `30-day money back guarantee` (with variants).
  - Dates: ISO `YYYY-MM-DD` and common month-name formats.
  - Emojis/symbols: remove arrows, stars, checkmarks, pictographs.
  - Separator-only and punctuation-only lines.


## Tests & Quality

- Run tests: `php artisan test`
- Code style: `./vendor/bin/pint`
 - Unit-only: `vendor/bin/phpunit --testsuite Unit` (tests cover `toText()` and `removeCtaPhrases()` behaviors)

## Project Decisions

- Use Filament 4 Schemas for forms/infolists for forward-compatibility.
- Prefer source-only extraction to avoid JS/UX hiding content.
- Preserve newlines, remove tabs, and collapse multiple spaces; avoid over-trimming text.
- Preserve FAQ `Q:`/`A:` lines extracted from accordion UI.
- CTA phrase removal using conservative regexes to minimize false positives.

## Roadmap

- Optional headless rendering (e.g., Browsershot) fallback for JS-heavy sites.
- AI generation flows for interstitial/advertorial content.
- Export tools (CSV/XLSX) and bulk actions.
- Per-site rules/overrides for extraction and cleaning.

## Screenshots

> Add screenshots or a short GIF of the extraction flow and the Pages view here.

## Security

If you discover a security issue, please avoid filing a public issue. Contact the maintainer privately.

## License

MIT © 2025
