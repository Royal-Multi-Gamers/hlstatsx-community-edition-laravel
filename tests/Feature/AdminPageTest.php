<?php

use App\Models\Admin;
use App\Models\Page;

beforeEach(function () {
    createLegacyOptionsTable();

    $admin = new Admin(['username' => 'superadmin']);
    $admin->adminId     = 1;
    $admin->accessLevel = 'superadmin';

    $this->actingAs($admin, 'admin');
});

test('admin page list is reachable', function () {
    $response = $this->get(route('admin.pages.index'));

    $response->assertStatus(200);
    $response->assertViewIs('admin.pages.index');
    $response->assertSee('Privacy Policy');
});

test('admin can create a page', function () {
    $response = $this->post(route('admin.pages.store'), [
        'slug'           => 'server-rules',
        'locale'         => 'en',
        'title'          => 'Server rules',
        'body'           => '<p>Be nice.</p>',
        'sort_order'     => 40,
        'is_published'   => '1',
        'show_in_footer' => '1',
    ]);

    $page = Page::where('slug', 'server-rules')->first();

    expect($page)->not->toBeNull()
        ->and($page->is_system)->toBeFalse()
        ->and($page->sort_order)->toBe(40);

    $response->assertRedirect(route('admin.pages.edit', $page->id));
});

test('admin can edit the privacy policy body', function () {
    $page = Page::where('slug', 'privacy')->where('locale', 'en')->first();

    $this->put(route('admin.pages.update', $page->id), [
        'slug'         => 'privacy',
        'locale'       => 'en',
        'title'        => 'Privacy Policy',
        'body'         => '<p>Our own wording.</p>',
        'sort_order'   => 10,
        'is_published' => '1',
    ])->assertRedirect(route('admin.pages.edit', $page->id));

    expect($page->fresh()->body)->toBe('<p>Our own wording.</p>');
});

test('slug of a system page cannot be changed', function () {
    $page = Page::where('slug', 'privacy')->where('locale', 'en')->first();

    $this->put(route('admin.pages.update', $page->id), [
        'slug'         => 'hijacked',
        'locale'       => 'en',
        'title'        => 'Privacy Policy',
        'body'         => '<p>Text</p>',
        'is_published' => '1',
    ]);

    expect($page->fresh()->slug)->toBe('privacy');
});

test('a system page cannot be deleted', function () {
    $page = Page::where('slug', 'cookies')->where('locale', 'en')->first();

    $this->delete(route('admin.pages.destroy', $page->id))
        ->assertRedirect(route('admin.pages.index'));

    expect(Page::find($page->id))->not->toBeNull();
});

test('a custom page can be deleted', function () {
    $page = Page::create([
        'slug'   => 'temporary',
        'locale' => 'en',
        'title'  => 'Temporary',
        'body'   => '<p>Bye</p>',
    ]);

    $this->delete(route('admin.pages.destroy', $page->id));

    expect(Page::find($page->id))->toBeNull();
});

test('duplicate slug and locale is rejected', function () {
    $this->post(route('admin.pages.store'), [
        'slug'   => 'privacy',
        'locale' => 'en',
        'title'  => 'Another privacy page',
        'body'   => '<p>Duplicate</p>',
    ])->assertSessionHasErrors('slug');
});

test('invalid slug is rejected', function () {
    $this->post(route('admin.pages.store'), [
        'slug'   => 'Not A Slug',
        'locale' => 'en',
        'title'  => 'Bad slug',
        'body'   => '<p>Nope</p>',
    ])->assertSessionHasErrors('slug');
});
