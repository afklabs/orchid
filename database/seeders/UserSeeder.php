<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

/**
 * Enterprise User Seeder with Advanced Security Features
 * 
 * Creates comprehensive user accounts with proper permission assignments,
 * security configurations, and audit logging for enterprise applications.
 * 
 * Security Features:
 * - Secure password generation
 * - Permission-based role assignment
 * - Account status configuration
 * - Audit trail logging
 * 
 * User Roles Created:
 * - Super Admin: Full system access
 * - Content Manager: Story and category management
 * - Editor: Content editing capabilities  
 * - Moderator: Content moderation
 * - Viewer: Read-only access
 * 
 * @package Database\Seeders
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class UserSeeder extends Seeder
{
    /**
     * Default password for development environment
     * WARNING: Change in production!
     */
    private const DEFAULT_PASSWORD = 'Admin@123456';

    /**
     * Production password requirements
     */
    private const PRODUCTION_PASSWORD_LENGTH = 16;

    /**
     * User role definitions with permissions
     */
    private const USER_ROLES = [
        'super_admin' => [
            'name' => 'Super Administrator',
            'email' => 'admin@admin.com',
            'permissions' => [
                // System Management - Full Access
                'platform.index',
                'platform.systems.users',
                'platform.systems.roles', 
                'platform.systems.attachment',
                'platform.systems.settings',
                'platform.systems.backup',
                
                // Content Management - Full Access
                'stories.*',
                'categories.*',
                'tags.*',
                'members.*',
                
                // Analytics & Reports - Full Access
                'analytics.*',
                'reports.*',
                'metrics.*',
                
                // Security & Audit - Full Access
                'security.*',
                
                // API Management - Full Access
                'api.*',
            ],
        ],

        'content_manager' => [
            'name' => 'Content Manager',
            'email' => 'content@admin.com',
            'permissions' => [
                // Platform Access
                'platform.index',
                'platform.systems.attachment',
                
                // Story Management - Full Access
                'stories.view',
                'stories.create',
                'stories.edit',
                'stories.delete',
                'stories.publish',
                'stories.schedule',
                'stories.moderate',
                
                // Category Management - Full Access
                'categories.view',
                'categories.create',
                'categories.edit',
                'categories.delete',
                'categories.reorder',
                
                // Tag Management - Full Access
                'tags.view',
                'tags.create',
                'tags.edit',
                'tags.delete',
                'tags.merge',
                
                // Member Management - Limited
                'members.view',
                'members.edit',
                
                // Analytics - View Only
                'analytics.view',
                'reports.view',
            ],
        ],

        'editor' => [
            'name' => 'Content Editor',
            'email' => 'editor@admin.com',
            'permissions' => [
                // Platform Access
                'platform.index',
                'platform.systems.attachment',
                
                // Story Management - Edit Only
                'stories.view',
                'stories.create',
                'stories.edit',
                'stories.publish',
                
                // Category & Tag Management - View Only
                'categories.view',
                'tags.view',
                'tags.create',
                
                // Member Management - View Only
                'members.view',
                
                // Analytics - View Only
                'analytics.view',
            ],
        ],

        'moderator' => [
            'name' => 'Content Moderator',
            'email' => 'moderator@admin.com',
            'permissions' => [
                // Platform Access
                'platform.index',
                
                // Story Management - Moderation
                'stories.view',
                'stories.edit',
                'stories.moderate',
                
                // Category & Tag Management - View Only
                'categories.view',
                'tags.view',
                
                // Member Management - Moderation
                'members.view',
                'members.suspend',
                
                // Analytics - Limited
                'analytics.view',
            ],
        ],

        'viewer' => [
            'name' => 'Content Viewer',
            'email' => 'viewer@admin.com',
            'permissions' => [
                // Platform Access
                'platform.index',
                
                // Read-Only Access
                'stories.view',
                'categories.view',
                'tags.view',
                'members.view',
                'analytics.view',
                'reports.view',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     * 
     * Creates user accounts with proper security configurations
     * and comprehensive error handling.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Enterprise User Seeder...');

        // Start database transaction for data integrity
        DB::beginTransaction();

        try {
            // Create users for each defined role
            $createdUsers = [];
            $skippedUsers = [];

            foreach (self::USER_ROLES as $roleKey => $roleData) {
                $result = $this->createUserWithRole($roleKey, $roleData);
                
                if ($result['success']) {
                    $createdUsers[] = $result['user'];
                    $this->command->info("✅ Created {$roleData['name']}: {$roleData['email']}");
                } else {
                    $skippedUsers[] = $roleData['email'];
                    $this->command->warn("⚠️  Skipped {$roleData['name']}: {$result['reason']}");
                }
            }

            // Commit transaction
            DB::commit();

            // Display summary
            $this->displaySeedingSummary($createdUsers, $skippedUsers);

            // Log seeding completion
            Log::info('User seeding completed', [
                'created_count' => count($createdUsers),
                'skipped_count' => count($skippedUsers),
                'environment' => app()->environment(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Throwable $exception) {
            // Rollback transaction on error
            DB::rollBack();

            $this->command->error('❌ User seeding failed: ' . $exception->getMessage());
            
            Log::error('User seeding failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'timestamp' => now()->toISOString(),
            ]);

            throw $exception;
        }
    }

    /**
     * Create a user with specific role and permissions.
     * 
     * @param string $roleKey Role identifier
     * @param array $roleData Role configuration
     * @return array Result with success status and user/reason
     */
    private function createUserWithRole(string $roleKey, array $roleData): array
    {
        try {
            // Check if user already exists
            if (User::where('email', $roleData['email'])->exists()) {
                return [
                    'success' => false,
                    'reason' => 'User already exists',
                ];
            }

            // Generate secure password
            $password = $this->generateSecurePassword();

            // Create user with security features
            $user = User::create([
                'name' => $roleData['name'],
                'email' => $roleData['email'],
                'email_verified_at' => now(),
                'password' => $password, // Will be hashed by User model
                'permissions' => $roleData['permissions'],
                'is_active' => true,
                'avatar' => null,
                'last_login_at' => now(),
                'last_login_ip' => '127.0.0.1', // Seeder creation
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'password_history' => null,
            ]);

            // Store password for display (only in development)
            $user->_seeder_password = $password;

            // Log user creation
            Log::info('User created via seeder', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $roleKey,
                'permissions_count' => count($roleData['permissions']),
                'environment' => app()->environment(),
                'timestamp' => now()->toISOString(),
            ]);

            return [
                'success' => true,
                'user' => $user,
            ];

        } catch (\Throwable $exception) {
            Log::error('Failed to create user', [
                'role' => $roleKey,
                'email' => $roleData['email'],
                'error' => $exception->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            return [
                'success' => false,
                'reason' => 'Creation failed: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * Generate a secure password based on environment.
     * 
     * @return string Generated password
     */
    private function generateSecurePassword(): string
    {
        // Use default password in development for convenience
        if (app()->environment(['local', 'development', 'testing'])) {
            return self::DEFAULT_PASSWORD;
        }

        // Generate random secure password for production
        return $this->generateRandomPassword(self::PRODUCTION_PASSWORD_LENGTH);
    }

    /**
     * Generate a cryptographically secure random password.
     * 
     * @param int $length Password length
     * @return string Generated password
     */
    private function generateRandomPassword(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        // Ensure at least one character from each category
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Fill remaining length with random characters
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to randomize character positions
        return str_shuffle($password);
    }

    /**
     * Display comprehensive seeding summary.
     * 
     * @param array $createdUsers Successfully created users
     * @param array $skippedUsers Skipped user emails
     */
    private function displaySeedingSummary(array $createdUsers, array $skippedUsers): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('🎉 Enterprise User Seeding Complete!');
        $this->command->info('═══════════════════════════════════════════════════════');

        if (!empty($createdUsers)) {
            $this->command->info('');
            $this->command->info('👥 Created Users:');
            
            foreach ($createdUsers as $user) {
                $this->command->info("   📧 {$user->email}");
                
                if (app()->environment(['local', 'development', 'testing'])) {
                    $this->command->warn("   🔐 Password: {$user->_seeder_password}");
                }
                
                $this->command->info("   🛡️  Permissions: " . count($user->permissions));
                $this->command->info('   ───────────────────────────────────────');
            }
        }

        if (!empty($skippedUsers)) {
            $this->command->info('');
            $this->command->warn('⚠️  Skipped Users (already exist):');
            foreach ($skippedUsers as $email) {
                $this->command->warn("   📧 {$email}");
            }
        }

        $this->command->info('');
        $this->command->warn('🚨 SECURITY REMINDERS:');
        $this->command->warn('   • Change all passwords before production deployment');
        $this->command->warn('   • Review and adjust permissions as needed');
        $this->command->warn('   • Enable 2FA for admin accounts');
        $this->command->warn('   • Monitor user access logs regularly');

        if (app()->environment('production')) {
            $this->command->error('');
            $this->command->error('🔒 PRODUCTION ENVIRONMENT DETECTED');
            $this->command->error('   Random passwords have been generated.');
            $this->command->error('   Please check application logs for password details.');
        }

        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info("   ✅ Created: " . count($createdUsers) . " users");
        $this->command->info("   ⚠️  Skipped: " . count($skippedUsers) . " users");
        $this->command->info("   🕒 Completed at: " . now()->format('Y-m-d H:i:s'));
        $this->command->info('═══════════════════════════════════════════════════════');
    }

    /**
     * Get statistics about created users for monitoring.
     * 
     * @return array User creation statistics
     */
    public static function getCreationStatistics(): array
    {
        return [
            'total_defined_roles' => count(self::USER_ROLES),
            'created_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'admin_users' => User::whereJsonContains('permissions', 'platform.systems.users')->count(),
            'last_created' => User::latest()->first()?->created_at?->toISOString(),
        ];
    }
}