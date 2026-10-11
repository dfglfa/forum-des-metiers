<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lets an admin mark a consultant (speaker) as not participating this year
            // (e.g. illness) without deleting their account/profile/talk. Defaults to
            // true so every existing and newly created user is "participating".
            $table->boolean('is_participating')->default(true)->after('class');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_participating');
        });
    }
};
