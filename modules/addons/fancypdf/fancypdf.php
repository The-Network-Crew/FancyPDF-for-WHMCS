<?php
/**
 *  FancyPDF for WHMCS (Addon Module for PDF Files)
 *   Repo: https://github.com/The-Network-Crew/FancyPDF-for-WHMCS/
 *   File: WHMCS addon registration, configuration and administration interface.
 *
 *  Copyright (C) The Network Crew Pty Ltd
 *   SPDX-License-Identifier: GPL-3.0-or-later
 *
 *  This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('WHMCS')) { exit('Direct access denied'); }

use WHMCS\Database\Capsule;

require_once __DIR__ . '/src/Installer.php';

/** One source of truth for the version shown by WHMCS and the update checker. */
function fancypdf_version(): string
{
    return '0.4.5';
}

/** Check the plain-text repository version when the admin opens FancyPDF. */
function fancypdf_latest_version(): ?string
{
    $url = 'https://github.com/The-Network-Crew/FancyPDF-for-WHMCS/raw/refs/heads/main/version';
    if (!function_exists('curl_init')) { return null; }

    $curl = curl_init($url);
    if ($curl === false) { return null; }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'FancyPDF Update Checker/' . fancypdf_version(),
    ]);
    $remote = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Only accept a plain x.y.z version; never interpret remote content as PHP.
    if ($status !== 200 || !is_string($remote)) { return null; }
    $remote = trim($remote);
    return preg_match('/^\d+\.\d+\.\d+$/D', $remote) ? $remote : null;
}

/** Register the addon and the branding, document and footer settings. */
function fancypdf_config(): array
{
    // WHMCS reads these field definitions in Addon Modules > Configure.
    return [
        'name' => 'FancyPDF',
        'description' => 'Open-source custom branded Invoice and Quote PDFs for WHMCS v8 LTS & PHP v8 (supported).',
        'version' => fancypdf_version(),
        'author' => 'The Network Crew Pty Ltd',
        'fields' => [
            'primary_colour' => ['FriendlyName' => 'Branding — primary colour', 'Type' => 'text', 'Size' => '12', 'Default' => '#20354A', 'Description' => 'Hex colour (#RRGGBB)'],
            'accent_colour' => ['FriendlyName' => 'Branding — accent colour', 'Type' => 'text', 'Size' => '12', 'Default' => '#D18A42', 'Description' => 'Hex colour (#RRGGBB)'],
            'logo_path' => ['FriendlyName' => 'Branding — logo override', 'Type' => 'text', 'Size' => '55', 'Default' => '', 'Description' => 'Relative path in WHMCS root (PNG/JPG); defaults to assets/img/logo.png/.jpg'],
            'show_icons' => ['FriendlyName' => 'Layout — show small icons', 'Type' => 'dropdown', 'Options' => 'on,off', 'Default' => 'on', 'Description' => 'Enable or disable the bundled local SVG icons in PDF metadata and footer headings.'],
            'logo_width' => ['FriendlyName' => 'Branding — logo width (mm)', 'Type' => 'text', 'Size' => '5', 'Default' => '52'],
            'invoice_title' => ['FriendlyName' => 'Invoice — heading', 'Type' => 'dropdown', 'Options' => 'INVOICE,TAX INVOICE', 'Default' => 'INVOICE'],
            'quote_title' => ['FriendlyName' => 'Quote — heading', 'Type' => 'dropdown', 'Options' => 'QUOTE,PROPOSAL', 'Default' => 'QUOTE'],
            'footer_left_title' => ['FriendlyName' => 'Footer — left title', 'Type' => 'text', 'Size' => '35', 'Default' => 'FROM'],
            'footer_centre_title' => ['FriendlyName' => 'Footer — centre title', 'Type' => 'text', 'Size' => '35', 'Default' => ''],
            'footer_right_title' => ['FriendlyName' => 'Footer — right title', 'Type' => 'text', 'Size' => '35', 'Default' => ''],
            'footer_centre' => ['FriendlyName' => 'Footer — centre column', 'Type' => 'textarea', 'Rows' => '4', 'Cols' => '65', 'Default' => '', 'Description' => 'Defaults to payment details on invoices when empty. Plain text and HTTP(S) hyperlinks; line breaks supported.'],
            'footer_right' => ['FriendlyName' => 'Footer — right column', 'Type' => 'textarea', 'Rows' => '4', 'Cols' => '65', 'Default' => '', 'Description' => 'Defaults to existing Footer — custom text when empty. Plain text and HTTP(S) hyperlinks; line breaks supported.'],
            'payment_details' => ['FriendlyName' => 'Payment — bank / remittance details', 'Type' => 'textarea', 'Rows' => '5', 'Cols' => '65', 'Default' => '', 'Description' => 'Shown in footer centre on invoices when Footer — centre column is empty'],
            'footer_text' => ['FriendlyName' => 'Footer — legacy closing text', 'Type' => 'textarea', 'Rows' => '3', 'Cols' => '65', 'Default' => 'Thank you for your business.'],
            'paper_size' => ['FriendlyName' => 'Layout — paper format', 'Type' => 'dropdown', 'Options' => 'A4,Letter', 'Default' => 'A4', 'Description' => 'Requires WHMCS PDF creation settings to use the same format; FancyPDF does not change document size.'],
        ],
    ];
}

/** Create the template backup registry on first activation. */
function fancypdf_activate(): array
{
    try {
        // Preserve original template bytes for a later explicit restore.
        if (!Capsule::schema()->hasTable('mod_fancypdf_installs')) {
            Capsule::schema()->create('mod_fancypdf_installs', function ($table) {
                $table->increments('id');
                $table->string('theme', 128);
                $table->string('file', 32);
                $table->longText('original_content')->nullable();
                $table->string('installed_sha256', 64);
                $table->unique(['theme', 'file']);
            });
        }
        return ['status' => 'success', 'description' => 'FancyPDF activated. Open Addons > FancyPDF to install template entry points.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Could not initialise installation tracking: ' . $e->getMessage()];
    }
}

/** Prevent deactivation while template overrides still need restoring. */
function fancypdf_deactivate(): array
{
    // Never discard original templates or backup records automatically.
    // Explicitly restore each theme before deactivation.
    if (Capsule::schema()->hasTable('mod_fancypdf_installs') && Capsule::table('mod_fancypdf_installs')->exists()) {
        return ['status' => 'error', 'description' => 'Restore all installed PDF templates from Addons > FancyPDF before deactivating.'];
    }
    return ['status' => 'success', 'description' => 'FancyPDF deactivated.'];
}

/** Show theme override controls and verified-file installation status. */
function fancypdf_output(array $vars): void
{
    $themes = fancypdf_themes();
    // Use the selected theme, or a safe installed theme if the input is invalid.
    $theme = (string) ($_POST['theme'] ?? $_GET['theme'] ?? array_key_first($themes) ?? '');
    if (!array_key_exists($theme, $themes)) { $theme = (string) (array_key_first($themes) ?? ''); }
    $message = '';
    $error = false;
    // File-changing actions are admin-only POSTs with WHMCS CSRF validation.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fancypdf_action'])) {
        try {
            if (!function_exists('check_token')) { throw new \RuntimeException('WHMCS CSRF validation unavailable'); }
            check_token('WHMCS.admin.default');
            $action = (string) $_POST['fancypdf_action'];
            if (!in_array($action, ['install', 'restore'], true)) { throw new \RuntimeException('Invalid operation'); }
            if ($theme === '') { throw new \RuntimeException('Select a theme'); }
            $message = fancypdf_install_action($theme, $action);
        } catch (\Throwable $e) { $error = true; $message = $e->getMessage(); }
    }
    // Escape generated admin HTML, including theme names and operation messages.
    $escape = static fn(string $x): string => htmlspecialchars($x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<p>Configure via <strong>System Settings &rarr; Addon Modules &rarr; FancyPDF &rarr; Configure</strong>.</p>';
    echo '<p>PDF template overrides / entry points are installed per-theme here.</p>';
    // Display a passive update notice; never download or execute update code.
    $installedVersion = fancypdf_version();
    $latestVersion = fancypdf_latest_version();
    $repositoryUrl = 'https://github.com/The-Network-Crew/FancyPDF-for-WHMCS';
    if ($latestVersion === null) {
        echo '<p><small>FancyPDF v' . $escape($installedVersion) . ' — Unable to check for updates.</small></p>';
    } elseif (version_compare($latestVersion, $installedVersion, '>')) {
        echo '<div class="alert alert-info">FancyPDF v' . $escape($latestVersion)
            . ' is available (installed: v' . $escape($installedVersion) . '). '
            . '<a href="' . $escape($repositoryUrl) . '" target="_blank" rel="noopener noreferrer">View on GitHub</a>.</div>';
    } else {
        echo '<p><small>FancyPDF v' . $escape($installedVersion) . ' — Up to date (remote: v' . $escape($latestVersion) . ').</small></p>';
    }
    if ($message !== '') { echo '<div class="alert alert-' . ($error ? 'danger' : 'success') . '">' . $escape($message) . '</div>'; }
    if (!$themes) { echo '<div class="alert alert-warning">No installed themes found.</div>'; return; }
    echo '<form method="post" action="' . $escape((string) $vars['modulelink']) . '">';
    if (function_exists('generate_token')) { echo '<input type="hidden" name="token" value="' . $escape((string) generate_token('plain')) . '">'; }
    echo '<p><label for="theme">System Theme</label><br><select id="theme" name="theme">';
    foreach ($themes as $key => $label) { echo '<option value="' . $escape($key) . '"' . ($key === $theme ? ' selected' : '') . '>' . $escape($label) . '</option>'; }
    echo '</select></p><button class="btn btn-primary" name="fancypdf_action" value="install">Install/Update Template Overrides</button> ';
    echo '<button class="btn btn-default" name="fancypdf_action" value="restore" onclick="return confirm(\'Restore the backed-up original PDF templates?\')">Restore original Templates</button></form>';
    echo '<p><small>Installation refuses to replace unknown or subsequently modified Template Files.<br>Existing contents are preserved in the database. The addon never automatically modifies templates during activation.</small></p>';
    echo '<h3>Installation records</h3><table class="table table-striped"><thead><tr><th>Theme</th><th>Template</th><th>Installed?</th></tr></thead><tbody>';
    // A matching hash proves file integrity, not compatibility with future WHMCS versions.
    foreach (Capsule::table('mod_fancypdf_installs')->orderBy('theme')->get() as $record) {
        $file = ROOTDIR . '/templates/' . $record->theme . '/' . $record->file;
        $state = is_file($file) && hash_file('sha256', $file) === $record->installed_sha256 ? 'Verified Override (SHA-256 match)' : 'Changed / Missing (hash mismatch)';
        echo '<tr><td>' . $escape($record->theme) . '</td><td>' . $escape($record->file) . '</td><td>' . $escape($state) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p><small>Verified override means the template file matches the SHA-256 hash recorded when FancyPDF installed it. <br>A WHMCS update that replaces or modifies the file will be detected as changed or missing. This check does not verify compatibility with an updated WHMCS PDF engine, nor whether other theme files have changed.</small></p>';
}
