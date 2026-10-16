<?php
/** FancyPDF — GPL-3.0-or-later */
if (!defined('WHMCS')) { exit('Direct access denied'); }

use WHMCS\Database\Capsule;

require_once __DIR__ . '/src/Installer.php';

function fancypdf_config(): array
{
    return [
        'name' => 'FancyPDF',
        'description' => 'Open-source invoice and quote PDFs for WHMCS 8.13 / PHP 8.3.',
        'version' => '0.1.0-dev',
        'author' => 'FancyPDF contributors',
        'fields' => [
            'primary_colour' => ['FriendlyName' => 'Branding — primary colour', 'Type' => 'text', 'Size' => '12', 'Default' => '#20354A', 'Description' => 'Hex colour (#RRGGBB)'],
            'accent_colour' => ['FriendlyName' => 'Branding — accent colour', 'Type' => 'text', 'Size' => '12', 'Default' => '#D18A42', 'Description' => 'Hex colour (#RRGGBB)'],
            'logo_path' => ['FriendlyName' => 'Branding — logo', 'Type' => 'text', 'Size' => '55', 'Default' => '', 'Description' => 'Optional relative path inside WHMCS root (PNG or JPEG); defaults to assets/img/logo.png or .jpg'],
            'logo_width' => ['FriendlyName' => 'Branding — logo width (mm)', 'Type' => 'text', 'Size' => '5', 'Default' => '52'],
            'payment_details' => ['FriendlyName' => 'Payment — bank / remittance details', 'Type' => 'textarea', 'Rows' => '5', 'Cols' => '65', 'Default' => '', 'Description' => 'Displayed on invoices only; never include secrets'],
            'footer_text' => ['FriendlyName' => 'Footer — custom text', 'Type' => 'textarea', 'Rows' => '3', 'Cols' => '65', 'Default' => 'Thank you for your business.'],
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
    echo '<h2>FancyPDF</h2><p>Configuration lives under <strong>System Settings &rarr; Addon Modules &rarr; FancyPDF &rarr; Configure</strong>. PDF entry points are installed per theme here.</p>';
    if ($message !== '') { echo '<div class="alert alert-' . ($error ? 'danger' : 'success') . '">' . $escape($message) . '</div>'; }
    if (!$themes) { echo '<div class="alert alert-warning">No installed themes found.</div>'; return; }
    echo '<form method="post" action="' . $escape((string) $vars['modulelink']) . '">';
    if (function_exists('generate_token')) { echo '<input type="hidden" name="token" value="' . $escape((string) generate_token('plain')) . '">'; }
    echo '<p><label for="theme">System theme</label><br><select id="theme" name="theme">';
    foreach ($themes as $key => $label) { echo '<option value="' . $escape($key) . '"' . ($key === $theme ? ' selected' : '') . '>' . $escape($label) . '</option>'; }
    echo '</select></p><button class="btn btn-primary" name="fancypdf_action" value="install">Install / update entry points</button> ';
    echo '<button class="btn btn-default" name="fancypdf_action" value="restore" onclick="return confirm(\'Restore the backed-up original PDF templates?\')">Restore originals</button></form>';
    echo '<p><small>Installation refuses to replace unknown or subsequently modified entry points. Existing contents are preserved in the database. The addon never automatically modifies templates during activation.</small></p>';
    echo '<h3>Installation records</h3><table class="table table-striped"><thead><tr><th>Theme</th><th>Template</th><th>Installed?</th></tr></thead><tbody>';
    foreach (Capsule::table('mod_fancypdf_installs')->orderBy('theme')->get() as $record) {
        $file = ROOTDIR . '/templates/' . $record->theme . '/' . $record->file;
        $state = is_file($file) && hash_file('sha256', $file) === $record->installed_sha256 ? 'Managed' : 'Changed / missing';
        echo '<tr><td>' . $escape($record->theme) . '</td><td>' . $escape($record->file) . '</td><td>' . $escape($state) . '</td></tr>';
    }
    echo '</tbody></table>';
}
