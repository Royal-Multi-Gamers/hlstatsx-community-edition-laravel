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

use App\Models\Server;

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
}
