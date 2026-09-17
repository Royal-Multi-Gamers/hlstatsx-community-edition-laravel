<?php

use App\Models\Page;

beforeEach(fn () => createLegacyOptionsTable());

test('privacy page is seeded and loads', function () {
    $response = $this->get(route('privacy'));

    $response->assertStatus(200);
    $response->assertViewIs('frontend.pages.show');
    $response->assertSee('Privacy Policy');
});

test('cookies page loads', function () {
    $this->get(route('cookies'))->assertStatus(200);
});

test('legal page loads', function () {
    $this->get(route('legal'))->assertStatus(200);
});

test('page is reachable through the generic slug route', function () {
    $this->get(route('pages.show', 'privacy'))->assertStatus(200);
});

test('unknown slug returns 404', function () {
    $this->get(route('pages.show', 'does-not-exist'))->assertStatus(404);
});

test('unpublished page returns 404', function () {
    Page::create([
        'slug'         => 'draft',
        'locale'       => 'en',
        'title'        => 'Draft page',
        'body'         => '<p>Hidden</p>',
        'is_published' => false,
    ]);

    $this->get(route('pages.show', 'draft'))->assertStatus(404);
});

test('page is served in the active locale', function () {
    session(['locale' => 'fr']);

    $this->get(route('privacy'))->assertSee('Politique de confidentialité', false);
});

test('page falls back to english when the locale translation is missing', function () {
    Page::create([
        'slug'         => 'rules',
        'locale'       => 'en',
        'title'        => 'Server rules',
        'body'         => '<p>Be nice.</p>',
        'is_published' => true,
    ]);

    session(['locale' => 'fr']);

    $this->get(route('pages.show', 'rules'))->assertSee('Server rules');
});

test('page body placeholders are replaced', function () {
    Page::create([
        'slug'         => 'about',
        'locale'       => 'en',
        'title'        => 'About',
        'body'         => '<p>%SITE_NAME%</p>',
        'is_published' => true,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertDontSee('%SITE_NAME%');
});

test('footer links published footer pages only', function () {
    Page::create([
        'slug'           => 'hidden',
        'locale'         => 'en',
        'title'          => 'Hidden page',
        'body'           => '<p>Nope</p>',
        'is_published'   => true,
        'show_in_footer' => false,
    ]);

    $response = $this->get(route('privacy'));

    $response->assertSee(route('pages.show', 'privacy'));
    $response->assertDontSee('Hidden page');
});
