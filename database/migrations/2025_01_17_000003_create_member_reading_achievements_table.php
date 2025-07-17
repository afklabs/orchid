// database/migrations/2025_01_17_000003_create_member_reading_achievements_table.php

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
        Schema::create('member_reading_achievements', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('member_id')
                ->constrained()
                ->onDelete('cascade')
                ->comment('Member who earned this achievement');
            
            // Achievement identification
            $table->string('achievement_type', 50)
                ->comment('Type of achievement (daily_reader, streak_keeper, etc.)');
            
            $table->string('achievement_key', 100)
                ->comment('Unique key for the achievement');
            
            // Progress tracking
            $table->unsignedSmallInteger('level')
                ->default(1)
                ->comment('Current level of the achievement');
            
            $table->unsignedInteger('progress')
                ->default(0)
                ->comment('Current progress towards next level');
            
            $table->unsignedInteger('target')
                ->default(0)
                ->comment('Target value for current level');
            
            // Achievement status
            $table->timestamp('achieved_at')
                ->nullable()
                ->comment('When the achievement was earned');
            
            $table->json('metadata')
                ->nullable()
                ->comment('Additional achievement data');
            
            $table->enum('status', ['in_progress', 'achieved', 'claimed'])
                ->default('in_progress')
                ->comment('Current status of the achievement');
            
            $table->unsignedInteger('points_awarded')
                ->default(0)
                ->comment('Points awarded for this achievement');
            
            $table->timestamp('notified_at')
                ->nullable()
                ->comment('When the member was notified');
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['member_id', 'achievement_type'], 'uniq_member_achievement');
            $table->index('achievement_type', 'idx_achievement_type');
            $table->index('status', 'idx_achievement_status');
            $table->index('achieved_at', 'idx_achievement_date');
            $table->index(['member_id', 'status'], 'idx_member_status');
            $table->index('points_awarded', 'idx_achievement_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_reading_achievements');
    }
};

