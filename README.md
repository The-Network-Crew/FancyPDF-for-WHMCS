# FancyPDF

**Development preview v0.4.4-dev** — independent GPL-3.0-or-later invoice and quote PDF addon for **self-hosted WHMCS 8.13** and **PHP 8.3**.

This is a first implementation, **under active WHMCS integration testing**. Use a staging WHMCS instance first. Do not deploy to production or email real invoices until the PDF output and restore cycle have been validated.

## Design

- WHMCS stays responsible for invoice, quote, tax, payments, formatting and the TCPDF object.
- A single small renderer handles invoice and quote PDFs, with configurable branding and remittance text.
- WHMCS native addon configuration fields store options in `tbladdonmodules`.
- The admin page installs four tiny theme entry points (`invoicepdf.tpl`, `quotepdf.tpl`, `invoicepdffooter.tpl`, `quotepdffooter.tpl`).
- Original templates are retained **inside the `mod_fancypdf_installs` database table**, not public directories.
- On reinstall, FancyPDF only replaces its own unmodified entry points; it refuses to overwrite external changes.
- Restore removes installed entry points and reinstates original content, including the absence of a file.
- Deactivation refuses while installed templates remain. Restore first.

## Install in staging

1. Back up the WHMCS database and the selected theme directory independently.
2. Copy `modules/addons/fancypdf` from this package into `WHMCS_ROOT/modules/addons/fancypdf`.
3. In **System Settings > Addon Modules**, activate FancyPDF and configure administrator access.
4. Open **Addons > FancyPDF**. Choose the *System Theme* used for PDF generation and select **Install/Update Template Overrides**.
5. In **System Settings > Addon Modules > FancyPDF > Configure**, set brand colours (`#RRGGBB`), logo relative path, payment details and footer.
6. Test an invoice and quote PDF from admin, client area and attached-to-email workflows. Test multi-page output, taxes, credits, payment statuses, special characters and logo images.
7. To roll back, return to **Addons > FancyPDF**, select each installed theme and click **Restore original Templates**. Confirm results, then deactivate.

Important: if you are currently running CleanPDF, **do not deactivate it prematurely**. FancyPDF can back up CleanPDF's readable entry-point files, but restoration will restore those files *verbatim*. Keep CleanPDF's complete files and your separate backups until cutover is verified. Run the cutover in staging first.

## Scope today

- Invoice and quote PDFs: yes (initial implementation)
- Primary and accent colours, company logo, logo width, payment text, closing text: yes
- WHMCS-provided line items, totals, tax and client details: initial pass
- Invoice transaction history: initial pass, relies on upstream `$transactions` variable
- Multi-page TCPDF content with repeating three-column footer: initial implementation, requires integration testing
- Safe installation/restore: implementation present, needs real-world integration testing
- A4/Letter selection: configuration note only; paper size remains controlled by WHMCS
- RTL, custom fonts, envelope layouts, custom watermarks: out of v0.1 scope

## Compatibility and test caveats

WHMCS provides its PDF templates with PHP scope variables. Field names differ in invoice and quote code paths; check generated PDFs carefully against your own 8.13 installation. The renderer does not recalculate monetary values. Theme-specific PDF header/footer files may still run in WHMCS; check for overlap with the rendered page.

The native WHMCS admin CSRF helper `check_token('WHMCS.admin.default')` is required before installing/restoring files. Test this validation in your 8.13 build before rollout.

The requested paper-size setting is included for visibility but does **not** reconfigure TCPDF because WHMCS constructs the PDF before loading its template; adjust WHMCS' PDF paper size separately if needed. The module's inline description is explicit about this.

## Source layout

```
modules/addons/fancypdf/
  fancypdf.php             # Addon registration, native config and admin UI
  src/Installer.php        # Theme discovery, managed installs and restoration
  src/Render.php           # Rendering with existing WHMCS TCPDF object
  entrypoints/invoicepdf.tpl
  entrypoints/quotepdf.tpl
  entrypoints/invoicepdffooter.tpl
  entrypoints/quotepdffooter.tpl
```

## Licence

FancyPDF code is GPL-3.0-or-later. This independently written package does not bundle any CleanPDF code or proprietary WHMCS template files. See LICENSE.

## Changes in v0.3.1-dev

- Editable titles for the three footer columns; the left column body remains WHMCS company/address data.
- Quote heading switch between QUOTE and PROPOSAL.
- Line Total headings on both document types; Unit Price on quotes.
- Quote taxable quantities have a `*` marker and legend; quote line discounts show percent suffixes.
- Tax rows use WHMCS-provided tax names, rates and formatted amounts; tax is not independently calculated.
- Footer divider uses accent colour and matches the top divider thickness (0.7 mm).

**Test note:** Staging tests should include invoices and quotes with each supported tax level, mixed taxable/non-taxable quote lines, and nonzero discounts. The renderer has been PHP-linted but not executed inside WHMCS in this build.


### v0.3.1-dev tidy-up

- Invoice items now use the same `*` taxable marker as quotes, based on the WHMCS `taxed`/`taxable` flag.
- The taxable-item legend is rendered as a right-aligned row below the totals, rather than between line items and totals.
- Invoice tax rows continue to use WHMCS native `taxname`, `taxrate`, and `tax` values (and second-level equivalents); quote tax rows use `taxlevel1`, `taxlevel2`, `tax1`, and `tax2`. FancyPDF does not calculate taxes.
- Footer text and annotation sizes increased from 7.5pt to 8pt; body 9pt, section 10pt, main heading 19pt with overflow reduction, logo fallback 17pt.
- **Validation:** PDFs containing actual nonzero tax should be tested in WHMCS. A taxable flag by itself does not guarantee a configured or charged tax.


## v0.4.0-dev (Design A)
- Compact side-by-side billing and metadata sections; full-width item tables.
- Brand-primary highlight on the final totals row (invoice Balance / quote Total).
- Neutral light-grey table headers, thin accent-coloured top and bottom rules.
- Bundled local SVG icons for dates, due dates, payment and footer headings.
- `show_icons` addon option (`on`/`off`), default enabled.
- No external requests, JavaScript, icon fonts or image libraries.

**Upgrade note:** WHMCS may require reactivation for a newly added addon configuration field to appear; safeguard settings and installation records first. PDFs must be visually tested inside WHMCS 8.13, including long subjects and multi-page documents.

## v0.4.1-dev layout fixes
- Removed the additional accent rules at the absolute page top and bottom. Kept the header divider and footer divider.
- Added a local accent-coloured `status.svg` for invoice status when icons are enabled.
- Reserve header space based on the greater of rendered address-height estimate and metadata rows, then set both X and Y before subsequent document content. This prevents proposal introductory text from overlapping long addresses.
- Slightly darkened the line-item header background to `#E3E8EC`.

**Staging validation:** check address blocks with many lines and wrapping, long proposal introductions, icon-off rendering, and multi-page invoices/quotes on WHMCS 8.13.

## v0.4.2-dev housekeeping

- Addon author: The Network Crew Pty Ltd; admin UI labels and navigation instructions revised.
- The installation screen compares the SHA-256 hash of each currently installed theme template with the hash recorded in `mod_fancypdf_installs`. **Verified override (SHA-256 match)** means only that the file's contents have not changed since FancyPDF last installed it. If WHMCS replaces or removes a managed template, the screen reports a mismatch; updates that do not touch the template will not change this status. Compatibility of the PDF rendering behaviour after an upgrade is **not** determined by this check.
- Footer centre and right text fields support explicit `http://` and `https://` URLs as clickable PDF links. All other text is HTML-escaped; arbitrary HTML is not supported. Footer text remains subject to available fixed-height space.
- No PDF layout or entry-point changes in this release; existing installations do not require entry-point reinstallation.

## v0.4.3-dev transaction table consistency

- Transactions header now uses the same configured primary-colour text and light-grey background as the line-item table.
- Matched padding, subtle row separators and right-aligned amounts.
- Styled Transactions section heading consistently, with no changes to payment or tax calculations.

## v0.4.4-dev transaction table heading

- Removed the standalone Transactions section heading.
- Renamed the transaction table Description heading to Transaction ID/Reference.
- Kept the existing transaction rows, layout, and styling unchanged.
