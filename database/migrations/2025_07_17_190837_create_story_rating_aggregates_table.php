<?php

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
        // Create story_rating_aggregates table
        Schema::create('story_rating_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')
                ->constrained('stories')
                ->onDelete('cascade')
                ->comment('Reference to the story');
            
            // Rating statistics
            $table->decimal('average_rating', 3, 2)
                ->default(0.00)
                ->comment('Average rating (0.00 to 5.00)');
            
            $table->unsignedInteger('total_ratings')
                ->default(0)
                ->comment('Total number of ratings');
            
            // Rating distribution
            $table->unsignedInteger('rating_1_count')
                ->default(0)
                ->comment('Number of 1-star ratings');
            
            $table->unsignedInteger('rating_2_count')
                ->default(0)
                ->comment('Number of 2-star ratings');
            
            $table->unsignedInteger('rating_3_count')
                ->default(0)
                ->comment('Number of 3-star ratings');
            
            $table->unsignedInteger('rating_4_count')
                ->default(0)
                ->comment('Number of 4-star ratings');
            
            $table->unsignedInteger('rating_5_count')
                ->default(0)
                ->comment('Number of 5-star ratings');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->unique('story_id', 'idx_story_rating_aggregate_unique');
            $table->index('average_rating', 'idx_average_rating');
            $table->index('total_ratings', 'idx_total_ratings');
            $table->index(['average_rating', 'total_ratings'], 'idx_rating_performance');
        });

        // Create story_views table if not exists
        if (!Schema::hasTable('story_views')) {
            Schema::create('story_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')
                    ->constrained('stories')
                    ->onDelete('cascade')
                    ->comment('Reference to the story');
                
                $table->foreignId('member_id')
                    ->nullable()
                    ->constrained('members')
                    ->onDelete('set null')
                    ->comment('Member who viewed (null for guests)');
                
                $table->string('device_id', 255)
                    ->nullable()
                    ->comment('Unique device identifier');
                
                $table->string('ip_address', 45)
                    ->nullable()
                    ->comment('IP address of viewer');
                
                $table->text('user_agent')
                    ->nullable()
                    ->comment('Browser user agent');
                
                $table->timestamp('viewed_at')
                    ->useCurrent()
                    ->comment('When the story was viewed');
                
                // No created_at/updated_at for this table
                
                // Indexes for performance
                $table->index('story_id', 'idx_story_views_story');
                $table->index('member_id', 'idx_story_views_member');
                $table->index('device_id', 'idx_story_views_device');
                $table->index('viewed_at', 'idx_story_views_date');
                $table->index(['story_id', 'viewed_at'], 'idx_story_views_analytics');
                $table->index(['story_id', 'device_id'], 'idx_story_views_unique');
            });
        }

        // Add performance columns to stories table if not exists
        Schema::table('stories', function (Blueprint $table) {
            if (!Schema::hasColumn('stories', 'performance_score')) {
                $table->decimal('performance_score', 5, 2)
                    ->default(0.00)
                    ->after('views')
                    ->comment('Calculated performance score (0-100)');
            }
            
            if (!Schema::hasColumn('stories', 'completion_rate')) {
                $table->decimal('completion_rate', 5, 2)
                    ->default(0.00)
                    ->after('performance_score')
                    ->comment('Story completion rate percentage');
            }
            
            if (!Schema::hasColumn('stories', 'engagement_score')) {
                $table->decimal('engagement_score', 5, 2)
                    ->default(0.00)
                    ->after('completion_rate')
                    ->comment('Engagement score based on interactions');
            }
            
            if (!Schema::hasColumn('stories', 'trending_score')) {
                $table->decimal('trending_score', 5, 2)
                    ->default(0.00)
                    ->after('engagement_score')
                    ->comment('Trending score for recent activity');
            }
            
            // Add indexes for performance metrics
            if (!collect(Schema::getIndexes('stories'))->contains('name', 'idx_performance_metrics')) {
                $table->index(['performance_score', 'completion_rate', 'engagement_score'], 'idx_performance_metrics');
            }
            
            if (!collect(Schema::getIndexes('stories'))->contains('name', 'idx_trending')) {
                $table->index('trending_score', 'idx_trending');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove performance columns from stories table
        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('idx_performance_metrics');
            $table->dropIndex('idx_trending');
            $table->dropColumn([
                'performance_score',
                'completion_rate', 
                'engagement_score',
                'trending_score'
            ]);
        });
        
        // Drop tables in reverse order
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('story_rating_aggregates');
    }
};