<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Design change: students now prioritize tags (student_tag_preferences) instead of picking
        // individual talks directly.
        Schema::dropIfExists('student_selections');
    }

    public function down(): void
    {
        Schema::create('student_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['student_id', 'topic_id']);
        });
    }
};
