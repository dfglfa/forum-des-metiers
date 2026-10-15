<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->where('key', 'event_title_fr')->where('value', 'Forum des métiers')
            ->update(['value' => 'Forum des Métiers', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'event_title_fr')->where('value', 'Forum des Métiers')
            ->update(['value' => 'Forum des métiers', 'updated_at' => now()]);
    }
};
