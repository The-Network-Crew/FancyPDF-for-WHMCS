<?php
/** FancyPDF — GPL-3.0-or-later. Executed only within WHMCS PDF template scope. */
if (!defined('WHMCS')) { exit('Direct access denied'); }

use WHMCS\Database\Capsule;

function fancypdf_escape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fancypdf_setting(array $options, string $name, string $default = ''): string
{
    return (string) ($options[$name] ?? $default);
}

function fancypdf_colour(string $value, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? strtoupper($value) : $fallback;
}

function fancypdf_options(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = [];
    foreach (Capsule::table('tbladdonmodules')->where('module', 'fancypdf')->get(['setting', 'value']) as $row) {
        $cache[(string) $row->setting] = (string) $row->value;
    }
    return $cache;
}

function fancypdf_logo(array $options): ?string
{
    $custom = trim(fancypdf_setting($options, 'logo_path'));
    $candidates = $custom !== '' ? [$custom] : ['assets/img/logo.png', 'assets/img/logo.jpg', 'assets/img/logo.jpeg'];
    foreach ($candidates as $candidate) {
        $candidate = str_replace('\\', '/', $candidate);
        if (str_starts_with($candidate, '/') || str_contains($candidate, '..') || !preg_match('~^[a-zA-Z0-9_./ -]+\.(?:png|jpe?g)$~iD', $candidate)) { continue; }
        $file = realpath(ROOTDIR . '/' . $candidate);
        $root = realpath(ROOTDIR);
        if ($file && $root && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) { return $file; }
    }
    return null;
}

function fancypdf_address(array $client): string
{
    $lines = [];
    if (!empty($client['companyname'])) { $lines[] = $client['companyname']; }
    $person = trim((string) ($client['firstname'] ?? '') . ' ' . (string) ($client['lastname'] ?? ''));
    if ($person !== '') { $lines[] = $person; }
    foreach (['address1', 'address2'] as $field) { if (!empty($client[$field])) { $lines[] = $client[$field]; } }
    $city = implode(' ', array_filter([(string) ($client['city'] ?? ''), (string) ($client['state'] ?? ''), (string) ($client['postcode'] ?? '')]));
    if ($city !== '') { $lines[] = $city; }
    if (!empty($client['country'])) { $lines[] = $client['country']; }
    return implode('<br>', array_map('fancypdf_escape', $lines));
}

function fancypdf_lines(mixed $value): string
{
    if (is_array($value)) { $value = implode("\n", $value); }
    return nl2br(fancypdf_escape($value), false);
}

function fancypdf_label(array $vars, string $key, string $fallback): string
{
    $lang = $vars['_LANG'] ?? [];
    return fancypdf_escape($lang[$key] ?? $fallback);
}

function fancypdf_payment_name(array $vars): string
{
    $raw = trim((string) ($vars['paymentmethod'] ?? ''));
    if ($raw === '') { return ''; }
    // In PDF context WHMCS may provide a gateway key instead of the display name.
    // Use the configured title, preserving intentional capitalisation (for example BPAY).
    $name = Capsule::table('tblpaymentgateways')
        ->where('gateway', $raw)->where('setting', 'name')->value('value');
    return is_string($name) && trim($name) !== '' ? $name : $raw;
}

function fancypdf_footer_column(string $title, string $content): string
{
    $title = trim($title);
    $heading = $title !== '' ? '<b>' . fancypdf_escape($title) . '</b><br>' : '';
    return $heading . fancypdf_lines($content);
}

function fancypdf_tax_label(string $name, mixed $rate): string
{
    $name = trim($name) !== '' ? trim($name) : 'Tax';
    $percentage = trim((string) ($rate ?? ''));
    return $percentage === '' ? $name : $name . ' (' . rtrim($percentage, '%') . '%)';
}

function fancypdf_taxable(mixed $value): bool
{
    if (is_bool($value)) { return $value; }
    return in_array(strtolower(trim((string) ($value ?? ''))), ['1', 'yes', 'true', 'on'], true);
}

function fancypdf_footer(string $type, array $vars): void
{
    $pdf = $vars['pdf'] ?? null;
    if (!is_object($pdf)) { return; }
    $options = fancypdf_options();
    $font = (string) ($vars['pdfFont'] ?? 'helvetica');
    $accent = fancypdf_colour(fancypdf_setting($options, 'accent_colour', '#D18A42'), '#D18A42');
    $icons = fancypdf_setting($options, 'show_icons', 'on') !== 'off';
    $company = trim((string) ($vars['companyname'] ?? ''));
    $companyAddress = $vars['companyaddress'] ?? [];
    if (!is_array($companyAddress)) { $companyAddress = [$companyAddress]; }
    $left = trim($company . "\n" . implode("\n", array_map('strval', $companyAddress)));
    $centre = trim(fancypdf_setting($options, 'footer_centre'));
    if ($centre === '' && $type === 'invoice') {
        $centre = trim(fancypdf_setting($options, 'payment_details'));
    }
    $right = trim(fancypdf_setting($options, 'footer_right'));
    if ($right === '') { $right = trim(fancypdf_setting($options, 'footer_text', 'Thank you for your business.')); }

    // Fixed position on each page; renderer reserves the bottom 49 mm.
    $pdf->SetAutoPageBreak(false, 0);
    $bottom = (float) $pdf->getPageHeight();
    $pdf->SetDrawColor(...fancypdf_rgb($accent));
    $pdf->SetLineWidth(0.7);
    $pdf->Line(16, $bottom - 44, (float) $pdf->getPageWidth() - 16, $bottom - 44);
    $pdf->SetFont($font, '', 8);
    $pdf->SetTextColor(75, 85, 96);
    $width = ((float) $pdf->getPageWidth() - 32) / 3;
    $columns = [
        [fancypdf_setting($options, 'footer_left_title', 'FROM'), $left, 'building.svg'],
        [fancypdf_setting($options, 'footer_centre_title'), $centre, 'credit-card.svg'],
        [fancypdf_setting($options, 'footer_right_title'), $right, 'headset.svg'],
    ];
    foreach ($columns as $index => [$title, $content, $iconFile]) {
        $x = 16 + $index * $width;
        $headingY = $bottom - 40.5;
        $title = trim($title);
        if ($title !== '') {
            $titleX = $x;
            if ($icons) {
                $icon = __DIR__ . '/../assets/icons/' . $iconFile;
                if (is_file($icon)) {
                    // TCPDF accepts SVG source prefixed by @. Local files only; no HTTP requests.
                    $svg = str_replace('#ACCENT#', $accent, file_get_contents($icon));
                    try { $pdf->ImageSVG('@' . $svg, $x, $headingY + .2, 3.6, 3.6); $titleX += 5.0; }
                    catch (\Throwable $e) { /* An icon cannot prevent PDF generation. */ }
                }
            }
            $pdf->SetFont($font, 'B', 8);
            $pdf->SetTextColor(...fancypdf_rgb(fancypdf_colour(fancypdf_setting($options, 'primary_colour', '#20354A'), '#20354A')));
            $pdf->SetXY($titleX, $headingY);
            $pdf->Cell($width - ($titleX - $x) - 3, 5, $title, 0, 0, 'L');
        }
        $pdf->SetTextColor(75, 85, 96);
        $pdf->SetFont($font, '', 8);
        $pdf->writeHTMLCell($width - 3, 33, $x, $bottom - ($title === '' ? 40 : 35), fancypdf_lines($content), 0, 0, false, true, '', true);
    }
    $pdf->SetAutoPageBreak(true, 49);
}

function fancypdf_render(string $type, array $vars): void
{
    if (!in_array($type, ['invoice', 'quote'], true)) { throw new \InvalidArgumentException('Unknown PDF type'); }
    $pdf = $vars['pdf'] ?? null;
    if (!is_object($pdf) || !method_exists($pdf, 'writeHTML')) { throw new \RuntimeException('WHMCS PDF renderer unavailable'); }
    $options = fancypdf_options();
    $primary = fancypdf_colour(fancypdf_setting($options, 'primary_colour', '#20354A'), '#20354A');
    $accent = fancypdf_colour(fancypdf_setting($options, 'accent_colour', '#D18A42'), '#D18A42');
    $font = (string) ($vars['pdfFont'] ?? 'helvetica');
    $icons = fancypdf_setting($options, 'show_icons', 'on') !== 'off';
    $client = is_array($vars['clientsdetails'] ?? null) ? $vars['clientsdetails'] : [];
    $companyAddress = fancypdf_lines($vars['companyaddress'] ?? []);
    $name = fancypdf_escape($vars['companyname'] ?? '');
    $reference = $type === 'invoice'
        ? (($vars['invoicenum'] ?? '') ?: ($vars['invoiceid'] ?? ''))
        : ($vars['quotenumber'] ?? '');
    $title = $type === 'invoice'
        ? (fancypdf_setting($options, 'invoice_title', 'INVOICE') === 'TAX INVOICE' ? 'TAX INVOICE' : 'INVOICE')
        : (fancypdf_setting($options, 'quote_title', 'QUOTE') === 'PROPOSAL' ? 'PROPOSAL' : 'QUOTE');
    $logo = fancypdf_logo($options);
    $width = (float) fancypdf_setting($options, 'logo_width', '52');
    $width = max(15, min(80, $width));
    $pdf->SetFont($font, '', 9);
    $pdf->SetTextColor(36, 50, 65);
    $pdf->SetMargins(16, 16, 16);
    $pdf->SetAutoPageBreak(true, 49);
    $pdf->SetY(18);
    if ($logo) {
        try { $pdf->Image($logo, 16, 18, $width, 0, '', '', '', false, 150); }
        catch (\Throwable $e) { /* Malformed logo should not cancel an invoice. */ }
    } else {
        $pdf->SetFont($font, 'B', 17);
        $pdf->Cell(90, 10, (string) ($vars['companyname'] ?? ''), 0, 0, 'L');
    }
    // Use one right-aligned heading, not two cells whose relative positions can wrap.
    $heading = $title . ' #' . (string) $reference;
    $pdf->SetTextColor(...fancypdf_rgb($primary));
    $size = 19.0;
    do {
        $pdf->SetFont($font, 'B', $size);
        if ($pdf->GetStringWidth($heading) <= 99 || $size <= 10) { break; }
        $size -= 0.5;
    } while (true);
    $pdf->SetXY((float) $pdf->getPageWidth() - 115, 18);
    $pdf->Cell(99, 11, $heading, 0, 0, 'R', false, '', 0, false, 'T', 'M');
    $dividerY = 42.0;
    if ($type === 'quote' && trim((string) ($vars['subject'] ?? '')) !== '') {
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetTextColor(76, 89, 100);
        $subject = (string) $vars['subject'];
        $subjectHeight = $pdf->getStringHeight(99, $subject);
        $pdf->writeHTMLCell(99, 0, (float) $pdf->getPageWidth() - 115, 30,
            '<b>' . fancypdf_escape($subject) . '</b>', 0, 0, false, true, 'R', true);
        $dividerY = max(42.0, 31 + $subjectHeight);
    }
    $pdf->SetDrawColor(...fancypdf_rgb($accent));
    $pdf->SetLineWidth(0.7);
    $pdf->Line(16, $dividerY, $pdf->getPageWidth() - 16, $dividerY);
    $pdf->SetY($dividerY + 7);

    $details = [];
    if ($type === 'invoice') {
        $details[] = ['label' => fancypdf_label($vars, 'invoicesdatecreated', 'Issued'), 'value' => $vars['datecreated'] ?? ''];
        $details[] = ['label' => fancypdf_label($vars, 'invoicesdatedue', 'Due'), 'value' => $vars['duedate'] ?? ''];
        $details[] = ['label' => 'Status', 'value' => $vars['status'] ?? ''];
        if (!empty($vars['paymentmethod'])) { $details[] = ['label' => 'Payment', 'value' => fancypdf_payment_name($vars)]; }
    } else {
        $details[] = ['label' => fancypdf_label($vars, 'quotedatecreated', 'Issued'), 'value' => $vars['datecreated'] ?? ''];
        $details[] = ['label' => fancypdf_label($vars, 'quotevaliduntil', 'Valid until'), 'value' => $vars['validuntil'] ?? ''];
    }
    // Two side-by-side blocks, with a compact fixed metadata grid. This prevents
    // the header from consuming more height than the taller of its two columns.
    $blockY = (float) $pdf->GetY();
    $billLabel = $type === 'invoice' ? 'BILL TO' : 'PREPARED FOR';
    $pdf->SetFont($font, 'B', 10);
    $pdf->SetTextColor(...fancypdf_rgb($primary));
    $pdf->SetXY(16, $blockY);
    $pdf->Cell(83, 5, $billLabel, 0, 0);
    $pdf->SetFont($font, '', 9);
    $pdf->SetTextColor(36, 50, 65);
    $address = fancypdf_address($client);
    $addressY = $blockY + 7;
    $pdf->writeHTMLCell(82, 0, 16, $addressY, $address, 0, 0, false, true, '', true);
    // writeHTMLCell(..., ln=0) does not reliably leave TCPDF's cursor at the
    // bottom of its rendered content. Independently reserve the measured height.
    $addressPlain = html_entity_decode(str_replace('<br>', "\n", $address), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $addressHeight = $pdf->getStringHeight(82, $addressPlain);
    $addressBottom = $addressY + max($addressHeight, count(explode("\n", $addressPlain)) * 5.0);
    $metaX = (float) $pdf->getPageWidth() - 103;
    foreach ($details as $index => $entry) {
        $y = $blockY + $index * 7.7;
        $icon = ($index === 0) ? 'calendar.svg' : (($index === 1) ? 'calendar-check.svg' : (($type === 'invoice' && $index === 2) ? 'status.svg' : (($index === 3) ? 'credit-card.svg' : '')));
        $labelX = $metaX;
        if ($icons && $icon !== '') {
            $file = __DIR__ . '/../assets/icons/' . $icon;
            if (is_file($file)) {
                $svg = str_replace('#ACCENT#', $accent, file_get_contents($file));
                try { $pdf->ImageSVG('@' . $svg, $metaX, $y + .5, 3.3, 3.3); $labelX += 4.6; }
                catch (\Throwable $e) { /* Plain-text metadata remains readable. */ }
            }
        }
        $pdf->SetFont($font, '', 8.5);
        $pdf->SetTextColor(104, 119, 133);
        $pdf->SetXY($labelX, $y);
        $pdf->Cell(31 - ($labelX - $metaX), 5, html_entity_decode(strip_tags($entry['label']), ENT_QUOTES, 'UTF-8'), 0, 0);
        $pdf->SetTextColor(36, 50, 65);
        $pdf->SetFont($font, 'B', 8.5);
        $pdf->SetXY($metaX + 32, $y);
        $pdf->Cell(55, 5, (string) ($entry['value'] ?? ''), 0, 0);
    }
    $metaBottom = $blockY + count($details) * 7.7;
    $pdf->SetXY(16, max($addressBottom, $metaBottom) + 7);
    $pdf->SetFont($font, '', 9);
    $pdf->SetTextColor(36, 50, 65);

    if ($type === 'quote' && !empty($vars['proposal'])) {
        $pdf->SetFont($font, '', 9);
        $pdf->writeHTML('<p>' . fancypdf_lines($vars['proposal']) . '</p>', true, false, true, false, '');
        $pdf->Ln(2);
    }

    $header = 'background-color:#E3E8EC;color:' . $primary . ';font-weight:bold;';
    $cells = '';
    $anyTaxable = false;
    if ($type === 'invoice') {
        foreach (($vars['invoiceitems'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $taxable = fancypdf_taxable($item['taxed'] ?? $item['taxable'] ?? false);
            $anyTaxable = $anyTaxable || $taxable;
            $description = fancypdf_lines($item['description'] ?? '') . ($taxable ? ' *' : '');
            $cells .= '<tr><td width="78%" style="border-bottom:1px solid #E8EDF0;">' . $description . '</td><td width="22%" align="right" style="border-bottom:1px solid #E8EDF0;">' . fancypdf_escape($item['amount'] ?? '') . '</td></tr>';
        }
        $table = '<table width="100%" cellpadding="7"><tr><th width="78%" style="' . $header . '">Description</th><th width="22%" align="right" style="' . $header . '">Line Total</th></tr>' . $cells . '</table>';
    } else {
        foreach (($vars['lineitems'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $taxable = fancypdf_taxable($item['taxed'] ?? $item['taxable'] ?? false);
            $anyTaxable = $anyTaxable || $taxable;
            $qty = fancypdf_escape($item['qty'] ?? '') . ($taxable ? '*' : '');
            $discount = trim((string) ($item['discount'] ?? ''));
            if ($discount !== '' && !str_ends_with($discount, '%')) { $discount .= '%'; }
            $cells .= '<tr><td width="9%" style="border-bottom:1px solid #E8EDF0;">' . $qty . '</td><td width="40%" style="border-bottom:1px solid #E8EDF0;">' . fancypdf_lines($item['description'] ?? '') . '</td><td width="19%" align="right" style="border-bottom:1px solid #E8EDF0;">' . fancypdf_escape($item['unitprice'] ?? '') . '</td><td width="14%" align="right" style="border-bottom:1px solid #E8EDF0;">' . fancypdf_escape($discount) . '</td><td width="18%" align="right" style="border-bottom:1px solid #E8EDF0;">' . fancypdf_escape($item['total'] ?? '') . '</td></tr>';
        }
        $table = '<table width="100%" cellpadding="6"><tr><th width="9%" style="' . $header . '">Qty</th><th width="40%" style="' . $header . '">Description</th><th width="19%" align="right" style="' . $header . '">Unit Price</th><th width="14%" align="right" style="' . $header . '">Discount</th><th width="18%" align="right" style="' . $header . '">Line Total</th></tr>' . $cells . '</table>';
    }
    $pdf->SetFont($font, '', 9);
    $pdf->writeHTML($table, true, false, true, false, '');
    $pdf->Ln(4);

    $summary = [];
    $summary[] = [fancypdf_label($vars, 'invoicessubtotal', 'Subtotal'), $vars['subtotal'] ?? ''];
    if ($type === 'invoice') {
        // Invoice PDFs use the native WHMCS taxname/taxrate/tax fields.
        // Suppress tax rows when WHMCS has no applicable named tax rule.
        foreach ([['taxname', 'taxrate', 'tax'], ['taxname2', 'taxrate2', 'tax2']] as [$nameKey, $rateKey, $amountKey]) {
            if (trim((string) ($vars[$nameKey] ?? '')) !== '') {
                $summary[] = [fancypdf_tax_label((string) $vars[$nameKey], $vars[$rateKey] ?? ''), $vars[$amountKey] ?? ''];
            }
        }
        if (!empty($vars['credit'])) { $summary[] = [fancypdf_label($vars, 'invoicescredit', 'Credit'), $vars['credit']]; }
        $summary[] = [fancypdf_label($vars, 'invoicestotal', 'Total'), $vars['total'] ?? $vars['invoiceamount'] ?? ''];
        if (isset($vars['amountpaid'])) { $summary[] = [fancypdf_label($vars, 'invoicesamountpaid', 'Paid'), $vars['amountpaid']]; }
        if (isset($vars['balance'])) { $summary[] = [fancypdf_label($vars, 'invoicesbalance', 'Balance due'), $vars['balance']]; }
    } else {
        foreach ([1, 2] as $index) {
            $taxlevel = $vars['taxlevel' . $index] ?? [];
            if ((float) ($taxlevel['rate'] ?? 0) > 0) { $summary[] = [fancypdf_tax_label((string) ($taxlevel['name'] ?? 'Tax'), $taxlevel['rate']), $vars['tax' . $index] ?? '']; }
        }
        $summary[] = [fancypdf_label($vars, 'invoicestotal', 'Total'), $vars['total'] ?? ''];
    }
    $summaryHtml = '<table width="100%" cellpadding="5">';
    $lastIndex = count($summary) - 1;
    foreach ($summary as $i => $entry) {
        $emphasis = ($i === $lastIndex) ? 'background-color:' . $primary . ';color:#FFFFFF;font-weight:bold;' : '';
        $summaryHtml .= '<tr><td width="60%"></td><td width="23%" align="right" style="' . $emphasis . '">' . fancypdf_escape($entry[0]) . '</td><td width="17%" align="right" style="' . $emphasis . '">' . fancypdf_escape($entry[1]) . '</td></tr>';
    }
    if ($anyTaxable) {
        // Place the legend in the right-hand totals area, avoiding an extra row
        // between the line-item table and the totals breakdown.
        $summaryHtml .= '<tr><td width="60%"></td><td colspan="2" align="right" style="font-size:8pt;color:#687785;">* Indicates a taxable line item.</td></tr>';
    }
    $summaryHtml .= '</table>';
    $pdf->writeHTML($summaryHtml, true, false, true, false, '');

    if ($type === 'invoice' && !empty($vars['transactions']) && is_array($vars['transactions'])) {
        $ledger = '<h4>Transactions</h4><table width="100%" cellpadding="5"><tr style="background-color:#EDF2F5;"><th width="25%">Date</th><th width="45%">Description</th><th width="30%">Amount</th></tr>';
        foreach ($vars['transactions'] as $transaction) {
            if (!is_array($transaction)) { continue; }
            $ledger .= '<tr><td>' . fancypdf_escape($transaction['date'] ?? '') . '</td><td>' . fancypdf_escape($transaction['transid'] ?? $transaction['description'] ?? '') . '</td><td>' . fancypdf_escape($transaction['amount'] ?? '') . '</td></tr>';
        }
        $pdf->writeHTML($ledger . '</table>', true, false, true, false, '');
    }
    if (!empty($vars['notes'])) { fancypdf_section($pdf, $font, 'Notes', (string) $vars['notes'], $primary); }
    if ($type === 'quote' && !empty($vars['customernotes'])) { fancypdf_section($pdf, $font, 'Additional information', (string) $vars['customernotes'], $primary); }

}

function fancypdf_rgb(string $colour): array
{
    return [hexdec(substr($colour, 1, 2)), hexdec(substr($colour, 3, 2)), hexdec(substr($colour, 5, 2))];
}

function fancypdf_section(object $pdf, string $font, string $title, string $body, string $colour): void
{
    $pdf->Ln(4);
    $pdf->SetFont($font, '', 9);
    $pdf->writeHTML('<span style="color:' . $colour . ';font-weight:bold;">' . fancypdf_escape($title) . '</span><br>' . fancypdf_lines($body), true, false, true, false, '');
}
