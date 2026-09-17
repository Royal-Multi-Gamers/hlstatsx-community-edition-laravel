<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The legacy HLStatsX tables are provisioned by database/install.sql, not by
 * migrations, so the in-memory test database has none of them. Tests that
 * render a frontend page need hlstats_Options, which every layout reads.
 */
function createLegacyOptionsTable(): void
{
    \App\Models\Option::flushCache();

    if (\Illuminate\Support\Facades\Schema::hasTable('hlstats_Options')) {
        return;
    }

    \Illuminate\Support\Facades\Schema::create('hlstats_Options', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->string('keyname', 32)->primary();
        $table->string('value', 128)->default('');
        $table->tinyInteger('opttype')->default(1);
    });
}
