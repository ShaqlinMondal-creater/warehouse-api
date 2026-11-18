<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE bids MODIFY my_rank VARCHAR(20) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE bids ALTER COLUMN my_rank TYPE VARCHAR(20) USING my_rank::text');
        } else {
            // sqlite / others: recreate via temporary column
            DB::statement('ALTER TABLE bids ADD COLUMN my_rank_tmp VARCHAR(20) NULL');
            DB::statement('UPDATE bids SET my_rank_tmp = CAST(my_rank AS TEXT)');
            // Drop + rename is awkward on sqlite without doctrine; keep both readable via app cast.
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE bids MODIFY my_rank INT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE bids ALTER COLUMN my_rank TYPE INTEGER USING NULLIF(regexp_replace(my_rank, \'[^0-9]\', \'\', \'g\'), \'\')::integer');
        }
    }
};
