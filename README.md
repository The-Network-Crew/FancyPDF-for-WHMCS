# FancyPDF for WHMCS

**Clean, custom-branded invoice and quote PDFs — without unnecessary complexity.**

<img src="_LOGO-Wide.png">

FancyPDF is a free, open-source addon that gives WHMCS invoices and quotes a more polished, compact appearance. It uses the **TCPDF engine already included with WHMCS** and retains WHMCS as the source of billing, tax and payment data.

No ionCube, external fonts, CDN assets, JavaScript or commercial licence checks are required to render a PDF.

> [!IMPORTANT]
> **Development release · v0.#.#-dev**  
> Currently targeted at **WHMCS 8.13 LTS and PHP 8.3**. Other versions have not yet been verified. Test in staging, including emailed PDF attachments, before using FancyPDF for customer-facing billing.

## Features

- **Your branding:** primary and accent colours, optional PNG/JPG logo override and adjustable logo width.
- **Invoices and quotes:** configurable *Invoice / Tax Invoice* and *Quote / Proposal* headings.
- **Space-efficient layouts:** full-width item tables, clear totals and tax indicators, and an optional online invoice link.
- **Payment visibility:** WHMCS-provided tax, credits, balances, payments and invoice transaction history.
- **Customisable footer:** three repeating columns, editable headings, WHMCS company details and clickable HTTP(S) links.
- **Local icons:** bundled SVGs, enabled or disabled with one setting.
- **Reversible theme integration:** per-theme template overrides, stored originals and SHA-256 modification checks.
- **Simple update checks:** compares the installed module version with the project's GitHub `version` file; never installs updates automatically.

## Requirements

| Requirement | Details |
| --- | --- |
| WHMCS | Development target: **8.13 LTS** |
| PHP | Development target: **8.3** |
| Access | WHMCS administrator access, addon permissions and a working database |
| Filesystem | Write access to the relevant `templates/<theme>/` directory |
| PDF engine | The standard WHMCS-supplied TCPDF instance |

**Known limitations**

- FancyPDF displays values supplied by WHMCS; it does **not** recalculate taxes, discounts, credits or balances.
- The paper-format setting is informational. WHMCS chooses the actual page size before FancyPDF begins rendering.
- Footer text should be kept reasonably short. Multi-page documents, long addresses, unusual characters and long titles still need testing.
- RTL and envelope layouts, custom font management and watermark editing are not included in this development release.

## Installation

1. **Back up** the WHMCS database and any customised PDF theme templates.
2. Copy `modules/addons/fancypdf/` into the matching directory in your WHMCS installation.
3. Go to **System Settings → Addon Modules**, activate **FancyPDF** and assign the appropriate administrator permissions.
4. Configure via **System Settings → Addon Modules → FancyPDF → Configure**.
5. Open **Addons → FancyPDF**, select the **System Theme**, and choose **Install/Update Template Overrides**.
6. Generate sample invoices and quotes/proposals. Check both admin/client downloads and PDF email attachments before going live.

**PDF template overrides / entry points are installed per-theme here.** Activation alone does not modify templates or WHMCS core files.

### Configuration

| Setting | What it controls |
| --- | --- |
| **Branding** | Primary/accent colours, optional logo override and logo width (mm) |
| **Document headings** | Invoice vs Tax Invoice; Quote vs Proposal |
| **Layout** | Show icons (`on` / `off`); informational paper-format setting |
| **Footer titles** | Independent headings for left, centre and right columns |
| **Footer content** | WHMCS company details on the left; custom text in the centre/right |
| **Payment details** | Invoice centre-column fallback if its content is blank |
| **Legacy closing text** | Right-column fallback if its content is blank |

Colours use `#RRGGBB`. The optional logo override is a **WHMCS-root-relative path** to a PNG/JPG image; by default, FancyPDF looks for `assets/img/logo.png` or `.jpg`.

Centre/right footer content supports plain text, new lines and explicit `https://` or `http://` URLs. Those URLs become clickable in the PDF. Arbitrary HTML is not supported. The left-column body always comes from WHMCS company settings; only its heading is configurable.

## Template overrides and safety

FancyPDF installs four small entry-point files into the selected theme:

```text
templates/<theme>/
├── invoicepdf.tpl
├── invoicepdffooter.tpl
├── quotepdf.tpl
└── quotepdffooter.tpl
```

Existing contents — or the fact that a file was previously absent — are recorded in the `mod_fancypdf_installs` database table so they can be restored later.

**FancyPDF will not silently overwrite unknown or subsequently modified template files.** The same protection applies to restoration. Database-backed originals complement, but do not replace, your own backups.

### What “Verified Override” means

The admin page compares the file on disk with the SHA-256 hash recorded at installation.

| Result | Meaning |
| --- | --- |
| **Verified Override (SHA-256 match)** | The installed file still matches FancyPDF's recorded copy. |
| **Changed / Missing (hash mismatch)** | The file has been edited, replaced or removed. |

A WHMCS or theme update that replaces an entry-point file should therefore be detected. **A verified hash does not establish compatibility** with the current WHMCS version or with other theme files. Recheck the overrides and regenerate representative PDFs after every WHMCS/theme update.

## Updating and removing

**Update FancyPDF:** Back up first, then replace `modules/addons/fancypdf/` with the newer release. Read [`CHANGELOG.md`](CHANGELOG.md) for configuration or template changes. In some WHMCS installations, newly added addon settings may require reactivation; preserve installation records and settings before attempting this.

**Restore original templates:** Open **Addons → FancyPDF**, select each theme with installed overrides, and choose **Restore original Templates**. Verify restoration **before** deactivating the addon. Deactivation is deliberately refused while installation records remain.

If restoration refuses a changed file, inspect it and compare it with your own backup. Do not force-delete it: the safeguard is intended to protect later customisations and WHMCS/theme changes.

**Migrating from CleanPDF?** Test on staging and retain a complete backup of that installation. FancyPDF can preserve readable entry-point contents, but restoring them does not reinstall or repair CleanPDF. FancyPDF does not incorporate its proprietary or encoded code.

### Update availability

The administration page fetches the plain-text `version` file from the project's GitHub `main` branch and compares it with `fancypdf_version()` in `fancypdf.php`. Versions use numeric `x.y.z` notation (the `-dev` suffix may appear in release filenames).

This is a **notification only**: it never downloads or executes an update. If GitHub is unreachable, the page reports that it cannot check and remains usable.

## Pre-release testing checklist

- [ ] Standard and long customer addresses; long invoice numbers and proposal subjects.
- [ ] Introductory and closing quote text, including multi-line content.
- [ ] Taxable/non-taxable items, discounts and one or two tax levels.
- [ ] Zero/non-zero credit, part-paid and fully paid invoices.
- [ ] Several transactions, including long references.
- [ ] Multi-page PDFs and repeating three-column footers.
- [ ] Logo, icon toggle, invoice links, footer links and permissions.
- [ ] Admin/client generation, PDF email attachments, template installation and restoration.

## Project structure

```text
README.md                       Project documentation
CHANGELOG.md                    Release history
LICENSE                         GNU GPL v3 or later
version                         Repository version marker
modules/addons/fancypdf/
├── fancypdf.php                Addon settings and administration
├── src/
│   ├── Installer.php           Safe template install and restoration
│   └── Render.php              Invoice, quote and footer rendering
├── entrypoints/               Four WHMCS PDF template overrides
└── assets/icons/              Bundled SVG assets
```

## Contributing

FancyPDF is maintained by **The Network Crew Pty Ltd**. Contributions should favour small, readable changes and the existing WHMCS/TCPDF integration rather than new runtime dependencies.

When reporting an issue, include your FancyPDF, WHMCS and PHP versions; the document type; reproduction steps; relevant errors; and a **redacted** PDF if useful. Never publish client billing details, credentials or sensitive links. Changes to shared rendering code should be tested against both invoices and quotes.

## Licence

FancyPDF is licensed under the **GNU General Public License v3.0 or later** ([`LICENSE`](LICENSE)). It is an independent project, not affiliated with or endorsed by WHMCS or the CleanPDF publisher. All respective trademarks remain the property of their owners.

See [`CHANGELOG.md`](CHANGELOG.md) for release history.
