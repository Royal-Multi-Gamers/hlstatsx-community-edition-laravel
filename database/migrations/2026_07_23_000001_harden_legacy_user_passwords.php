<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `hlstats_Users`.`password` was varchar(32) — exactly one MD5 digest wide, so
 * the column could not physically hold a bcrypt hash. Widen it so new accounts
 * can be stored with a modern hash, and drop the legacy default credential
 * ('admin' / '123456') that shipped in install.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hlstats_Users')) {
            return;
        }

        DB::statement('ALTER TABLE `hlstats_Users` MODIFY `password` VARCHAR(255) NOT NULL DEFAULT \'\'');

        // Remove the seeded default superadmin if it still carries md5('123456').
        DB::table('hlstats_Users')
            ->where('username', 'admin')
            ->where('password', 'e10adc3949ba59abbe56e057f20f883e')
            ->delete();
    }

    public function down(): void
    {
        // Not reversed: narrowing the column back would truncate bcrypt hashes.
    }
};
