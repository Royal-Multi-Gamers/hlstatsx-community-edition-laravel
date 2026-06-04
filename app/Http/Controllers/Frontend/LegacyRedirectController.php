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
use Illuminate\Http\Request;

class LegacyRedirectController extends Controller
{
    /**
     * Redirect legacy ?mode=X&game=Y URLs to the new clean routes.
     */
    public function redirect(Request $request)
    {
        $mode = $request->query('mode', 'home');
        $game = $request->query('game');

        $map = [
            'home'        => 'home',
            'players'     => 'players.index',
            'player'      => 'players.show',
            'clans'       => 'clans.index',
            'clan'        => 'clans.show',
            'weapons'     => 'weapons.index',
            'maps'        => 'maps.index',
            'chat'        => 'chat.index',
            'awards'      => 'awards.index',
            'actions'     => 'actions.index',
            'bans'        => 'bans.index',
            'countries'   => 'countries.index',
            'servers'     => 'servers.index',
            'gamepage'    => 'game.show',
        ];

        $routeName = $map[$mode] ?? 'home';

        $params = [];
        if ($game) {
            $params['game'] = $game;
        }

        // Handle player/clan show routes
        if ($mode === 'player' && $request->query('player')) {
            return redirect()->route('players.show', ['id' => $request->query('player')]);
        }
        if ($mode === 'clan' && $request->query('clan')) {
            return redirect()->route('clans.show', ['id' => $request->query('clan')]);
        }
        if ($mode === 'gamepage' && $game) {
            return redirect()->route('game.show', ['code' => $game]);
        }

        return redirect()->route($routeName, $params);
    }
}
