<?php
/** FancyPDF — GPL-3.0-or-later */
if (!defined('WHMCS')) { exit('Direct access denied'); }

use WHMCS\Database\Capsule;

require_once __DIR__ . '/src/Installer.php';

function fancypdf_config(): array
{
    return [
        'name' => 'FancyPDF',
        'description' => 'Open-source custom branded Invoice and Quote PDFs for WHMCS v8 LTS & PHP v8 (supported).',
        'version' => '0.4.2-dev',
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

function fancypdf_activate(): array
{
    try {
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

function fancypdf_deactivate(): array
{
    // Never discard original templates or backup records automatically.
    // Explicitly restore each theme before deactivation.
    if (Capsule::schema()->hasTable('mod_fancypdf_installs') && Capsule::table('mod_fancypdf_installs')->exists()) {
        return ['status' => 'error', 'description' => 'Restore all installed PDF templates from Addons > FancyPDF before deactivating.'];
    }
    return ['status' => 'success', 'description' => 'FancyPDF deactivated.'];
}

function fancypdf_output(array $vars): void
{
    $themes = fancypdf_themes();
    $theme = (string) ($_POST['theme'] ?? $_GET['theme'] ?? array_key_first($themes) ?? '');
    if (!array_key_exists($theme, $themes)) { $theme = (string) (array_key_first($themes) ?? ''); }
    $message = '';
    $error = false;
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
    $escape = static fn(string $x): string => htmlspecialchars($x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<p>Configure via <strong>System Settings &rarr; Addon Modules &rarr; FancyPDF &rarr; Configure</strong>.</p>';
    echo '<p>PDF template overrides / entry points are installed per-theme here.</p>';
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
    foreach (Capsule::table('mod_fancypdf_installs')->orderBy('theme')->get() as $record) {
        $file = ROOTDIR . '/templates/' . $record->theme . '/' . $record->file;
        $state = is_file($file) && hash_file('sha256', $file) === $record->installed_sha256 ? 'Verified Override (SHA-256 match)' : 'Changed / Missing (hash mismatch)';
        echo '<tr><td>' . $escape($record->theme) . '</td><td>' . $escape($record->file) . '</td><td>' . $escape($state) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p><small>Verified override means the template file matches the SHA-256 hash recorded when FancyPDF installed it. <br>A WHMCS update that replaces or modifies the file will be detected as changed or missing. This check does not verify compatibility with an updated WHMCS PDF engine, nor whether other theme files have changed.</small></p>';
}
