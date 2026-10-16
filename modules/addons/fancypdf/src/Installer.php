<?php
/**
 *  FancyPDF for WHMCS (Addon Module for PDF Files)
 *   Repo: https://github.com/The-Network-Crew/FancyPDF-for-WHMCS/
 *   File: Safe installation, verification and restoration of PDF template overrides.
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

/** Find installed system themes eligible for template overrides. */
function fancypdf_themes(): array
{
    $themes = [];
    foreach (glob(ROOTDIR . '/templates/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        if (preg_match('/^[a-zA-Z0-9_-]+$/D', $name) && !is_link($dir)) { $themes[$name] = $name; }
    }
    // Stable ordering keeps the theme selector predictable between visits.
    ksort($themes);
    return $themes;
}

/** Install or restore template overrides without overwriting unknown changes. */
function fancypdf_install_action(string $theme, string $action): string
{
    if (!isset(fancypdf_themes()[$theme])) { throw new \RuntimeException('Invalid theme'); }
    // Restrict writes to a direct child of the WHMCS templates directory.
    $root = realpath(ROOTDIR . '/templates/' . $theme);
    $base = realpath(ROOTDIR . '/templates');
    if ($root === false || $base === false || dirname($root) !== $base) { throw new \RuntimeException('Invalid theme path'); }
    $files = ['invoicepdf.tpl', 'quotepdf.tpl', 'invoicepdffooter.tpl', 'quotepdffooter.tpl'];
    $changes = [];
    // Preflight all four files before changing anything; abort on any conflict.
    foreach ($files as $name) {
        $target = $root . '/' . $name;
        if (is_link($target)) { throw new \RuntimeException('Symlink template refused: ' . $name); }
        // Preserve the first original contents and track exactly what we installed.
        $record = Capsule::table('mod_fancypdf_installs')->where('theme', $theme)->where('file', $name)->first();
        $now = is_file($target) ? file_get_contents($target) : null;
        if ($now === false) { throw new \RuntimeException('Cannot read ' . $name); }
        if ($action === 'install') {
            $content = file_get_contents(__DIR__ . '/../entrypoints/' . $name);
            if ($content === false) { throw new \RuntimeException('Package file missing: ' . $name); }
            // Never clobber edits or templates replaced by a WHMCS/theme update.
            if ($record && ($now === null || hash('sha256', $now) !== $record->installed_sha256)) { throw new \RuntimeException($name . ' was modified after installation; refusing overwrite'); }
            if (!$record && $now !== null && str_contains($now, '/modules/addons/fancypdf/')) { throw new \RuntimeException($name . ': unmanaged FancyPDF entry point detected'); }
            $changes[] = compact('target', 'name', 'record', 'now', 'content');
        } else {
            if (!$record) { continue; }
            if ($now === null || hash('sha256', $now) !== $record->installed_sha256) { throw new \RuntimeException($name . ' changed since installation; refusing restore'); }
            // A null backup means the template did not exist before installation.
            $content = $record->original_content;
            $changes[] = compact('target', 'name', 'record', 'now', 'content');
        }
    }
    if (!$changes) { return 'No managed templates to restore.'; }
    // Apply filesystem changes first; register them only after all writes succeed.
    $completed = [];
    try {
        foreach ($changes as $change) {
            $target = $change['target'];
            $content = $change['content'];
            if ($content === null) {
                if (is_file($target) && !unlink($target)) { throw new \RuntimeException('Could not remove ' . $target); }
            } else {
                // Write to a sibling temporary file, then rename into place.
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
        // Record file hashes and original contents together in one DB transaction.
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
