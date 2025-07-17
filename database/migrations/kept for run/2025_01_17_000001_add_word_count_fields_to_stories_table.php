<?php
// database/migrations/2025_01_17_000001_add_word_count_fields_to_stories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Word count analytics fields
            $table->unsignedInteger('word_count')->default(0)->after('reading_time_minutes')
                ->comment('Total word count of the story content');

            $table->enum('reading_level', ['beginner', 'intermediate', 'advanced'])
                ->default('intermediate')
                ->after('word_count')
                ->comment('Reading difficulty level based on word count');

            // Indexes for performance
            $table->index('word_count', 'idx_stories_word_count');
            $table->index('reading_level', 'idx_stories_reading_level');
            $table->index(['reading_level', 'word_count'], 'idx_stories_level_words');
        });

        // Update existing stories with word count
        DB::statement("
            UPDATE stories
            SET word_count = (
                LENGTH(content) - LENGTH(REPLACE(content, ' ', '')) + 1
            )
            WHERE content IS NOT NULL AND content != ''
        ");

        // Update reading levels based on word count
        DB::statement("
            UPDATE stories
            SET reading_level = CASE
                WHEN word_count <= 500 THEN 'beginner'
                WHEN word_count <= 1500 THEN 'intermediate'
                ELSE 'advanced'
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('idx_stories_level_words');
            $table->dropIndex('idx_stories_reading_level');
            $table->dropIndex('idx_stories_word_count');

            $table->dropColumn(['word_count', 'reading_level']);
        });
    }
};