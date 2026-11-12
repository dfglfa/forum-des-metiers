<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // series.name is a 255 char string, but consultant_profiles.serie was only 10 chars,
    // so longer series names (e.g. "sonstige / autre") failed with SQLSTATE 22001.
    public function up(): void
    {
        Schema::table('consultant_profiles', function (Blueprint $table) {
            $table->string('serie', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consultant_profiles', function (Blueprint $table) {
            $table->string('serie', 10)->nullable()->change();
        });
    }
};
