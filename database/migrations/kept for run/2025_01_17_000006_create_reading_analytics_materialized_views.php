<?php
// database/migrations/2025_01_17_000006_create_reading_analytics_materialized_views.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to create materialized views for analytics.
     */
    public function up(): void
    {
        // Create view for daily analytics
        DB::statement("
            CREATE OR REPLACE VIEW v_daily_reading_analytics AS
            SELECT
                date,
                COUNT(DISTINCT member_id) as active_readers,
                SUM(words_read) as total_words,
                SUM(stories_completed) as total_stories,
                AVG(words_read) as avg_words_per_reader,
                MAX(words_read) as max_words,
                AVG(reading_time_minutes) as avg_reading_time
            FROM member_reading_statistics
            WHERE words_read > 0
            GROUP BY date
        ");

        // Create view for member rankings
        DB::statement("
            CREATE OR REPLACE VIEW v_member_rankings AS
            SELECT
                m.id,
                m.name,
                m.avatar,
                COALESCE(SUM(mrs.words_read), 0) as total_words_read,
                COALESCE(SUM(mrs.stories_completed), 0) as total_stories,
                COALESCE(MAX(mrs.reading_streak_days), 0) as best_streak,
                COALESCE(MAX(mrs.longest_streak_days), 0) as longest_streak,
                COUNT(DISTINCT mrs.date) as reading_days,
                COALESCE(m.total_points, 0) as achievement_points
            FROM members m
            LEFT JOIN member_reading_statistics mrs ON m.id = mrs.member_id
            GROUP BY m.id, m.name, m.avatar, m.total_points
        ");

        // Create view for story performance
        DB::statement("
            CREATE OR REPLACE VIEW v_story_performance AS
            SELECT
                s.id,
                s.title,
                s.word_count,
                s.reading_level,
                s.views,
                s.category_id,
                c.name as category_name,
                COUNT(DISTINCT mrh.member_id) as unique_readers,
                COUNT(CASE WHEN mrh.reading_progress >= 100 THEN 1 END) as completions,
                AVG(mrh.time_spent) as avg_reading_time,
                AVG(msr.rating) as avg_rating
            FROM stories s
            LEFT JOIN categories c ON s.category_id = c.id
            LEFT JOIN member_reading_history mrh ON s.id = mrh.story_id
            LEFT JOIN member_story_ratings msr ON s.id = msr.story_id
            GROUP BY s.id, s.title, s.word_count, s.reading_level,
                     s.views, s.category_id, c.name
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_story_performance");
        DB::statement("DROP VIEW IF EXISTS v_member_rankings");
        DB::statement("DROP VIEW IF EXISTS v_daily_reading_analytics");
    }
};