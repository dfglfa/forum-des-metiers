<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->insert([
            ['key' => 'event_description_de', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'event_description_fr', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', ['event_description_de', 'event_description_fr'])->delete();
    }
};
