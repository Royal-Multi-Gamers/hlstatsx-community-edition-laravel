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

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Server;
use App\Services\GeoIPService;
use App\Services\StatsService;
use Carbon\Carbon;

class GameController extends Controller
{
    public function __construct(
        private StatsService $stats,
        private GeoIPService $geoip,
    ) {}

    public function show(string $code)
    {
        $game = Game::findOrFail($code);

        $servers = Server::visible()
            ->forGame($code)
            ->get()
            ->map(function ($server) {
                $coords = $this->geoip->getCoordinates($server->address);
                return [
                    'server'    => $server,
                    'lat'       => $coords['lat'] ?? null,
                    'lng'       => $coords['lng'] ?? null,
                    'chart'     => $this->stats->getActivityChart($server->serverId),
                    'players'   => $this->stats->getServerPlayers($server->serverId),
                ];
            });

        $mapMarkers = $servers
            ->filter(fn($s) => $s['lat'] !== null && $s['lng'] !== null)
            ->map(fn($s) => [
                'lat'     => $s['lat'],
                'lng'     => $s['lng'],
                'name'    => $s['server']->name,
                'address' => $s['server']->full_address,
                'online'  => $s['server']->last_event >= now()->subMinutes(5)->timestamp,
            ])
            ->values();

        $playerMarkers = $servers
            ->flatMap(fn($s) => $s['players'])
            ->filter(fn($p) => !empty($p->lat) && !empty($p->lng) && (float)$p->lat !== 0.0 && (float)$p->lng !== 0.0)
            ->map(fn($p) => [
                'lat'     => (float)$p->lat,
                'lng'     => (float)$p->lng,
                'name'    => $p->lastName,
                'country' => $p->country ?? null,
            ])
            ->values();

        $dailyAwards = $this->stats->getDailyAwards($code, Carbon::today());

        $totalPlayers = Server::forGame($code)->sum('act_players');
        $totalKills   = \App\Models\Player::forGame($code)->sum('kills');

        $theme = app(\App\Services\ThemeService::class)->getActive();
        $tileUrl = $theme['charts']['map-tiles'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

        return view('frontend.game.show', compact(
            'game', 'servers', 'mapMarkers', 'playerMarkers', 'dailyAwards',
            'totalPlayers', 'totalKills', 'tileUrl'
        ));
    }
}
