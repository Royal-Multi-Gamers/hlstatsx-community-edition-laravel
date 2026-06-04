<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hlstats_Options')
            ->updateOrInsert(
                ['keyname' => 'version'],
                ['value' => '1.0.1', 'opttype' => 1]
            );
    }

    public function down(): void
    {
        DB::table('hlstats_Options')
            ->where('keyname', 'version')
            ->update(['value' => '1.0.0']);
    }
};
