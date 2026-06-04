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
use App\Models\Game;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminServerController extends Controller
{
    public function index()
    {
        $servers = Server::orderBy('game')->orderBy('name')->paginate(50);
        return view('admin.servers.index', compact('servers'));
    }

    public function create()
    {
        $games = Game::visible()->orderBy('name')->get();
        $mods  = DB::table('hlstats_Mods_Supported')->orderBy('name')->get();
        return view('admin.servers.create', compact('games', 'mods'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $server = DB::transaction(function () use ($request, $data) {
            $server = Server::create($data);

            $mod = $request->input('game_mod', '');

            // 1. Copie des Mods_Defaults pour l'admin mod sélectionné
            DB::table('hlstats_Mods_Defaults')
                ->where('code', $mod)
                ->get()
                ->each(fn($r) => DB::table('hlstats_Servers_Config')->insertOrIgnore([
                    'serverId'  => $server->serverId,
                    'parameter' => $r->parameter,
                    'value'     => $r->value,
                ]));

            // 2. Paramètre Mod
            DB::table('hlstats_Servers_Config')->insertOrIgnore([
                'serverId'  => $server->serverId,
                'parameter' => 'Mod',
                'value'     => $mod,
            ]);

            // 3. Copie des Games_Defaults (via realgame du jeu)
            $realgame = DB::table('hlstats_Games')
                ->where('code', $data['game'])
                ->value('realgame') ?? $data['game'];

            DB::table('hlstats_Games_Defaults')
                ->where('code', $realgame)
                ->get()
                ->each(function ($r) use ($server) {
                    $exists = DB::table('hlstats_Servers_Config')
                        ->where('serverId', $server->serverId)
                        ->where('parameter', $r->parameter)
                        ->exists();
                    if ($exists) {
                        DB::table('hlstats_Servers_Config')
                            ->where('serverId', $server->serverId)
                            ->where('parameter', $r->parameter)
                            ->update(['value' => $r->value]);
                    } else {
                        DB::table('hlstats_Servers_Config')->insert([
                            'serverId'  => $server->serverId,
                            'parameter' => $r->parameter,
                            'value'     => $r->value,
                        ]);
                    }
                });

            // 4. HLStatsURL auto
            $hlstatsUrl = rtrim(config('app.url'), '/') . '/';
            DB::table('hlstats_Servers_Config')
                ->where('serverId', $server->serverId)
                ->where('parameter', 'HLStatsURL')
                ->update(['value' => $hlstatsUrl]);

            return $server;
        });

        return redirect()
            ->route('admin.server-config.index', ['server_id' => $server->serverId])
            ->with('success', 'Server created. Vérifiez et ajustez la configuration ci-dessous.');
    }

    public function edit(int $id)
    {
        $server = Server::findOrFail($id);
        $games  = Game::visible()->orderBy('name')->get();
        $mods   = DB::table('hlstats_Mods_Supported')->orderBy('name')->get();
        $currentMod = DB::table('hlstats_Servers_Config')
            ->where('serverId', $id)
            ->where('parameter', 'Mod')
            ->value('value') ?? '';
        return view('admin.servers.edit', compact('server', 'games', 'mods', 'currentMod'));
    }

    public function update(Request $request, int $id)
    {
        $server = Server::findOrFail($id);
        $data   = $this->validated($request);
        $server->update($data);

        // Met à jour le paramètre Mod si fourni
        $mod = $request->input('game_mod');
        if ($mod !== null) {
            DB::table('hlstats_Servers_Config')
                ->where('serverId', $id)
                ->where('parameter', 'Mod')
                ->updateOrInsert(
                    ['serverId' => $id, 'parameter' => 'Mod'],
                    ['value' => $mod]
                );
        }

        return redirect()->route('admin.servers.index')->with('success', 'Server updated.');
    }

    public function destroy(int $id)
    {
        Server::findOrFail($id)->delete();
        return redirect()->route('admin.servers.index')->with('success', 'Server deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:128'],
            'address'    => ['required', 'string', 'max:64'],
            'port'       => ['required', 'integer', 'min:1', 'max:65535'],
            'game'       => ['required', 'string', 'max:32'],
            'publicaddress' => ['nullable', 'string', 'max:64'],
            'rcon'       => ['nullable', 'string', 'max:64'],
        ]);
    }
}
