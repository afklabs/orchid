<?php
// database/migrations/2025_01_17_000004_add_word_count_to_member_reading_history.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('member_reading_history', function (Blueprint $table) {
            // Add word count tracking
            $table->unsignedInteger('words_read')
                ->default(0)
                ->after('time_spent')
                ->comment('Words read in this session');

            $table->timestamp('completed_at')
                ->nullable()
                ->after('last_read_at')
                ->comment('When the story was completed');

            // Add index for completed stories
            $table->index(['member_id', 'completed_at'], 'idx_member_completed');
            $table->index(['story_id', 'completed_at'], 'idx_story_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_reading_history', function (Blueprint $table) {
            $table->dropIndex('idx_story_completed');
            $table->dropIndex('idx_member_completed');

            $table->dropColumn(['words_read', 'completed_at']);
        });
    }
};