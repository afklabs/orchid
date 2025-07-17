// database/migrations/2025_01_17_000002_create_member_reading_statistics_table.php

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
        Schema::create('member_reading_statistics', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('member_id')
                ->constrained()
                ->onDelete('cascade')
                ->comment('Member who owns these statistics');
            
            // Core statistics
            $table->date('date')
                ->comment('Date of the statistics');
            
            $table->unsignedInteger('words_read')
                ->default(0)
                ->comment('Total words read on this date');
            
            $table->unsignedInteger('stories_completed')
                ->default(0)
                ->comment('Number of stories completed on this date');
            
            $table->unsignedInteger('reading_time_minutes')
                ->default(0)
                ->comment('Total reading time in minutes');
            
            // Streak tracking
            $table->unsignedInteger('reading_streak_days')
                ->default(0)
                ->comment('Current reading streak in days');
            
            $table->date('streak_start_date')
                ->nullable()
                ->comment('When the current streak started');
            
            $table->date('streak_end_date')
                ->nullable()
                ->comment('When the streak ended (if broken)');
            
            $table->unsignedInteger('longest_streak_days')
                ->default(0)
                ->comment('Longest reading streak achieved');
            
            // Reading level
            $table->enum('reading_level', ['beginner', 'intermediate', 'advanced', 'expert'])
                ->default('intermediate')
                ->comment('Current reading level based on performance');
            
            // Metadata
            $table->json('meta_data')
                ->nullable()
                ->comment('Additional metadata and analytics');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->unique(['member_id', 'date'], 'uniq_member_date');
            $table->index('date', 'idx_statistics_date');
            $table->index('words_read', 'idx_statistics_words');
            $table->index('reading_streak_days', 'idx_statistics_streak');
            $table->index(['member_id', 'date', 'words_read'], 'idx_member_date_words');
            $table->index(['date', 'words_read'], 'idx_date_words_leaderboard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_reading_statistics');
    }
};
