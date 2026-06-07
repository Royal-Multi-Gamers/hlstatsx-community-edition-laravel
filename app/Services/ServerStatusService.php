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

namespace App\Services;

use App\Models\Game;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServerStatusService
{
    /**
     * Ping a Source server via A2S_INFO UDP query.
     *
     * Implements the challenge mechanism introduced in December 2020:
     * if the server replies with S2C_CHALLENGE (0x41), the request is
     * re-sent with the 4-byte challenge appended.
     *
     * @see https://developer.valvesoftware.com/wiki/Server_queries#A2S_INFO
     */
    public function ping(string $ip, int $port): bool
    {
        $socket = @fsockopen("udp://{$ip}", $port, $errno, $errstr, 2);
        if (!$socket) {
            return false;
        }

        try {
            stream_set_timeout($socket, 2);

            // A2S_INFO: FF FF FF FF 54 "Source Engine Query" 00
            $request = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
            fwrite($socket, $request);
            $data = fread($socket, 1400);

            if (strlen($data) < 5) {
                return false;
            }

            // S2C_CHALLENGE: FF FF FF FF 41 <4-byte challenge>
            // Re-send the request with the challenge appended.
            if (substr($data, 0, 5) === "\xFF\xFF\xFF\xFF\x41") {
                if (strlen($data) < 9) {
                    return false;
                }
                $challenge = substr($data, 5, 4);
                fwrite($socket, $request . $challenge);
                $data = fread($socket, 1400);

                if (strlen($data) < 5) {
                    return false;
                }
            }

            // Valid A2S_INFO response: FF FF FF FF 49 ('I')
            return substr($data, 0, 5) === "\xFF\xFF\xFF\xFF\x49";

        } finally {
            fclose($socket);
        }
    }

    /**
     * Get number of online players for a server.
     */
    public function getOnlinePlayers(int $serverId): int
    {
        $server = Server::find($serverId);
        if (!$server) {
            return 0;
        }

        return (int) $server->act_players;
    }

    /**
     * Update online status for all tracked servers.
     */
    public function updateAllStatuses(): void
    {
        Server::visible()->each(function (Server $server) {
            $online = $this->ping($server->address, (int) $server->port);
            $server->update(['last_event' => $online ? now()->timestamp : $server->last_event]);
        });
    }

    /**
     * Fetch the max_players value for a server through its game's custom
     * HTTP JSON API, when configured.
     *
     * The endpoint must return either a JSON array of objects or an object
     * whose first array value contains them. Servers are matched by their
     * `name` against the value of `Game.query_match_field`.
     *
     * Returns null when the game has no custom API, the request fails, or
     * no entry matches the server name.
     */
    public function fetchCustomMaxPlayers(Server $server): ?int
    {
        // The `game` column name collides with the `game()` relation:
        // accessing $server->game returns the string code, not the model.
        // Resolve the Game explicitly (cached per code for the request).
        static $gameCache = [];
        $code = (string) $server->getAttribute('game');
        if ($code === '') {
            return null;
        }
        $game = $gameCache[$code] ??= Game::find($code);

        if (! $game || ! $game->query_url || ! $game->query_match_field || ! $game->query_max_players_field) {
            return null;
        }

        $payload = $this->fetchCustomApiPayload($game->query_url);
        if ($payload === null) {
            return null;
        }

        $matchField = $game->query_match_field;
        $maxField   = $game->query_max_players_field;
        $needle     = (string) $server->name;

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (($entry[$matchField] ?? null) === $needle && isset($entry[$maxField])) {
                return (int) $entry[$maxField];
            }
        }

        return null;
    }

    /**
     * Fetch and decode a custom-API endpoint. Cached for 60s per URL to
     * avoid hammering the upstream when several servers share the same
     * endpoint (e.g. BattleBit's GetServerList).
     */
    private function fetchCustomApiPayload(string $url): ?array
    {
        return Cache::remember('server_query_api:' . sha1($url), 60, function () use ($url) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'hlstatsx-ce-server-query'])
                    ->get($url);
            } catch (\Throwable $e) {
                Log::warning("Custom server-query API failed for {$url}: {$e->getMessage()}");
                return null;
            }

            if (! $response->successful()) {
                Log::warning("Custom server-query API HTTP {$response->status()} for {$url}");
                return null;
            }

            $data = $response->json();
            if (! is_array($data)) {
                return null;
            }

            // If the API wraps the list in an envelope object, unwrap the
            // first array value (handles {data: [...]}, {servers: [...]}, …)
            if (! array_is_list($data)) {
                foreach ($data as $value) {
                    if (is_array($value) && array_is_list($value)) {
                        return $value;
                    }
                }
                return null;
            }

            return $data;
        });
    }
}
