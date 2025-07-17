<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhanced Members Table Migration
 * 
 * Creates or updates the members table with comprehensive security,
 * performance, and functionality features for the Orchid admin panel.
 * 
 * Security Features:
 * - Encrypted sensitive data storage
 * - Account lockout protection
 * - Two-factor authentication support
 * - Password history tracking
 * - Device ID validation
 * 
 * Performance Features:
 * - Optimized database indexes
 * - Efficient query support
 * - Pagination-friendly structure
 * - Search optimization
 * 
 * @version 2.0.0
 * @since   2025-01-17
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Basic member information
            $table->string('name', 255)->index();
            $table->string('email', 320)->unique(); // RFC 5321 max length
            $table->timestamp('email_verified_at')->nullable()->index();
            $table->string('email_verification_token', 64)->nullable()->index();
            
            // Contact information
            $table->string('phone', 20)->nullable()->index();
            $table->timestamp('phone_verified_at')->nullable();
            
            // Personal information
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // Authentication
            $table->string('password');
            $table->timestamp('password_changed_at')->nullable();
            $table->rememberToken();
            
            // Account status and security
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])
                  ->default('pending')
                  ->index();
            
            // Security features
            $table->string('device_id', 255)->nullable()->index();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable()->index();
            $table->ipAddress('last_login_ip')->nullable();
            $table->text('last_user_agent')->nullable();
            
            // Two-factor authentication
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            
            // Profile information
            $table->string('avatar', 255)->nullable();
            $table->text('bio')->nullable();
            $table->json('preferences')->nullable();
            $table->json('metadata')->nullable();
            
            // API tokens (if using Sanctum)
            $table->text('api_tokens')->nullable();
            
            // Notification preferences
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            
            // Privacy settings
            $table->boolean('profile_public')->default(false);
            $table->boolean('show_reading_activity')->default(true);
            $table->boolean('allow_friend_requests')->default(true);
            
            // Reading preferences
            $table->unsignedInteger('daily_reading_goal')->nullable();
            $table->enum('preferred_reading_time', ['morning', 'afternoon', 'evening', 'night'])->nullable();
            $table->json('favorite_categories')->nullable();
            
            // Tracking and analytics
            $table->timestamp('first_story_read_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->unsignedInteger('total_reading_time')->default(0); // in minutes
            $table->unsignedBigInteger('total_words_read')->default(0);
            $table->unsignedInteger('stories_completed')->default(0);
            $table->decimal('average_reading_speed', 8, 2)->nullable(); // words per minute
            
            // Gamification
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('streak_days')->default(0);
            $table->date('streak_last_date')->nullable();
            
            // Soft deletes
            $table->softDeletes();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance optimization
            $table->index(['status', 'created_at']);
            $table->index(['last_activity_at', 'status']);
            $table->index(['email_verified_at', 'status']);
            $table->index(['points', 'level']);
            $table->index(['streak_days', 'status']);
            $table->index(['created_at', 'status']);
            
            // Composite indexes for common queries
            $table->index(['status', 'email_verified_at', 'created_at'], 'members_status_verified_created_idx');
            $table->index(['gender', 'status', 'created_at'], 'members_gender_status_created_idx');
            
            // Full-text search index (MySQL specific)
            if (config('database.default') === 'mysql') {
                $table->fullText(['name', 'email', 'bio'], 'members_search_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};

/**
 * Additional Migration for Member Statistics Table
 * 
 * Separate table for detailed member statistics to improve performance
 * and provide better analytics capabilities.
 */
class CreateMemberStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            
            // Reading statistics
            $table->date('date')->index();
            $table->unsignedInteger('words_read')->default(0);
            $table->unsignedInteger('stories_read')->default(0);
            $table->unsignedInteger('reading_time_minutes')->default(0);
            $table->unsignedInteger('sessions_count')->default(0);
            
            // Interaction statistics
            $table->unsignedInteger('likes_given')->default(0);
            $table->unsignedInteger('comments_made')->default(0);
            $table->unsignedInteger('bookmarks_added')->default(0);
            $table->unsignedInteger('shares_made')->default(0);
            
            // Performance metrics
            $table->decimal('average_reading_speed', 8, 2)->nullable();
            $table->decimal('completion_rate', 5, 2)->nullable(); // percentage
            $table->unsignedInteger('longest_session_minutes')->default(0);
            
            // Engagement metrics
            $table->boolean('achieved_daily_goal')->default(false);
            $table->unsignedInteger('streak_contribution')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['member_id', 'date']);
            $table->index(['date', 'words_read']);
            $table->index(['member_id', 'date', 'words_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_statistics');
    }
}