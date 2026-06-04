<?php
/*
 * HLStatsX Community Edition - Laravel Rebase
 * A modern Laravel 13 rewrite of the HLStatsX:CE web frontend, preserving the original MySQL schema.
 *
 * A long lineage of open-source stats for Half-Life & Source engine games:
 *   HLstats (Simon Garner, 2001) -> HLstatsX (Tobias Oetzel, 2005)
 *   -> HLstatsX:CE (Nicholas Hastings, 2008) -> This rebase (Royal-Multi-Gamers, 2026)
 *
 * Perl daemon sourced from SnipeZilla/HLSTATS-2.
 *
 * Copyright (C) 2025-2026 Royal-Multi-Gamers
 * Licensed under the GNU General Public License v2.0
 * https://www.gnu.org/licenses/gpl-2.0.html
 *
 * https://github.com/Royal-Multi-Gamers/hlstatsx-community-edition
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminUpdateController extends Controller
{
    private const GITHUB_REPO    = 'Royal-Multi-Gamers/hlstatsx-community-edition';
    private const GITHUB_API     = 'https://api.github.com/repos/Royal-Multi-Gamers/hlstatsx-community-edition/releases/latest';
    private const CODELOAD_HOST  = 'https://codeload.github.com/';
    private const API_HOST       = 'https://api.github.com/';

    /** Paths relative to base_path() that must never be overwritten during an update. */
    private const PROTECTED_PATHS = ['.env', 'storage', '.git', 'vendor', 'node_modules', 'public/storage'];

    // -------------------------------------------------------------------------

    public function index()
    {
        $versionInfo = $this->getVersionInfo();
        return view('admin.update.index', compact('versionInfo'));
    }

    public function apply()
    {
        // Force a fresh GitHub API call
        Cache::forget('admin_update_check');
        $info = $this->getVersionInfo();

        if (! $info['latest']) {
            return back()->with('error', 'Impossible de contacter GitHub pour vérifier la mise à jour.');
        }

        if ($info['upToDate']) {
            return back()->with('error', 'Aucune mise à jour disponible, vous êtes déjà sur la dernière version.');
        }

        $zipUrl = $info['latest']['zipball_url'] ?? null;

        // Security: only accept URLs from the official GitHub repo
        if (! $zipUrl ||
            (! str_starts_with($zipUrl, self::API_HOST) &&
             ! str_starts_with($zipUrl, self::CODELOAD_HOST))) {
            return back()->with('error', 'URL de téléchargement inattendue, mise à jour annulée par sécurité.');
        }

        $tempZip = storage_path('app/update.zip');
        $tempDir = storage_path('app/update_temp');
        $appRoot = base_path();
        $log     = [];

        try {
            // 1. Download -------------------------------------------------------
            $log[] = 'Téléchargement de la version ' . $info['latestTag'] . '…';
            $response = Http::timeout(180)
                ->withHeaders(['User-Agent' => 'hlstatsx-ce-updater'])
                ->get($zipUrl);

            if (! $response->successful()) {
                return back()->with('error', 'Échec du téléchargement : HTTP ' . $response->status());
            }

            file_put_contents($tempZip, $response->body());
            $log[] = 'Archive téléchargée (' . round(filesize($tempZip) / 1024) . ' KB)';

            // 2. Extract --------------------------------------------------------
            if (is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
            mkdir($tempDir, 0755, true);

            $zip = new \ZipArchive();
            if ($zip->open($tempZip) !== true) {
                return back()->with('error', "Impossible d'ouvrir l'archive zip.");
            }
            $zip->extractTo($tempDir);
            $zip->close();

            // 3. Locate the single root directory inside the zip ----------------
            $entries = array_filter(scandir($tempDir), fn($e) => $e !== '.' && $e !== '..' && is_dir("$tempDir/$e"));
            $sourceDir = $tempDir . '/' . array_values($entries)[0] ?? null;

            if (! $sourceDir || ! is_dir($sourceDir)) {
                return back()->with('error', "Impossible de trouver le répertoire source dans l'archive.");
            }
            $log[] = 'Archive extraite dans : ' . basename($sourceDir);

            // 4. Copy files (protected paths are skipped) -----------------------
            $copied = $this->copyDirectory($sourceDir, $appRoot);
            $log[] = "$copied fichier(s) copié(s)";

            // 5. Run migrations -------------------------------------------------
            Artisan::call('migrate', ['--force' => true]);
            $log[] = 'Migrations exécutées : ' . trim(Artisan::output());

            // 6. Try composer install (optional, may fail without CLI access) ---
            $composerOutput = $this->tryComposer($appRoot);
            if ($composerOutput !== null) {
                $log[] = 'composer install : ' . $composerOutput;
            } else {
                $log[] = '⚠ composer install ignoré (binaire introuvable — lancez-le manuellement)';
            }

            // 7. Flush all caches -----------------------------------------------
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
            Cache::forget('admin_update_check');
            $log[] = 'Caches vidés et reconstruits';

        } catch (\Throwable $e) {
            return back()->with('error', 'Mise à jour échouée : ' . $e->getMessage());
        } finally {
            if (file_exists($tempZip)) {
                unlink($tempZip);
            }
            if (is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
        }

        return redirect()->route('admin.update.index')
            ->with('update_success', [
                'version' => $info['latestTag'],
                'log'     => $log,
            ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function copyDirectory(string $source, string $dest): int
    {
        $count    = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = ltrim(str_replace(['\\', $source], ['/', ''], $item->getPathname()), '/');

            // Skip protected paths
            foreach (self::PROTECTED_PATHS as $protected) {
                if ($relative === $protected || str_starts_with($relative, $protected . '/')) {
                    continue 2;
                }
            }

            $target = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
                $count++;
            }
        }

        return $count;
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    private function tryComposer(string $appRoot): ?string
    {
        $candidates = ['composer', 'composer.phar', '/usr/local/bin/composer', '/usr/bin/composer'];
        foreach ($candidates as $bin) {
            $test = shell_exec(escapeshellcmd($bin) . ' --version 2>&1');
            if ($test && str_contains($test, 'Composer')) {
                $cmd = escapeshellcmd($bin) . ' install --no-dev --optimize-autoloader --no-interaction 2>&1';
                $out = [];
                exec('cd ' . escapeshellarg($appRoot) . ' && ' . $cmd, $out);
                return implode(' | ', array_slice($out, -3)); // last 3 lines
            }
        }
        return null;
    }

    private function getVersionInfo(): array
    {
        $installed = DB::table('hlstats_Options')->where('keyname', 'version')->value('value') ?? 'unknown';
        $installed = trim($installed);

        $latest = Cache::remember('admin_update_check', 3600, function () {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'hlstatsx-ce-update-checker'])
                    ->get(self::GITHUB_API);

                return $response->successful() ? $response->json() : null;
            } catch (\Throwable) {
                return null;
            }
        });

        if ($latest === null) {
            return ['installed' => $installed, 'latest' => null, 'upToDate' => null];
        }

        $latestTag = ltrim($latest['tag_name'] ?? '', 'v');
        $upToDate  = version_compare($installed, $latestTag, '>=');

        return [
            'installed' => $installed,
            'latest'    => $latest,
            'latestTag' => $latestTag,
            'upToDate'  => $upToDate,
        ];
    }
}
