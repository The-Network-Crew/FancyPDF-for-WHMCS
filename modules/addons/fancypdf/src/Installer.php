<?php
/** FancyPDF — GPL-3.0-or-later */
if (!defined('WHMCS')) { exit('Direct access denied'); }
use WHMCS\Database\Capsule;

function fancypdf_themes(): array
{
    $themes = [];
    foreach (glob(ROOTDIR . '/templates/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        if (preg_match('/^[a-zA-Z0-9_-]+$/D', $name) && !is_link($dir)) { $themes[$name] = $name; }
    }
    ksort($themes);
    return $themes;
}

function fancypdf_install_action(string $theme, string $action): string
{
    if (!isset(fancypdf_themes()[$theme])) { throw new \RuntimeException('Invalid theme'); }
    $root = realpath(ROOTDIR . '/templates/' . $theme);
    $base = realpath(ROOTDIR . '/templates');
    if ($root === false || $base === false || dirname($root) !== $base) { throw new \RuntimeException('Invalid theme path'); }
    $files = ['invoicepdf.tpl', 'quotepdf.tpl'];
    $changes = [];
    // Preflight both files so a conflict stops the whole operation.
    foreach ($files as $name) {
        $target = $root . '/' . $name;
        if (is_link($target)) { throw new \RuntimeException('Symlink template refused: ' . $name); }
        $record = Capsule::table('mod_fancypdf_installs')->where('theme', $theme)->where('file', $name)->first();
        $now = is_file($target) ? file_get_contents($target) : null;
        if ($now === false) { throw new \RuntimeException('Cannot read ' . $name); }
        if ($action === 'install') {
            $content = file_get_contents(__DIR__ . '/../entrypoints/' . $name);
            if ($content === false) { throw new \RuntimeException('Package file missing: ' . $name); }
            if ($record && ($now === null || hash('sha256', $now) !== $record->installed_sha256)) { throw new \RuntimeException($name . ' was modified after installation; refusing overwrite'); }
            if (!$record && $now !== null && str_contains($now, '/modules/addons/fancypdf/')) { throw new \RuntimeException($name . ': unmanaged FancyPDF entry point detected'); }
            $changes[] = compact('target', 'name', 'record', 'now', 'content');
        } else {
            if (!$record) { continue; }
            if ($now === null || hash('sha256', $now) !== $record->installed_sha256) { throw new \RuntimeException($name . ' changed since installation; refusing restore'); }
            $content = $record->original_content;
            $changes[] = compact('target', 'name', 'record', 'now', 'content');
        }
    }
    if (!$changes) { return 'No managed templates to restore.'; }
    $completed = [];
    try {
        foreach ($changes as $change) {
            $target = $change['target'];
            $content = $change['content'];
            if ($content === null) {
                if (is_file($target) && !unlink($target)) { throw new \RuntimeException('Could not remove ' . $target); }
            } else {
                $tmp = tempnam(dirname($target), '.fancypdf-');
                if ($tmp === false) { throw new \RuntimeException('Could not create temporary file'); }
                try {
                    if (file_put_contents($tmp, $content, LOCK_EX) !== strlen($content)) { throw new \RuntimeException('Could not write ' . $target); }
                    if (is_file($target)) { chmod($tmp, fileperms($target) & 0777); }
                    if (!rename($tmp, $target)) { throw new \RuntimeException('Could not replace ' . $target); }
                } finally { if (is_file($tmp)) { unlink($tmp); } }
            }
            $completed[] = $change;
        }
        Capsule::connection()->transaction(function () use ($changes, $theme, $action): void {
            foreach ($changes as $change) {
                if ($action === 'install') {
                    if ($change['record']) {
                        Capsule::table('mod_fancypdf_installs')->where('theme', $theme)->where('file', $change['name'])->update(['installed_sha256' => hash('sha256', $change['content'])]);
                    } else {
                        Capsule::table('mod_fancypdf_installs')->insert(['theme' => $theme, 'file' => $change['name'], 'original_content' => $change['now'], 'installed_sha256' => hash('sha256', $change['content'])]);
                    }
                } else {
                    Capsule::table('mod_fancypdf_installs')->where('theme', $theme)->where('file', $change['name'])->delete();
                }
            }
        });
    } catch (\Throwable $e) {
        // Best-effort file rollback; keep DB original record if database action failed.
        foreach (array_reverse($completed) as $change) {
            if ($change['now'] === null) { @unlink($change['target']); }
            else { @file_put_contents($change['target'], $change['now'], LOCK_EX); }
        }
        throw $e;
    }
    return ucfirst($action) . ' completed for ' . $theme . ' (' . count($changes) . ' templates).';
}
