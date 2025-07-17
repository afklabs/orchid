// app/Models/Member.php (Enhanced with Security & Performance)
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * Enhanced Member Model with Security & Performance Features
 */
class Member extends Model
{
    use HasFactory, SoftDeletes, HasApiTokens, AsSource, Filterable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'device_id',
        'last_login_at',
        'email_verified_at',
        'phone_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'failed_login_attempts',
        'locked_until',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'locked_until' => 'datetime',
        'two_factor_recovery_codes' => 'encrypted:array',
        'failed_login_attempts' => 'integer',
    ];

    // Security constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING = 'pending';
    
    public const MAX_FAILED_ATTEMPTS = 7;
    public const LOCKOUT_DURATION = 15; // minutes

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function readingHistory(): HasMany
    {
        return $this->hasMany(MemberReadingHistory::class);
    }

    public function storyInteractions(): HasMany
    {
        return $this->hasMany(MemberStoryInteraction::class);
    }

    public function storyRatings(): HasMany
    {
        return $this->hasMany(MemberStoryRating::class);
    }

    public function storyViews(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SECURITY METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Set password with basic validation (simplified for stories app)
     */
    public function setPasswordAttribute(string $value): void
    {
        // Basic password validation - 8+ characters with at least one letter and number
        if (!$this->isValidPassword($value)) {
            throw new \InvalidArgumentException('Password must be at least 8 characters with letters and numbers');
        }

        $this->attributes['password'] = Hash::make($value);
        $this->attributes['password_changed_at'] = now();
    }

    /**
     * Validate password (simplified for stories app)
     */
    private function isValidPassword(string $password): bool
    {
        return strlen($password) >= 8 &&
               preg_match('/[A-Za-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    /**
     * Handle failed login attempt
     */
    public function recordFailedLogin(): void
    {
        $this->increment('failed_login_attempts');
        
        if ($this->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->update([
                'locked_until' => now()->addMinutes(self::LOCKOUT_DURATION),
                'status' => self::STATUS_SUSPENDED
            ]);
        }
    }

    /**
     * Handle successful login
     */
    public function recordSuccessfulLogin(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'status' => self::STATUS_ACTIVE
        ]);
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeNotLocked(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('locked_until')
              ->orWhere('locked_until', '<', now());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | ANALYTICS METHODS
    |--------------------------------------------------------------------------
    */

    public function getTotalReadingTimeAttribute(): int
    {
        return $this->readingHistory()->sum('time_spent') ?? 0;
    }

    public function getCompletedStoriesCountAttribute(): int
    {
        return $this->readingHistory()->completed()->count();
    }

    public function getAverageRatingAttribute(): float
    {
        return (float) $this->storyRatings()->avg('rating') ?? 0.0;
    }
}
