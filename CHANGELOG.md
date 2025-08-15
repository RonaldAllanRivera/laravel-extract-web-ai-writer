# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

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
