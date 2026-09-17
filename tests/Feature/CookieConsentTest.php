<?php

use App\Models\Page;

beforeEach(fn () => createLegacyOptionsTable());

test('cookie banner markup is rendered on frontend pages', function () {
    $response = $this->get(route('privacy'));

    $response->assertStatus(200);
    $response->assertSee('data-cookie-banner', false);
});

test('cookie banner links to the privacy and cookies pages', function () {
    $response = $this->get(route('privacy'));

    $response->assertSee(route('cookies'));
    $response->assertSee(route('privacy'));
});

test('cookie banner is not rendered inside the admin area', function () {
    $this->get(route('admin.login'))->assertDontSee('data-cookie-banner', false);
});

test('seeded legal pages exist in both locales', function () {
    foreach (['privacy', 'cookies', 'legal'] as $slug) {
        foreach (['en', 'fr'] as $locale) {
            expect(Page::where('slug', $slug)->where('locale', $locale)->exists())->toBeTrue();
        }
    }
});

test('system pages are flagged as such', function () {
    expect(Page::where('slug', 'privacy')->where('locale', 'en')->first()->is_system)->toBeTrue();
});
