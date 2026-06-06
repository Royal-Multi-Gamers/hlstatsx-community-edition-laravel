<?php
/*
 * HLStatsX Community Edition - Laravel Rebase
 *
 * Introduce a dedicated `webapp_version` key in hlstats_Options.
 *
 * The legacy Perl daemon (hlstats.pl / hlstats-awards.pl) hardcodes its own
 * version (currently 2.5.9) and unconditionally writes it into the `version`
 * key at every startup and every awards run — which overwrites the Laravel
 * webapp version. We therefore track the webapp version under a separate key
 * that the daemon never touches.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Seed webapp_version from the current `version` value when possible,
        // so existing installs preserve their already-stored release number.
        $current = DB::table('hlstats_Options')
            ->where('keyname', 'version')
            ->value('value');

        $seed = $current && trim($current) !== '' && trim($current) !== '2.5.9'
            ? trim($current)
            : '1.0.2';

        DB::table('hlstats_Options')->updateOrInsert(
            ['keyname' => 'webapp_version'],
            ['value' => $seed, 'opttype' => 1]
        );
    }

    public function down(): void
    {
        DB::table('hlstats_Options')->where('keyname', 'webapp_version')->delete();
    }
};
