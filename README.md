# Laravel Extract Web AI Writer

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
- **Text cleaning**: Converts to plaintext with preserved paragraphs/line breaks and CTA phrase stripping.
- **Filament v4 Admin**: Tables for quick review; record view shows copyable, pre-wrapped text.
- **Ready for AI**: Data model and flow designed to plug prompts for interstitial/advertorial generation.

## Tech Stack

- Laravel 12, PHP 8.2+
- Livewire 3, Filament 4 (Panels, Tables, Schemas, Infolists)
- MySQL (Laragon), Guzzle (HTTP), Symfony DomCrawler + HTML5

## Architecture Overview

- `app/Services/ContentExtractor.php`
  - Fetches HTML via Guzzle
  - Limits to `<body>`, removes HTML/JS/UI chrome
  - Converts to plain text and applies `removeCtaPhrases()`
- `app/Filament/Pages/ExtractContent.php`
  - Simple form to submit a URL; creates a `Page` record
- `app/Filament/Resources/PageResource.php`
  - List view with row click -> View page
  - Infolist shows URL, meta, and copyable `cleaned_text`
- `app/Models/Page.php` with casts and fillables

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

## Tests & Quality

- Run tests: `php artisan test`
- Code style: `./vendor/bin/pint`

## Project Decisions

- Use Filament 4 Schemas for forms/infolists for forward-compatibility.
- Prefer source-only extraction to avoid JS/UX hiding content.
- Preserve newlines, collapse only spaces/tabs; avoid over-trimming text.
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
