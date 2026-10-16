# FancyPDF for WHMCS

**Open-source, custom-branded invoice and quote PDFs for WHMCS.**

FancyPDF replaces the standard WHMCS invoice and quote PDF presentation with a clean, compact design while retaining WHMCS as the source of invoice data, taxes, payment information and PDF generation. It uses the TCPDF instance provided by WHMCS and does not load external scripts, fonts, icon services or images.

> **Release status:** `v0.4.4-dev` is a development release, not a production-certified release. The current development target is **WHMCS 8.13 LTS with PHP 8.3**. Compatibility with other WHMCS/PHP versions has not yet been verified. Test on staging and validate your own documents before enabling customer delivery.

## Features

- Custom primary and accent colours; optional PNG/JPG logo override and adjustable logo width.
- Invoice heading: **INVOICE** or **TAX INVOICE**; quote heading: **QUOTE** or **PROPOSAL**.
- Compact, full-width line-item tables with quantity, unit price, discount and line totals where applicable.
- WHMCS-provided tax lines, credit, payments and balances; taxable-item markers and explanatory note.
- Invoice payment history table when transaction data is supplied by WHMCS.
- Clickable online invoice link below the invoice title, subject to normal WHMCS access controls.
- Repeating three-column footer: WHMCS company details on the left and configurable centre/right text, titles and explicit HTTP(S) hyperlinks.
- Small bundled SVG icons, switched on or off through one addon setting.
- Per-theme PDF template overrides with database-backed original copies and SHA-256 change detection.
- PHP source available for inspection and modification; no ionCube or commercial licence checks.

## Requirements and limitations

- A **self-hosted WHMCS installation** with administrator permissions to activate addon modules and install PDF template overrides.
- Tested development target: **WHMCS 8.13 / PHP 8.3**; other versions are not claimed to work yet.
- Write permissions on the selected `templates/<theme>/` directory, and a functional WHMCS database.
- WHMCS must be able to render its standard invoice and quote PDFs; FancyPDF uses its existing TCPDF object.
- The `Layout — paper format` setting is informational: the actual PDF paper size is determined when WHMCS creates the PDF, before FancyPDF renders it.
- FancyPDF does **not** calculate tax, discounts, credits or balances. The document shows the values provided by WHMCS; verify tax configuration and samples in your installation.
- Fixed-height footer columns may need concise text to avoid overflowing into other page content. Multi-page documents, long addresses, long titles and unusual characters require staging verification.
- RTL layouts, envelope layouts, custom font management and watermark editing are not part of this development release.

## Installation

1. **Back up your WHMCS database and theme templates** separately. Retain the backups until installation and rollback have been tested.
2. Copy `modules/addons/fancypdf/` into your WHMCS installation at the same relative path.
3. Open **System Settings → Addon Modules**, activate **FancyPDF**, and grant access to the appropriate administrator roles.
4. Configure via **System Settings → Addon Modules → FancyPDF → Configure**.
5. Open **Addons → FancyPDF**. Choose the **System Theme** used for the PDFs and select **Install/Update Template Overrides**.
6. Generate test invoices and proposals/quotes from administrator and client views, and verify PDF email attachments before sending any production documents.

PDF template overrides / entry points are installed **per theme**. FancyPDF does not patch WHMCS core files and does not silently install templates during activation.

### Configuration overview

| Group | Settings / behaviour |
| --- | --- |
| Branding | Primary colour, accent colour, PNG/JPG logo override, logo width in millimetres |
| Layout | Show icons (`on`/`off`); paper-format informational setting |
| Document names | Invoice or Tax Invoice; Quote or Proposal |
| Footer headings | Independently configurable left, centre and right titles |
| Footer content | Left: WHMCS company information; centre/right: configurable text |
| Payment | Invoice centre footer falls back to the payment/remittance field when its own content is empty |
| Legacy footer | Right footer falls back to the legacy closing-text field when its own content is empty |

Branding colours are entered as `#RRGGBB`. The optional logo override is a path **relative to the WHMCS root** and accepts PNG/JPG files. If omitted, FancyPDF looks for `assets/img/logo.png` or `.jpg`.

Footer centre/right fields accept plain text, line breaks and explicit `http://` or `https://` links. The recognised URLs become clickable in generated PDFs; arbitrary HTML is not supported. Company information on the left comes from WHMCS system settings; only its heading is editable.

### What the installer changes

FancyPDF installs these **four** files in the selected theme:

```text
templates/<theme>/invoicepdf.tpl
templates/<theme>/quotepdf.tpl
templates/<theme>/invoicepdffooter.tpl
templates/<theme>/quotepdffooter.tpl
```

The installed files are small entry points to the addon renderer; they are not WHMCS core changes. The original file contents (or the fact that a file did not exist) are recorded in the `mod_fancypdf_installs` database table for restoration.

**Safety policy:** Installation refuses to overwrite an unknown FancyPDF entry point or a previously installed template that has subsequently changed. Restoration likewise refuses to overwrite modified files. Independently back up existing custom templates before installation; the database-backed records are not a substitute for normal backups.

### Installation status and WHMCS upgrades

An entry labelled **Verified Override (SHA-256 match)** means its current bytes match the hash FancyPDF stored when it installed the file. A file removed or replaced by a WHMCS/theme update will normally show **Changed / Missing (hash mismatch)**.

That status **does not certify compatibility** with a newer WHMCS PDF engine, other template files or a changed theme. Check all installed overrides after WHMCS/theme upgrades and regenerate representative PDFs. Never use the status label alone as proof that invoices remain correct.

## Verification checklist

Before using customer-facing PDFs, verify:

- [ ] Invoice and quote/proposal with normal and long customer addresses.
- [ ] Long invoice numbers, proposal subjects and introductory/closing text.
- [ ] Taxable and non-taxable line items, discounts, one or two tax levels and non-zero GST/tax.
- [ ] Zero and non-zero credits, partial payments, unpaid and fully paid invoices.
- [ ] Invoices with several transactions and long payment references.
- [ ] Multiple-page documents and repeating footers; logo and SVG icons on/off.
- [ ] Online invoice links, footer links, client permissions and emailed PDF attachments.
- [ ] Install/update and restore operations on your chosen theme, including modification detection.

## Updating and removing FancyPDF

**Updating the addon files:** Back up first, then replace `modules/addons/fancypdf/` with the new package. Check the changelog for new configuration fields or entry-point changes. WHMCS may not expose newly introduced addon configuration fields until a module reactivation in some installations; exercise care because installation records and existing settings must be preserved.

**Removing:** Open **Addons → FancyPDF**, select each theme with installed overrides and click **Restore original Templates**. Confirm restoration before deactivating the addon. The module deliberately refuses deactivation when installed template records remain.

If a restore is refused because a file has changed, do **not** blindly delete or overwrite it. Inspect the file and compare with your own backups; the refusal protects changes made after FancyPDF's installation.

### Existing CleanPDF installations

Test the migration on staging. FancyPDF can record the original readable PDF entry points, but restoring them does **not** reinstall or repair another module. Keep the complete CleanPDF installation and separate backups until your cutover is verified. No CleanPDF proprietary/encoded code is part of FancyPDF.

## Project structure

```text
README.md                       Project and installation guide
CHANGELOG.md                    Release history
LICENSE                         GPL-3.0-or-later
modules/addons/fancypdf/
  fancypdf.php                  Addon configuration and admin page
  src/Installer.php             Template install, checks and restoration
  src/Render.php                Shared invoice, quote and footer rendering
  entrypoints/                  Four WHMCS PDF template entry points
  assets/icons/                 Local SVG assets
```

## Contributing

FancyPDF is an independent open-source project maintained by **The Network Crew Pty Ltd**. Bug reports and patches should include the FancyPDF version, WHMCS/PHP versions, document type, reproduction steps, relevant error messages and a **redacted** example PDF where helpful. Never publish real customer addresses, invoice links with sensitive tokens, credentials or other private billing information.

Keep contributions compatible with the existing WHMCS TCPDF workflow, prefer small readable changes, avoid runtime external services and document any new setting or migration requirement. Please test both invoice and quote paths whenever shared rendering code changes.

## Licence

Licensed under **GNU GPL-3.0-or-later**; see [`LICENSE`](LICENSE). FancyPDF is independently developed and is not affiliated with or endorsed by WHMCS or the former CleanPDF publisher. WHMCS and other trademarks belong to their respective owners.

For release-by-release details, see [`CHANGELOG.md`](CHANGELOG.md).
