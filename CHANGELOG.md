# Changelog

Notable changes to **FancyPDF for WHMCS** are recorded here. Versions below document the **16 October 2026 development series**, leading to `v0.4.4-dev`. This is pre-release software; no production-stability or broader compatibility claim is implied.

The release history reflects the development milestones and available packages, with later refinements incorporated into the final `v0.4.4-dev` source. Some intermediate archives were created under reused development version labels; this changelog uses the agreed final progression and groups related fixes together.

## [0.4.5-dev] - 2026-10-16

### Added
- Passive update check when opening FancyPDF administration, comparing the addon version with the plain-text GitHub `version` file.
- Show the installed version, remote version, and a link to GitHub when an update is available; report connection failures without interrupting administration.

### Changed
- Use `fancypdf_version()` as the single local PHP version source; metadata and update checks use numeric `x.y.z` values.
- Update the repository root `version` file to `0.4.5`.

## [0.4.4-dev] — 2026-10-16

### Added
- Added a clickable **Click to view online:** invoice URL below the document heading, using WHMCS's configured System URL and internal invoice ID; normal WHMCS access controls remain in effect.
- Added concise PHP source comments explaining the installer, configuration, rendering, tax, footer and transaction paths.

### Changed
- Consolidated minor transaction-table wording and formatting refinements from the development series, retaining the user's latest source adjustments.
- Hid the invoice **Credit** row when its formatted amount represents zero (the check currently assumes a dot-decimal monetary format).
- Expanded the project README for public-release preparation and introduced this changelog.

## [0.4.3-dev] — 2026-10-16

### Changed
- Styled the invoice transactions table consistently with the line-items table: background colour `#E3E8EC`, primary-colour bold headings, matching padding, subtle row separators and right-aligned amounts.
- Removed the unnecessary standalone **Transactions** section heading in later iteration.
- Clarified transaction column labels; the final supplied source uses **Date of TXN**, **Transaction Reference**, and **Value of TXN**.

## [0.4.2-dev] — 2026-10-16

### Changed
- Updated addon attribution to **The Network Crew Pty Ltd**, administration labels, configuration guidance and installer warning wording.
- Removed the duplicate addon title in the admin page.
- Clarified the **Verified Override (SHA-256 match)** status: it only establishes that the installed template has not changed, not that it remains compatible after a WHMCS update.

### Added
- Automatic clickable HTTP(S) links in the configurable centre and right footer fields, with plain text escaped and arbitrary HTML excluded.

## [0.4.1-dev] — 2026-10-16

### Fixed
- Removed unintended extra rules at the page's outer top and bottom while retaining the intended header/footer dividers.
- Added the missing invoice **Status** icon.
- Prevented proposal introductory text from overlapping customer addresses by placing following content below the taller of the customer-address and document-metadata blocks. The same positioning rule applies to invoices.

### Changed
- Darkened the line-item table header slightly to `#E3E8EC`.

## [0.4.0-dev] — 2026-10-16

### Added
- Implemented the **Design A** direction: compact, full-width item tables, cleaner two-column customer/metadata layout and restrained branding.
- Introduced a **primary-colour** highlight for the final amount/balance row.
- Bundled small local SVG icons for dates, payment and footer headings; introduced a single `show_icons` on/off setting.

### Changed
- Moved to softer neutral table-header styling and retained accent-coloured section dividers.
- Kept assets local, with no runtime JavaScript, CDN or external image services.

## [0.3.1-dev] — 2026-10-16

### Fixed
- Added taxable markers to invoice descriptions as well as quote quantities.
- Moved the taxable-item legend below the totals breakdown, aligned to the right to preserve line-item space.

### Changed
- Normalised explanatory-note/footer typography to 8 pt; retained the compact body and heading hierarchy.
- Clarified native WHMCS invoice versus quote tax-variable handling without recalculating amounts.

## [0.3.0-dev] — 2026-10-16

### Added
- Configurable **QUOTE / PROPOSAL** document heading.
- Configurable titles for all three footer columns; centre and right columns also have editable content.
- Taxable quote quantity markers, percentage suffixes on quote discounts and corresponding tax note.

### Changed
- Quote headings changed to **Unit Price** and **Line Total**; invoice amount heading changed to **Line Total**.
- Kept WHMCS-provided tax rows in the totals summary and made the footer accent divider match the header divider weight.

## [0.2.0-dev] — 2026-10-16

### Added
- Configurable **INVOICE / TAX INVOICE** title.
- Repeating three-column footer using WHMCS's PDF footer templates, moving company details out of the document body.
- Editable centre and right footer content, with fallback to remittance/legacy closing text.

### Fixed
- Prevented the invoice heading and number from splitting awkwardly.
- Used the configured payment gateway display name rather than its internal slug where available.
- Moved quote subject beneath the quote number to accommodate longer subjects.

## [0.1.0-dev] — 2026-10-16

### Added
- Initial independent, GPL-3.0-or-later FancyPDF addon for WHMCS 8.13 / PHP 8.3 development testing.
- Shared TCPDF-based renderer for invoice and quote PDFs with colour, logo and payment-detail configuration.
- Native WHMCS addon settings and a per-theme override installer for invoice/quote PDF entry points.
- Database-backed original-template preservation, SHA-256 change detection and guarded restoration.

---

**Before a stable release:** verify multi-page pagination, unusual currencies and decimal formats, all supported tax configurations, footer overflow, transactional invoice emails, template restore paths and WHMCS/PHP compatibility claims in a controlled test environment.
