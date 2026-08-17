<?php

/*
 * Custom error pages replace Laravel's bare defaults. The 404 in particular is
 * what Googlebot receives for every retired legacy URL, so it must answer with
 * the right status, be marked noindex, and still offer a way back into the site.
 */

test('an unknown url renders the custom 404 page', function () {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound();
    $response->assertViewIs('errors.404');
    $response->assertSee('404', escape: false);
});

test('the 404 page is marked noindex', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('name="robots" content="noindex,follow"', escape: false);
});

test('the 404 page links back into the site', function () {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound();
    $response->assertSee('href="' . route('players.index') . '"', escape: false);
    $response->assertSee('href="' . route('servers.index') . '"', escape: false);
});

test('error views render standalone for 500 and 503', function (string $view) {
    // These must not depend on the database, the cache or the Vite manifest —
    // they are rendered precisely when those are the thing that broke.
    $html = view($view)->render();

    expect($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('noindex,nofollow')
        ->and($html)->not->toContain('@vite');
})->with(['errors.500', 'errors.503']);

test('robots.txt is served with an absolute sitemap url', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $response->assertSee('Sitemap: ' . route('sitemap'), escape: false);
    $response->assertSee('Disallow: /admin', escape: false);
});

test('robots.txt keeps the legacy entry point crawlable', function () {
    // Blocking /hlstats.php would stop Google from ever seeing the 301s and 404s
    // that resolve the "Page with redirect" reports.
    $this->get('/robots.txt')
        ->assertOk()
        ->assertDontSee('hlstats.php', escape: false);
});
