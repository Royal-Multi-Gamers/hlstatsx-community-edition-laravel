<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The pre-rebase /hlstats.php?mode=… URLs are still in Google's index and in
 * forum posts. They must answer 301 (so the index consolidates onto the new
 * URL) or 404 (so a retired page is dropped) — never a 302 bounce to the home
 * page, which Search Console reports as "Page with redirect" / soft 404.
 *
 * Targets are written as literal paths on purpose: dataset values are built
 * before the application boots, so route() is not available there.
 */

test('listing modes redirect permanently', function (string $query, string $target) {
    $this->get("/hlstats.php?{$query}")
        ->assertStatus(301)
        ->assertRedirect($target);
})->with([
    ['mode=players',   '/players'],
    ['mode=clans',     '/clans'],
    ['mode=servers',   '/servers'],
    ['mode=weapons',   '/weapons'],
    ['mode=maps',      '/maps'],
    ['mode=actions',   '/actions'],
    ['mode=awards',    '/awards'],
    ['mode=ribbons',   '/awards'],
    ['mode=chat',      '/chat'],
    ['mode=countries', '/countries'],
    ['mode=bans',      '/bans'],
    ['mode=cheaters',  '/bans'],
    ['mode=roles',     '/roles'],
    ['mode=help',      '/help'],
    ['mode=top10',     '/players'],
    ['mode=livestats', '/servers'],
    ['mode=search',    '/search'],
    ['mode=home',      '/'],
]);

test('bare hlstats.php redirects to home', function () {
    $this->get('/hlstats.php')
        ->assertStatus(301)
        ->assertRedirect('/');
});

test('detail modes redirect permanently to the new route', function (string $query, string $target) {
    $this->get("/hlstats.php?{$query}")
        ->assertStatus(301)
        ->assertRedirect($target);
})->with([
    ['mode=playerinfo&player=7212', '/players/7212'],
    ['mode=player&player=7212',     '/players/7212'],
    ['mode=claninfo&clan=42',       '/clans/42'],
    ['mode=clan&clan=42',           '/clans/42'],
    ['mode=serverinfo&server=3',    '/servers/3'],
    ['mode=gamepage&game=cstrike',  '/game/cstrike'],
    ['mode=game&game=cstrike',      '/game/cstrike'],
]);

test('the game filter is carried across the redirect', function () {
    $this->get('/hlstats.php?mode=players&game=cstrike')
        ->assertStatus(301)
        ->assertRedirect('/players?game=cstrike');
});

test('the search term is carried across the redirect', function () {
    $this->get('/hlstats.php?mode=search&q=sniper')
        ->assertStatus(301)
        ->assertRedirect('/search?q=sniper');
});

test('mode matching is case insensitive', function () {
    $this->get('/hlstats.php?mode=PlayerInfo&player=7212')
        ->assertStatus(301)
        ->assertRedirect('/players/7212');
});

test('retired modes answer 404 instead of bouncing to the home page', function (string $mode) {
    $this->get("/hlstats.php?mode={$mode}")->assertNotFound();
})->with(['rss', 'trend', 'herotracker', 'statsme', 'totallyunknown']);

test('detail modes without a usable identifier answer 404', function (string $query) {
    $this->get("/hlstats.php?{$query}")->assertNotFound();
})->with([
    'mode=playerinfo',
    'mode=playerinfo&player=',
    'mode=playerinfo&player=abc',
    'mode=playerinfo&player=0',
    'mode=playerinfo&player=-5',
    'mode=claninfo',
    'mode=gamepage',
]);

test('playersig without a numeric player answers 404', function (string $query) {
    $this->get("/hlstats.php?{$query}")->assertNotFound();
})->with(['mode=playersig', 'mode=playersig&player=abc']);

test('playersig serves a PNG for a known player', function () {
    $playerId = seedSignaturePlayer();

    $response = $this->get("/hlstats.php?mode=playersig&player={$playerId}");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');

    $size = getimagesizefromstring($response->getContent());

    expect($size)->not->toBeFalse()
        ->and($size[0])->toBe(400)
        ->and($size[1])->toBe(75);
});

test('the clean signature route serves the same image', function () {
    $playerId = seedSignaturePlayer();

    $this->get("/players/{$playerId}/signature.png")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('the signature route answers 404 for an unknown player', function () {
    seedSignaturePlayer();

    $this->get('/players/999999999/signature.png')->assertNotFound();
});

/**
 * Inserts a minimal player row. Every other column in hlstats_Players carries a
 * schema default, so only `game` has to be supplied alongside the stats.
 */
function seedSignaturePlayer(): int
{
    if (!extension_loaded('gd') || !Schema::hasTable('hlstats_Players')) {
        test()->markTestSkipped('Requires the GD extension and the hlstats schema.');
    }

    return (int) DB::table('hlstats_Players')->insertGetId([
        'lastName'    => 'SignatureTest',
        'game'        => 'cstrike',
        'skill'       => 1500,
        'kills'       => 100,
        'deaths'      => 50,
        'headshots'   => 25,
        'shots'       => 1000,
        'hits'        => 300,
        'flag'        => 'fr',
        'clan'        => 0,
        'hideranking' => 0,
    ]);
}
