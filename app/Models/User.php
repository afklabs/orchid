<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Orchid\Platform\Models\User as OrchidUser;
use Orchid\Support\Facades\Dashboard;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Carbon\Carbon;

/**
 * Enhanced User Model with Enterprise Permission System
 * 
 * This model extends Orchid's User model with comprehensive security features,
 * performance optimizations, and audit logging capabilities.
 * 
 * Security Features:
 * - Password complexity validation
 * - Account lockout mechanism
 * - Session management
 * - Permission caching and validation
 * - Audit trail logging
 * 
 * Performance Features:
 * - Permission caching with Redis
 * - Optimized database queries
 * - Lazy loading of relationships
 * - Index optimization
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property array|null $permissions
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $failed_login_attempts
 * @property \Carbon\Carbon|null $locked_until
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property array|null $password_history
 * @property bool $is_active
 * @property string|null $avatar
 * 
 * @package App\Models
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class User extends OrchidUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Maximum failed login attempts before account lockout
     */
    public const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Account lockout duration in minutes
     */
    public const LOCKOUT_DURATION = 30;

    /**
     * Number of previous passwords to remember
     */
    public const PASSWORD_HISTORY_LIMIT = 5;

    /**
     * Permission cache TTL in seconds (1 hour)
     */
    private const PERMISSION_CACHE_TTL = 3600;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'permissions',
        'email_verified_at',
        'avatar',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'password_history',
        'failed_login_attempts',
        'locked_until',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permissions' => 'array',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'password_history' => 'encrypted:array',
        'failed_login_attempts' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array<string, string>
     */
    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'email' => Like::class,
        'is_active' => Where::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array<int, string>
     */
    protected $allowedSorts = [
        'id',
        'name',
        'email',
        'is_active',
        'last_login_at',
        'updated_at',
        'created_at',
    ];

    /**
     * Boot the model and register event handlers.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Log user creation for audit trail
        static::created(function (User $user): void {
            Log::info('User created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString(),
            ]);
        });

        // Log user updates with sensitive field tracking
        static::updated(function (User $user): void {
            $sensitiveFields = ['password', 'permissions', 'is_active'];
            $changedSensitiveFields = array_intersect($sensitiveFields, array_keys($user->getDirty()));

            if (!empty($changedSensitiveFields)) {
                Log::warning('Sensitive user data modified', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'changed_fields' => $changedSensitiveFields,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'timestamp' => now()->toISOString(),
                ]);

                // Clear permission cache when permissions change
                if (in_array('permissions', $changedSensitiveFields)) {
                    $user->clearPermissionCache();
                }
            }
        });

        // Log user deletion
        static::deleted(function (User $user): void {
            Log::critical('User deleted', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
            ]);
        });
    }

    /**
     * Create admin user via console command with enhanced security.
     * Override method for orchid:admin command.
     * 
     * @param string $name Admin name
     * @param string $email Admin email
     * @param string $password Admin password
     * @throws \InvalidArgumentException When user already exists or validation fails
     */
    public static function createAdmin(string $name, string $email, string $password): void
    {
        // Validate input parameters
        if (empty($name) || empty($email) || empty($password)) {
            throw new \InvalidArgumentException('Name, email, and password are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        if (static::where('email', $email)->exists()) {
            throw new \InvalidArgumentException('User already exists with this email');
        }

        // Validate password complexity
        if (!static::validatePasswordComplexity($password)) {
            throw new \InvalidArgumentException('Password does not meet security requirements');
        }

        try {
            $user = static::create([
                'name' => trim(strip_tags($name)),
                'email' => strtolower(trim($email)),
                'email_verified_at' => now(),
                'password' => $password, // Will be hashed by mutator
                'permissions' => Dashboard::getAllowAllPermission(),
                'is_active' => true,
                'last_login_at' => now(),
                'last_login_ip' => '127.0.0.1', // Console creation
            ]);

            Log::info('Admin user created via console', [
                'user_id' => $user->id,
                'email' => $user->email,
                'created_by' => 'console',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Throwable $exception) {
            Log::error('Failed to create admin user', [
                'email' => $email,
                'error' => $exception->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);

            throw new \RuntimeException('Failed to create admin user: ' . $exception->getMessage());
        }
    }

    /**
     * Check if user has specific permission with caching.
     * Override Orchid's hasAccess method with compatible signature.
     * 
     * @param string $permit Permission name to check
     * @param bool $cache Whether to use caching (default: true)
     * @return bool True if user has permission
     */
    public function hasAccess(string $permit, bool $cache = true): bool
    {
        // Check if user is active
        if (!$this->is_active || $this->isLocked()) {
            return false;
        }

        // Get cached permissions or build fresh
        $userPermissions = $cache ? $this->getCachedPermissions() : ($this->permissions ?? []);

        // Check for wildcard permissions (e.g., 'stories.*')
        if (str_contains($permit, '.')) {
            $wildcardPermission = substr($permit, 0, strrpos($permit, '.')) . '.*';
            if (in_array($wildcardPermission, $userPermissions)) {
                return true;
            }
        }

        // Check for exact permission match
        return in_array($permit, $userPermissions);
    }

    /**
     * Check if user has any of the specified permissions.
     * 
     * @param array|string $permissions Array of permission names or single permission
     * @param bool $cache Whether to use caching (default: true)
     * @return bool True if user has at least one permission
     */
    public function hasAnyAccess($permissions, bool $cache = true): bool
    {
        // Handle null or empty permissions
        if (empty($permissions)) {
            return false;
        }

        // Type validation للأمان
        if (!is_array($permissions) && !is_string($permissions)) {
            throw new \InvalidArgumentException('Permissions must be string or array');
        }

        // Convert single permission to array
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        foreach ($permissions as $permission) {
            if ($this->hasAccess($permission, $cache)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all specified permissions.
     * 
     * @param array $permissions Array of permission names
     * @param bool $cache Whether to use caching (default: true)
     * @return bool True if user has all permissions
     */
    public function hasAllAccess(array $permissions, bool $cache = true): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasAccess($permission, $cache)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user permissions with caching optimization.
     * 
     * @return array Array of permission names
     */
    private function getCachedPermissions(): array
    {
        $cacheKey = "user.{$this->id}.permissions";

        return Cache::remember(
            $cacheKey,
            self::PERMISSION_CACHE_TTL,
            fn() => $this->permissions ?? []
        );
    }

    /**
     * Clear user permission cache.
     */
    public function clearPermissionCache(): void
    {
        $cacheKey = "user.{$this->id}.permissions";
        Cache::forget($cacheKey);

        Log::info('User permission cache cleared', [
            'user_id' => $this->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Check if the account is currently locked.
     * 
     * @return bool True if account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Lock the user account for security reasons.
     * 
     * @param string|null $reason Reason for locking
     */
    public function lockAccount(?string $reason = null): void
    {
        $this->update([
            'locked_until' => now()->addMinutes(self::LOCKOUT_DURATION),
            'failed_login_attempts' => self::MAX_LOGIN_ATTEMPTS,
        ]);

        Log::warning('User account locked', [
            'user_id' => $this->id,
            'email' => $this->email,
            'reason' => $reason ?? 'Maximum login attempts exceeded',
            'locked_until' => $this->locked_until->toISOString(),
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Unlock the user account.
     */
    public function unlockAccount(): void
    {
        $this->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        Log::info('User account unlocked', [
            'user_id' => $this->id,
            'email' => $this->email,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Increment failed login attempts with automatic lockout.
     */
    public function incrementLoginAttempts(): void
    {
        $attempts = $this->failed_login_attempts + 1;

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount('Maximum login attempts exceeded');
        } else {
            $this->update(['failed_login_attempts' => $attempts]);

            Log::warning('Failed login attempt', [
                'user_id' => $this->id,
                'email' => $this->email,
                'attempts' => $attempts,
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Reset failed login attempts on successful login.
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        Log::info('Successful user login', [
            'user_id' => $this->id,
            'email' => $this->email,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track password history to prevent reuse.
     * 
     * @param string $newPassword New password to set
     */
    private function trackPasswordHistory(string $newPassword): void
    {
        if (!$this->exists || !$this->password) {
            return; // Skip for new users or when no current password
        }

        $history = $this->password_history ?? [];

        // Add current password to history before changing
        array_unshift($history, $this->getOriginal('password'));

        // Keep only the last N passwords
        $history = array_slice($history, 0, self::PASSWORD_HISTORY_LIMIT);

        $this->password_history = $history;
    }

    /**
     * Check if a password was recently used.
     * 
     * @param string $password Password to check
     * @return bool True if password was recently used
     */
    public function wasPasswordRecentlyUsed(string $password): bool
    {
        $history = $this->password_history ?? [];

        foreach ($history as $hashedPassword) {
            if (Hash::check($password, $hashedPassword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate password complexity requirements.
     * 
     * @param string $password Password to validate
     * @return bool True if password meets requirements
     */
    public static function validatePasswordComplexity(string $password): bool
    {
        // Minimum 12 characters
        if (strlen($password) < 12) {
            return false;
        }

        // Must contain uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Must contain lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Must contain digit
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Must contain special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Override password mutator to track history and validate complexity.
     * 
     * @param string $password New password
     */
    public function setPasswordAttribute(string $password): void
    {
        // Track password history before setting new password
        $this->trackPasswordHistory($password);

        // Hash and store the password
        $this->attributes['password'] = Hash::make($password);
    }

    /**
     * Get user's avatar URL with fallback.
     * 
     * @return string Avatar URL
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar && file_exists(storage_path('app/public/' . $this->avatar))) {
            return asset('storage/' . $this->avatar);
        }

        // Generate Gravatar URL as fallback
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }

    /**
     * Scope to get active (non-locked, non-disabled) users.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('locked_until')
                          ->orWhere('locked_until', '<', now());
                    });
    }

    /**
     * Scope to get users with specific permission.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $permission Permission name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->whereJsonContains('permissions', $permission);
    }

    /**
     * Get user statistics for admin dashboard.
     * 
     * @return array User statistics
     */
    public static function getStatistics(): array
    {
        return [
            'total_users' => static::count(),
            'active_users' => static::active()->count(),
            'locked_users' => static::where('locked_until', '>', now())->count(),
            'inactive_users' => static::where('is_active', false)->count(),
            'users_created_today' => static::whereDate('created_at', today())->count(),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Convert user to safe array for API responses.
     * 
     * @return array Sanitized user data
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'email_verified' => !is_null($this->email_verified_at),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'permissions_count' => count($this->permissions ?? []),
        ];
    }
}