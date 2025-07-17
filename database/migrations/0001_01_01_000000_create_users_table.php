<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhanced Users Table Migration with Enterprise Security Features
 * 
 * This migration creates a comprehensive users table with advanced security features:
 * - Account lockout mechanism
 * - Password history tracking
 * - Login monitoring and audit trail
 * - Orchid platform permissions
 * - Performance optimization indexes
 * 
 * MySQL Compatible Version - All syntax errors fixed
 * 
 * @version 1.0.1
 * @since   2025-01-01
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates users table with comprehensive security and audit features
     * optimized for MySQL databases.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Primary identification fields
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            
            // Authentication fields
            $table->string('password');
            $table->rememberToken();
            
            // Orchid Platform Integration
            $table->json('permissions')->nullable();
            $table->string('avatar', 255)->nullable();
            
            // Security & Account Management
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->text('password_history')->nullable();
            
            // Session & Login Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            
            // Audit & Timestamps
            $table->timestamps();
            
            // Performance Indexes (MySQL Compatible)
            $table->index(['email', 'is_active'], 'users_email_active_idx');
            $table->index(['is_active', 'created_at'], 'users_active_created_idx');
            $table->index(['failed_login_attempts', 'locked_until'], 'users_security_idx');
            $table->index('last_login_at', 'users_last_login_idx');
            $table->index('email_verified_at', 'users_verified_idx');
            
            // Note: JSON indexes not added due to MySQL limitations
            // Use application-level filtering for permissions
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 255)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            
            // Index for cleanup of expired tokens
            $table->index('created_at', 'password_reset_created_idx');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            
            // Performance indexes for session cleanup and user lookup
            $table->index(['user_id', 'last_activity'], 'sessions_user_activity_idx');
            $table->index('last_activity', 'sessions_cleanup_idx');
            
            // Foreign key constraint with cascade delete
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Safely drops all created tables in reverse dependency order.
     */
    public function down(): void
    {
        // Drop in reverse order to handle foreign key constraints
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};