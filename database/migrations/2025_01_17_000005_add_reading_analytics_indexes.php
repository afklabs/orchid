// database/migrations/2025_01_17_000005_add_reading_analytics_indexes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for performance optimization.
     */
    public function up(): void
    {
        // Add composite indexes for analytics queries
        Schema::table('stories', function (Blueprint $table) {
            $table->index(['active', 'active_from', 'word_count'], 'idx_active_stories_analytics');
            $table->index(['category_id', 'word_count', 'views'], 'idx_category_analytics');
        });

        // Add indexes for member analytics
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'total_points')) {
                $table->unsignedInteger('total_points')->default(0)->after('status');
            }
            
            $table->index('total_points', 'idx_members_points');
        });

        // Add covering index for leaderboard queries
        Schema::table('member_reading_statistics', function (Blueprint $table) {
            $table->index(
                ['date', 'words_read', 'member_id'], 
                'idx_daily_leaderboard'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('idx_category_analytics');
            $table->dropIndex('idx_active_stories_analytics');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('idx_members_points');
            
            if (Schema::hasColumn('members', 'total_points')) {
                $table->dropColumn('total_points');
            }
        });

        Schema::table('member_reading_statistics', function (Blueprint $table) {
            $table->dropIndex('idx_daily_leaderboard');
        });
    }
};
