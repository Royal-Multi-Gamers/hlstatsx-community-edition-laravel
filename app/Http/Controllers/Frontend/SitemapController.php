<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\GameMap;
use App\Models\Player;
use App\Models\Server;
use App\Models\Weapon;
use Carbon\Carbon;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $entries = [];

        $staticRoutes = [
            'home',
            'search',
            'players.index',
            'clans.index',
            'servers.index',
            'weapons.index',
            'maps.index',
            'chat.index',
            'countries.index',
            'awards.index',
            'roles.index',
            'actions.index',
            'bans.index',
            'voicecomm.index',
            'help',
        ];

        foreach ($staticRoutes as $routeName) {
            $entries[] = [
                'loc' => route($routeName),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => $routeName === 'home' ? '1.0' : '0.7',
            ];
        }

        Player::query()
            ->where('hideranking', 0)
            ->orderByDesc('activity')
            ->limit(3000)
            ->get(['playerId', 'activity'])
            ->each(function (Player $player) use (&$entries): void {
                $entries[] = [
                    'loc' => route('players.show', ['id' => $player->playerId]),
                    'lastmod' => $this->safeDate($player->activity),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            });

        Clan::query()
            ->where('hidden', 0)
            ->orderBy('clanId')
            ->limit(1000)
            ->get(['clanId'])
            ->each(function (Clan $clan) use (&$entries): void {
                $entries[] = [
                    'loc' => route('clans.show', ['id' => $clan->clanId]),
                    'lastmod' => null,
                    'changefreq' => 'weekly',
                    'priority' => '0.5',
                ];
            });

        Server::query()
            ->orderByDesc('last_event')
            ->limit(1000)
            ->get(['serverId', 'last_event'])
            ->each(function (Server $server) use (&$entries): void {
                $entries[] = [
                    'loc' => route('servers.show', ['id' => $server->serverId]),
                    'lastmod' => $this->safeTimestamp($server->last_event),
                    'changefreq' => 'daily',
                    'priority' => '0.6',
                ];
            });

        Weapon::query()
            ->orderByDesc('kills')
            ->limit(1000)
            ->get(['code'])
            ->each(function (Weapon $weapon) use (&$entries): void {
                if (!empty($weapon->code)) {
                    $entries[] = [
                        'loc' => route('weapons.show', ['code' => $weapon->code]),
                        'lastmod' => null,
                        'changefreq' => 'monthly',
                        'priority' => '0.5',
                    ];
                }
            });

        GameMap::query()
            ->select('map')
            ->whereNotNull('map')
            ->where('map', '!=', '')
            ->orderByDesc('kills')
            ->limit(1000)
            ->get()
            ->each(function (GameMap $map) use (&$entries): void {
                $entries[] = [
                    'loc' => route('maps.show', ['map' => $map->map]),
                    'lastmod' => null,
                    'changefreq' => 'weekly',
                    'priority' => '0.5',
                ];
            });

        return response()
            ->view('seo.sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function safeDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toAtomString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeTimestamp(mixed $value): ?string
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        try {
            return Carbon::createFromTimestamp((int) $value)->toAtomString();
        } catch (\Throwable) {
            return null;
        }
    }
}
