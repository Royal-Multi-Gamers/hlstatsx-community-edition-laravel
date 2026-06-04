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
 * https://github.com/Royal-Multi-Gamers/hlstatsx-community-edition-laravel
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ban;
use App\Models\Clan;
use App\Models\Game;
use App\Models\Player;
use App\Models\Server;
use App\Services\StatsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminDashboardController extends Controller
{
    public function __construct(private StatsService $stats) {}

    public function index()
    {
        $stats = [
            'players' => Player::count(),
            'clans'   => Clan::count(),
            'servers' => Server::count(),
            'games'   => Game::count(),
            'bans'    => Ban::where(function ($q) {
                $q->whereNull('expires')->orWhere('expires', '>', now());
            })->count(),
        ];

        $globalStats = $this->stats->getGlobalStats();
        $versionInfo = $this->getVersionInfo();

        return view('admin.dashboard', compact('stats', 'globalStats', 'versionInfo'));
    }

    private function getInstalledVersion(): string
    {
        $version = DB::table('hlstats_Options')
            ->where('keyname', 'version')
            ->value('value');

        return $version ? trim($version) : 'unknown';
    }

    private function getVersionInfo(): array
    {
        $installed = $this->getInstalledVersion();

        $latest = Cache::remember('admin_update_check', 3600, function () {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'hlstatsx-ce-update-checker'])
                    ->get('https://api.github.com/repos/Royal-Multi-Gamers/hlstatsx-community-edition-laravel/releases/latest');

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable) {}

            return null;
        });

        if ($latest === null) {
            return [
                'installed' => $installed,
                'latest'    => null,
                'upToDate'  => null,
            ];
        }

        $latestTag = ltrim($latest['tag_name'] ?? '', 'v');
        $upToDate  = version_compare($installed, $latestTag, '>=');

        return [
            'installed'   => $installed,
            'latest'      => $latest,
            'latestTag'   => $latestTag,
            'upToDate'    => $upToDate,
        ];
    }
}
