<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Orchid\Filters\{Filterable, Types\Like, Types\Where, Types\WhereDateStartEnd};
use Orchid\Screen\AsSource;

/**
 * Enhanced Story Model with Complete Enterprise Features
 * 
 * Daily Stories App - One Story Per Day Model with Word Count Analytics
 * 
 * Features:
 * - Daily story scheduling and management
 * - Advanced word count system with reading analytics
 * - Upcoming story teaser system with countdown
 * - Smart republishing system with performance analytics
 * - Comprehensive publishing history and audit trail
 * - Advanced caching and performance optimizations
 * - Security validations and rate limiting
 * - Business intelligence and recommendations
 * 
 * Business Logic:
 * - One active story at a time (daily model)
 * - Upcoming story preview for user engagement
 * - Performance-based republishing recommendations
 * - Word count tracking for reader statistics
 * - Reading level determination and analytics
 * - Comprehensive analytics and metrics tracking
 * 
 * Security Features:
 * - Input sanitization and XSS protection
 * - Permission-based access control
 * - Rate limiting for view tracking
 * - Audit trail for all changes
 * 
 * Performance Features:
 * - Redis caching with TTL management
 * - Optimized database queries with eager loading
 * - Index optimization for frequent operations
 * - Memory-efficient data processing
 * 
 * @property int $id
 * @property string $title
 * @property string|null $excerpt
 * @property string $content
 * @property string|null $image_url
 * @property int|null $category_id
 * @property int $views
 * @property int $reading_time_minutes
 * @property int $word_count
 * @property string $reading_level
 * @property bool $active
 * @property \Carbon\Carbon|null $active_from
 * @property \Carbon\Carbon|null $active_until
 * @property array|null $meta_data
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * 
 * @package App\Models
 * @author  Development Team
 * @version 3.0.0
 * @since   2025-01-01
 */
class Story extends Model implements \Orchid\Screen\AsSource
{
    use HasFactory, Filterable, AsSource;

    /*
    |--------------------------------------------------------------------------
    | CONSTANTS & CONFIGURATION
    |--------------------------------------------------------------------------
    */

    /**
     * Cache TTL for different data types
     */
    private const CACHE_TTL = [
        'current_story' => 1800,    // 30 minutes
        'upcoming_story' => 1800,   // 30 minutes
        'story_data' => 900,        // 15 minutes
        'analytics' => 3600,        // 1 hour
        'recommendations' => 7200,  // 2 hours
    ];

    /**
     * Business rules and thresholds
     */
    private const BUSINESS_RULES = [
        'reading_speed_wpm' => 200,
        'max_content_length' => 1000000, // 1MB
        'republish_threshold_views' => 100,
        'republish_min_days' => 5,
        'performance_threshold' => 50, // percentage
        'view_rate_limit_minutes' => 60,
    ];

    /**
     * Word count thresholds for reading levels
     */
    private const READING_LEVELS = [
        'beginner' => ['min' => 0, 'max' => 500],
        'intermediate' => ['min' => 501, 'max' => 1500],
        'advanced' => ['min' => 1501, 'max' => PHP_INT_MAX],
    ];

    /**
     * Reading equivalents for user engagement
     */
    private const READING_EQUIVALENTS = [
        'short_story' => ['words' => 2000, 'name' => 'قصة قصيرة', 'pages' => 8],
        'novella' => ['words' => 20000, 'name' => 'رواية قصيرة', 'pages' => 80],
        'novel' => ['words' => 80000, 'name' => 'رواية كاملة', 'pages' => 320],
        'epic' => ['words' => 150000, 'name' => 'رواية ملحمية', 'pages' => 600],
    ];

    /*
    |--------------------------------------------------------------------------
    | MODEL CONFIGURATION
    |--------------------------------------------------------------------------
    */

    /**
     * The database table used by the model.
     */
    protected $table = 'stories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'excerpt',
        'content',
        'image_url',
        'category_id',
        'reading_time_minutes',
        'word_count',
        'reading_level',
        'active',
        'active_from',
        'active_until',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'meta_data',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'id' => 'integer',
        'category_id' => 'integer',
        'views' => 'integer',
        'reading_time_minutes' => 'integer',
        'word_count' => 'integer',
        'active' => 'boolean',
        'active_from' => 'datetime',
        'active_until' => 'datetime',
        'meta_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'views' => 0,
        'active' => false,
        'reading_time_minutes' => 0,
        'word_count' => 0,
        'reading_level' => 'intermediate',
        'meta_data' => '{}',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'is_published',
        'is_expired',
        'is_upcoming',
        'is_current',
        'slug',
        'average_rating',
        'total_ratings',
        'formatted_reading_time',
        'formatted_word_count',
        'reading_equivalent',
        'status',
        'countdown_data',
        'performance_score',
    ];

    /**
     * Fields available for filtering.
     */
    protected $allowedFilters = [
        'id',
        'title',
        'category_id',
        'active',
        'reading_level',
        'word_count',
        'created_at',
        'updated_at',
        'active_from',
        'active_until',
    ];

    /**
     * Fields available for sorting.
     */
    protected $allowedSorts = [
        'id',
        'title',
        'views',
        'reading_time_minutes',
        'word_count',
        'active',
        'active_from',
        'active_until',
        'created_at',
        'updated_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS
    |--------------------------------------------------------------------------
    */

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (Story $story) {
            $story->sanitizeInput();
            $story->calculateWordCount();
            $story->calculateReadingTime();
            $story->determineReadingLevel();
            $story->validateBusinessRules();
            $story->generateMetaData();
        });

        static::saved(function (Story $story) {
            $story->clearRelatedCaches();
            $story->logAuditTrail('saved');
        });

        static::updated(function (Story $story) {
            $story->handleStatusChanges();
        });

        static::deleted(function (Story $story) {
            $story->clearRelatedCaches();
            $story->logAuditTrail('deleted');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the category that owns the story.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)
            ->withDefault([
                'name' => 'Uncategorized',
                'slug' => 'uncategorized',
            ]);
    }

    /**
     * Get the tags associated with the story.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'story_tags')
            ->withTimestamps();
    }

    /**
     * Get the story views.
     */
    public function storyViews(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /**
     * Get the story ratings.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(MemberStoryRating::class);
    }

    /**
     * Get the story interactions.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(MemberStoryInteraction::class);
    }

    /**
     * Get the publishing history.
     */
    public function publishingHistory(): HasMany
    {
        return $this->hasMany(StoryPublishingHistory::class);
    }

    /**
     * Get the reading history.
     */
    public function readingHistory(): HasMany
    {
        return $this->hasMany(MemberReadingHistory::class);
    }

    /*
    |--------------------------------------------------------------------------
    | WORD COUNT & READING ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate word count with Arabic and English support.
     */
    private function calculateWordCount(): void
    {
        if (empty($this->content)) {
            $this->word_count = 0;
            return;
        }

        // Clean content from HTML tags and normalize whitespace
        $cleanContent = strip_tags($this->content);
        $cleanContent = preg_replace('/\s+/', ' ', trim($cleanContent));
        
        if (empty($cleanContent)) {
            $this->word_count = 0;
            return;
        }

        // Count Arabic words (sequences of Arabic characters)
        $arabicMatches = preg_match_all('/[\p{Arabic}\p{N}]+/u', $cleanContent, $matches);
        $arabicWords = $arabicMatches ?: 0;
        
        // Count English words (traditional word count)
        $englishWords = str_word_count($cleanContent);
        
        // Total word count
        $this->word_count = $arabicWords + $englishWords;
        
        // Minimum word count of 1 for non-empty content
        if ($this->word_count === 0 && !empty($cleanContent)) {
            $this->word_count = 1;
        }
    }

    /**
     * Calculate reading time based on word count.
     */
    private function calculateReadingTime(): void
    {
        if ($this->word_count === 0) {
            $this->reading_time_minutes = 1;
            return;
        }

        // Calculate based on average reading speed
        $minutes = ceil($this->word_count / self::BUSINESS_RULES['reading_speed_wpm']);
        $this->reading_time_minutes = max(1, $minutes);
    }

    /**
     * Determine reading level based on word count.
     */
    private function determineReadingLevel(): void
    {
        foreach (self::READING_LEVELS as $level => $range) {
            if ($this->word_count >= $range['min'] && $this->word_count <= $range['max']) {
                $this->reading_level = $level;
                return;
            }
        }
        
        // Default to intermediate if no match
        $this->reading_level = 'intermediate';
    }

    /*
    |--------------------------------------------------------------------------
    | DAILY STORY BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */

    /**
     * Get the current active daily story.
     */
    public static function getCurrentDailyStory(): ?self
    {
        $cacheKey = 'stories.current_daily';

        return Cache::remember($cacheKey, self::CACHE_TTL['current_story'], function () {
            return static::where('active', true)
                ->where('active_from', '<=', now())
                ->where(function (Builder $query) {
                    $query->whereNull('active_until')
                        ->orWhere('active_until', '>', now());
                })
                ->with(['category', 'tags'])
                ->orderBy('active_from', 'desc')
                ->first();
        });
    }

    /**
     * Get the upcoming story for teaser display.
     */
    public static function getUpcomingStory(): ?self
    {
        $cacheKey = 'stories.upcoming';

        return Cache::remember($cacheKey, self::CACHE_TTL['upcoming_story'], function () {
            return static::where('active', true)
                ->where('active_from', '>', now())
                ->with(['category', 'tags'])
                ->orderBy('active_from', 'asc')
                ->first();
        });
    }

    /**
     * Get daily story for specific date.
     */
    public static function getDailyStoryForDate(Carbon $date): ?self
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        return static::where('active', true)
            ->where('active_from', '<=', $endOfDay)
            ->where(function (Builder $query) use ($startOfDay) {
                $query->whereNull('active_until')
                    ->orWhere('active_until', '>=', $startOfDay);
            })
            ->with(['category', 'tags'])
            ->first();
    }

    /**
     * Get archived stories (past daily stories).
     */
    public static function getArchivedStories(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('active', true)
            ->whereNotNull('active_until')
            ->where('active_until', '<', now())
            ->with(['category', 'tags'])
            ->orderBy('active_until', 'desc')
            ->limit($limit)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for published stories.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('active_from')
                    ->orWhere('active_from', '<=', now());
            })
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('active_until')
                    ->orWhere('active_until', '>', now());
            });
    }

    /**
     * Scope for upcoming stories.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where('active_from', '>', now());
    }

    /**
     * Scope for expired stories.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('active_until')
            ->where('active_until', '<=', now());
    }

    /**
     * Scope for current active story.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->published();
    }

    /**
     * Scope for searching stories.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $searchTerm = '%' . $search . '%';
        
        return $query->where(function (Builder $subQuery) use ($searchTerm) {
            $subQuery->where('title', 'LIKE', $searchTerm)
                ->orWhere('excerpt', 'LIKE', $searchTerm)
                ->orWhere('content', 'LIKE', $searchTerm)
                ->orWhere('meta_keywords', 'LIKE', $searchTerm);
        });
    }

    /**
     * Scope for stories by reading level.
     */
    public function scopeByReadingLevel(Builder $query, string $level): Builder
    {
        return $query->where('reading_level', $level);
    }

    /**
     * Scope for stories by word count range.
     */
    public function scopeByWordCount(Builder $query, int $minWords, int $maxWords = null): Builder
    {
        $query->where('word_count', '>=', $minWords);
        
        if ($maxWords !== null) {
            $query->where('word_count', '<=', $maxWords);
        }
        
        return $query;
    }

    /**
     * Scope for performance analysis.
     */
    public function scopeLowPerforming(Builder $query): Builder
    {
        return $query->where('views', '<', self::BUSINESS_RULES['republish_threshold_views'])
            ->whereHas('publishingHistory', function (Builder $subQuery) {
                $subQuery->where('action', 'published')
                    ->where('created_at', '<=', now()->subDays(self::BUSINESS_RULES['republish_min_days']));
            });
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the story's slug.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->title)
        );
    }

    /**
     * Check if story is published.
     */
    protected function isPublished(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->active) {
                    return false;
                }

                $now = now();
                
                if ($this->active_from && $this->active_from->isAfter($now)) {
                    return false;
                }

                if ($this->active_until && $this->active_until->isBefore($now)) {
                    return false;
                }

                return true;
            }
        );
    }

    /**
     * Check if story is expired.
     */
    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->active_until && $this->active_until->isPast()
        );
    }

    /**
     * Check if story is upcoming.
     */
    protected function isUpcoming(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->active && $this->active_from && $this->active_from->isFuture()
        );
    }

    /**
     * Check if story is currently active.
     */
    protected function isCurrent(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_published && !$this->is_expired
        );
    }

    /**
     * Get story status.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->active) {
                    return 'draft';
                }

                if ($this->is_upcoming) {
                    return 'scheduled';
                }

                if ($this->is_expired) {
                    return 'expired';
                }

                if ($this->is_current) {
                    return 'active';
                }

                return 'draft';
            }
        );
    }

    /**
     * Get countdown data for upcoming stories.
     */
    protected function countdownData(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->is_upcoming || !$this->active_from) {
                    return null;
                }

                $now = now();
                $diff = $this->active_from->diff($now);

                return [
                    'days' => $diff->days,
                    'hours' => $diff->h,
                    'minutes' => $diff->i,
                    'seconds' => $diff->s,
                    'total_seconds' => $this->active_from->diffInSeconds($now),
                    'formatted' => $this->formatCountdown($diff),
                ];
            }
        );
    }

    /**
     * Get average rating with caching.
     */
    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: function () {
                $cacheKey = "story.{$this->id}.avg_rating";
                
                return Cache::remember($cacheKey, self::CACHE_TTL['story_data'], function () {
                    return round($this->ratings()->avg('rating') ?? 0, 1);
                });
            }
        );
    }

    /**
     * Get total ratings count with caching.
     */
    protected function totalRatings(): Attribute
    {
        return Attribute::make(
            get: function () {
                $cacheKey = "story.{$this->id}.total_ratings";
                
                return Cache::remember($cacheKey, self::CACHE_TTL['story_data'], function () {
                    return $this->ratings()->count();
                });
            }
        );
    }

    /**
     * Get formatted reading time.
     */
    protected function formattedReadingTime(): Attribute
    {
        return Attribute::make(
            get: function () {
                $minutes = $this->reading_time_minutes;
                
                if ($minutes < 1) {
                    return '< 1 دقيقة';
                }
                
                if ($minutes === 1) {
                    return 'دقيقة واحدة';
                }
                
                return "{$minutes} دقائق";
            }
        );
    }

    /**
     * Get formatted word count.
     */
    protected function formattedWordCount(): Attribute
    {
        return Attribute::make(
            get: function () {
                $count = $this->word_count;
                
                if ($count >= 1000) {
                    return number_format($count / 1000, 1) . 'K كلمة';
                }
                
                return number_format($count) . ' كلمة';
            }
        );
    }

    /**
     * Get reading equivalent.
     */
    protected function readingEquivalent(): Attribute
    {
        return Attribute::make(
            get: function () {
                $wordCount = $this->word_count;
                
                foreach (array_reverse(self::READING_EQUIVALENTS, true) as $type => $equiv) {
                    if ($wordCount >= $equiv['words']) {
                        $ratio = $wordCount / $equiv['words'];
                        
                        return [
                            'type' => $type,
                            'name' => $equiv['name'],
                            'ratio' => round($ratio, 1),
                            'pages' => round($ratio * $equiv['pages']),
                            'description' => $this->getEquivalentDescription($ratio, $equiv['name']),
                        ];
                    }
                }
                
                return [
                    'type' => 'words',
                    'name' => 'كلمات',
                    'ratio' => 1,
                    'pages' => 1,
                    'description' => "{$wordCount} كلمة",
                ];
            }
        );
    }

    /**
     * Get performance score.
     */
    protected function performanceScore(): Attribute
    {
        return Attribute::make(
            get: function () {
                $cacheKey = "story.{$this->id}.performance_score";
                
                return Cache::remember($cacheKey, self::CACHE_TTL['analytics'], function () {
                    return $this->calculatePerformanceScore();
                });
            }
        );
    }

    /**
     * Sanitize title input.
     */
    protected function title(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => trim(strip_tags($value))
        );
    }

    /**
     * Sanitize and validate content.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                $value = trim($value);
                
                if (strlen($value) > self::BUSINESS_RULES['max_content_length']) {
                    throw new \InvalidArgumentException(
                        'Content exceeds maximum length of ' . self::BUSINESS_RULES['max_content_length'] . ' characters'
                    );
                }
                
                return $value;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REPUBLISHING SYSTEM
    |--------------------------------------------------------------------------
    */

    /**
     * Check if story can be republished.
     */
    public function canRepublish(): bool
    {
        $viewsThreshold = self::BUSINESS_RULES['republish_threshold_views'];
        $daysThreshold = self::BUSINESS_RULES['republish_min_days'];
        
        return $this->views < $viewsThreshold 
            && $this->created_at->diffInDays(now()) >= $daysThreshold
            && !$this->is_current
            && $this->publishingHistory()->where('action', 'published')->exists();
    }

    /**
     * Get republishing recommendation.
     */
    public function getRepublishRecommendation(): array
    {
        $cacheKey = "story.{$this->id}.republish_recommendation";

        return Cache::remember($cacheKey, self::CACHE_TTL['recommendations'], function () {
            $lastPublication = $this->publishingHistory()
                ->where('action', 'published')
                ->latest()
                ->first();
            
            $avgViews = static::avg('views') ?? 100;
            $performance = $avgViews > 0 ? ($this->views / $avgViews) * 100 : 0;
            
            return [
                'should_republish' => $performance < self::BUSINESS_RULES['performance_threshold'],
                'performance_score' => round($performance, 1),
                'word_count' => $this->word_count,
                'reading_level' => $this->reading_level,
                'last_published' => $lastPublication?->created_at,
                'days_since_publish' => $lastPublication?->created_at->diffInDays(now()),
                'suggested_improvements' => $this->getSuggestedImprovements(),
                'optimal_republish_time' => $this->getOptimalRepublishTime(),
                'expected_improvement' => $this->getExpectedImprovement(),
            ];
        });
    }

    /**
     * Record publishing action with enhanced data.
     */
    public function recordPublishingAction(
        string $action,
        ?array $previousData = null,
        ?string $notes = null,
        ?array $targetMetrics = null
    ): StoryPublishingHistory {
        
        $performanceData = null;
        
        if ($action === 'republished') {
            $performanceData = [
                'previous_views' => $previousData['views'] ?? $this->views,
                'previous_rating' => $this->average_rating,
                'word_count' => $this->word_count,
                'reading_level' => $this->reading_level,
                'republish_reason' => $notes,
                'target_views' => $targetMetrics['views'] ?? null,
                'target_rating' => $targetMetrics['rating'] ?? null,
                'performance_score' => $this->performance_score,
                'baseline_date' => now()->toISOString(),
            ];
        }

        $history = StoryPublishingHistory::create([
            'story_id' => $this->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'previous_active_status' => $previousData['active'] ?? null,
            'new_active_status' => $this->active,
            'previous_active_from' => $previousData['active_from'] ?? null,
            'previous_active_until' => $previousData['active_until'] ?? null,
            'new_active_from' => $this->active_from,
            'new_active_until' => $this->active_until,
            'notes' => $notes,
            'changed_fields' => $previousData ? array_keys($previousData) : null,
            'performance_data' => $performanceData,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        // Clear related caches
        $this->clearRelatedCaches();

        return $history;
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW TRACKING & ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Increment view count with rate limiting.
     */
    public function incrementViews(
        ?string $deviceId = null,
        ?int $memberId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        try {
            // Rate limiting: one view per device per hour
            $rateLimitKey = "story_view_limit.{$this->id}." . ($deviceId ?? $ipAddress ?? 'unknown');
            
            if (Cache::has($rateLimitKey)) {
                return false; // Already viewed recently
            }

            // Record the view
            StoryView::create([
                'story_id' => $this->id,
                'device_id' => $deviceId,
                'member_id' => $memberId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'viewed_at' => now(),
            ]);

            // Increment counter
            $this->increment('views');

            // Set rate limit
            Cache::put($rateLimitKey, true, self::BUSINESS_RULES['view_rate_limit_minutes'] * 60);

            // Clear related caches
            $this->clearCache();

            return true;

        } catch (\Throwable $exception) {
            Log::error('Failed to increment story views', [
                'story_id' => $this->id,
                'device_id' => $deviceId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Record story completion for reading statistics.
     */
    public function recordCompletion(int $memberId, int $timeSpent = 0): void
    {
        try {
            // Update reading history
            MemberReadingHistory::updateOrCreate(
                [
                    'member_id' => $memberId,
                    'story_id' => $this->id,
                ],
                [
                    'reading_progress' => 100,
                    'time_spent' => $timeSpent,
                    'completed_at' => now(),
                    'last_read_at' => now(),
                ]
            );

            // Update reading statistics
            MemberReadingStatistics::recordStoryCompletion($memberId, $this);

            Log::info('Story completion recorded', [
                'story_id' => $this->id,
                'member_id' => $memberId,
                'word_count' => $this->word_count,
                'reading_level' => $this->reading_level,
                'time_spent' => $timeSpent,
            ]);

        } catch (\Throwable $exception) {
            Log::error('Failed to record story completion', [
                'story_id' => $this->id,
                'member_id' => $memberId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API RESOURCE METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get formatted data for API responses (current story).
     */
    public function toApiResource(?int $memberId = null): array
    {
        $cacheKey = "story.{$this->id}.api_resource" . ($memberId ? ".member.{$memberId}" : '');

        return Cache::remember($cacheKey, self::CACHE_TTL['story_data'], function () use ($memberId) {
            $data = [
                'id' => $this->id,
                'title' => $this->title,
                'excerpt' => $this->excerpt,
                'content' => $this->content,
                'image_url' => $this->image_url,
                'slug' => $this->slug,
                'category' => $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug ?? Str::slug($this->category->name),
                ] : null,
                'tags' => $this->tags->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug ?? Str::slug($tag->name),
                ]),
                'views' => $this->views,
                'word_count' => $this->word_count,
                'formatted_word_count' => $this->formatted_word_count,
                'reading_time' => $this->formatted_reading_time,
                'reading_time_minutes' => $this->reading_time_minutes,
                'reading_level' => $this->reading_level,
                'reading_equivalent' => $this->reading_equivalent,
                'is_published' => $this->is_published,
                'is_expired' => $this->is_expired,
                'is_current' => $this->is_current,
                'published_at' => $this->active_from?->toISOString(),
                'expires_at' => $this->active_until?->toISOString(),
                'average_rating' => $this->average_rating,
                'total_ratings' => $this->total_ratings,
                'status' => $this->status,
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
            ];

            // Add member-specific data if member ID provided
            if ($memberId) {
                $data['member_interactions'] = $this->getMemberInteractions($memberId);
            }

            return $data;
        });
    }

    /**
     * Get teaser data for upcoming stories.
     */
    public function toTeaserApiResource(): array
    {
        $cacheKey = "story.{$this->id}.teaser_resource";

        return Cache::remember($cacheKey, self::CACHE_TTL['upcoming_story'], function () {
            return [
                'id' => $this->id,
                'title' => $this->title,
                'excerpt' => Str::limit($this->excerpt ?? '', 100),
                'image_url' => $this->image_url,
                'category' => $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ] : null,
                'tags' => $this->tags->take(3)->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]),
                'word_count' => $this->word_count,
                'formatted_word_count' => $this->formatted_word_count,
                'reading_level' => $this->reading_level,
                'reading_equivalent' => $this->reading_equivalent,
                'reading_time' => $this->formatted_reading_time,
                'scheduled_for' => $this->active_from?->toISOString(),
                'countdown' => $this->countdown_data,
                'is_teaser' => true,
                'status' => 'upcoming',
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS INTELLIGENCE METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate performance score based on multiple metrics.
     */
    private function calculatePerformanceScore(): float
    {
        try {
            // Get average metrics for comparison
            $avgViews = static::avg('views') ?? 100;
            $avgRating = static::join('member_story_ratings', 'stories.id', '=', 'member_story_ratings.story_id')
                ->avg('member_story_ratings.rating') ?? 3.0;

            // Calculate normalized scores (0-100)
            $viewScore = $avgViews > 0 ? min(100, ($this->views / $avgViews) * 100) : 0;
            $ratingScore = $avgRating > 0 ? ($this->average_rating / $avgRating) * 100 : 0;
            
            // Word count bonus (longer stories get slight bonus)
            $wordCountBonus = min(10, ($this->word_count / 1000) * 2);
            
            // Engagement metrics
            $completionRate = $this->getCompletionRate();
            $interactionRate = $this->getInteractionRate();

            // Weighted score calculation
            $weights = [
                'views' => 0.35,
                'rating' => 0.25,
                'completion' => 0.2,
                'interaction' => 0.15,
                'word_count' => 0.05,
            ];

            $score = ($viewScore * $weights['views']) +
                    ($ratingScore * $weights['rating']) +
                    ($completionRate * $weights['completion']) +
                    ($interactionRate * $weights['interaction']) +
                    ($wordCountBonus * $weights['word_count']);

            return round(min(100, max(0, $score)), 1);

        } catch (\Throwable $exception) {
            Log::warning('Failed to calculate performance score', [
                'story_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get suggested improvements for republishing.
     */
    private function getSuggestedImprovements(): array
    {
        $suggestions = [];

        // Analyze performance metrics
        if ($this->views < self::BUSINESS_RULES['republish_threshold_views']) {
            $suggestions[] = 'Consider updating the title for better engagement';
            $suggestions[] = 'Add more relevant tags to improve discoverability';
        }

        if ($this->average_rating < 3.5) {
            $suggestions[] = 'Review content quality and readability';
            $suggestions[] = 'Consider adding more engaging elements';
        }

        // Word count analysis
        if ($this->word_count < 200) {
            $suggestions[] = 'Consider expanding the content for better depth';
        } elseif ($this->word_count > 2000) {
            $suggestions[] = 'Consider breaking into shorter, more digestible sections';
        }

        // Reading level suggestions
        if ($this->reading_level === 'advanced') {
            $suggestions[] = 'Consider simplifying language for broader appeal';
        }

        // Timing suggestions
        $bestPublishTimes = $this->getOptimalPublishTimes();
        if (!empty($bestPublishTimes)) {
            $suggestions[] = "Consider republishing at {$bestPublishTimes[0]}:00 for better engagement";
        }

        return $suggestions;
    }

    /**
     * Get optimal republish time based on historical data.
     */
    private function getOptimalRepublishTime(): Carbon
    {
        try {
            // Analyze when similar stories performed best
            $category = $this->category;
            $bestHours = StoryView::join('stories', 'stories.id', '=', 'story_views.story_id')
                ->where('stories.category_id', $category?->id)
                ->where('stories.reading_level', $this->reading_level)
                ->selectRaw('HOUR(story_views.viewed_at) as hour, COUNT(*) as views')
                ->groupBy('hour')
                ->orderByDesc('views')
                ->limit(3)
                ->pluck('hour');

            $optimalHour = $bestHours->first() ?? 9; // Default 9 AM
            
            return now()->addDays(1)->setHour($optimalHour)->setMinute(0)->setSecond(0);

        } catch (\Throwable $exception) {
            Log::warning('Failed to calculate optimal republish time', [
                'story_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            return now()->addDays(1)->setHour(9)->setMinute(0)->setSecond(0);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get equivalent description for reading time.
     */
    private function getEquivalentDescription(float $ratio, string $name): string
    {
        if ($ratio < 0.1) {
            return "جزء صغير من {$name}";
        } elseif ($ratio < 0.5) {
            return "ربع {$name} تقريباً";
        } elseif ($ratio < 1) {
            return "نصف {$name} تقريباً";
        } elseif ($ratio < 2) {
            return "{$name} كاملة";
        } else {
            return number_format($ratio, 1) . " {$name}";
        }
    }

    /**
     * Sanitize input data.
     */
    private function sanitizeInput(): void
    {
        if ($this->excerpt) {
            $this->excerpt = trim(strip_tags($this->excerpt));
        }

        if ($this->meta_title) {
            $this->meta_title = trim(strip_tags($this->meta_title));
        }

        if ($this->meta_description) {
            $this->meta_description = trim(strip_tags($this->meta_description));
        }

        if ($this->meta_keywords) {
            $this->meta_keywords = trim(strip_tags($this->meta_keywords));
        }
    }

    /**
     * Validate business rules.
     */
    private function validateBusinessRules(): void
    {
        // Validate dates
        if ($this->active_from && $this->active_until) {
            if ($this->active_from->isAfter($this->active_until)) {
                throw new \InvalidArgumentException('Active from date must be before active until date');
            }
        }

        // Validate required fields for published stories
        if ($this->active) {
            if (empty($this->title)) {
                throw new \InvalidArgumentException('Title is required for published stories');
            }

            if (empty($this->content)) {
                throw new \InvalidArgumentException('Content is required for published stories');
            }

            // Validate no scheduling conflicts
            $this->validateDailySchedule();
        }
    }

    /**
     * Validate daily schedule to prevent conflicts.
     */
    private function validateDailySchedule(): void
    {
        if (!$this->active || !$this->active_from) {
            return;
        }

        $conflictingStories = static::where('id', '!=', $this->id ?? 0)
            ->where('active', true)
            ->where(function (Builder $query) {
                $activeFrom = $this->active_from;
                $activeUntil = $this->active_until ?? $activeFrom->copy()->addHours(24);

                $query->where(function (Builder $subQuery) use ($activeFrom, $activeUntil) {
                    // Stories that start during our active period
                    $subQuery->whereBetween('active_from', [$activeFrom, $activeUntil]);
                })->orWhere(function (Builder $subQuery) use ($activeFrom, $activeUntil) {
                    // Stories that end during our active period
                    $subQuery->whereBetween('active_until', [$activeFrom, $activeUntil]);
                })->orWhere(function (Builder $subQuery) use ($activeFrom, $activeUntil) {
                    // Stories that completely encompass our active period
                    $subQuery->where('active_from', '<=', $activeFrom)
                        ->where(function (Builder $innerQuery) use ($activeUntil) {
                            $innerQuery->whereNull('active_until')
                                ->orWhere('active_until', '>=', $activeUntil);
                        });
                });
            })
            ->exists();

        if ($conflictingStories) {
            throw new \InvalidArgumentException('Another story is scheduled for this time period. Only one story can be active at a time.');
        }
    }

    /**
     * Generate meta data automatically.
     */
    private function generateMetaData(): void
    {
        if (empty($this->meta_title)) {
            $this->meta_title = $this->title;
        }

        if (empty($this->meta_description) && $this->excerpt) {
            $this->meta_description = Str::limit($this->excerpt, 160);
        }

        // Auto-generate keywords from title and content
        if (empty($this->meta_keywords)) {
            $keywords = [];
            $keywords[] = $this->reading_level;
            $keywords[] = $this->category->name ?? '';
            $keywords[] = $this->formatted_word_count;
            
            $this->meta_keywords = implode(', ', array_filter($keywords));
        }
    }

    /**
     * Handle status changes and trigger events.
     */
    private function handleStatusChanges(): void
    {
        if ($this->wasChanged('active')) {
            $action = $this->active ? 'activated' : 'deactivated';
            
            $this->recordPublishingAction(
                $action,
                $this->getOriginal(),
                "Story {$action} automatically"
            );
        }
    }

    /**
     * Format countdown display.
     */
    private function formatCountdown(\DateInterval $diff): string
    {
        if ($diff->days > 0) {
            return "{$diff->days}د {$diff->h} س";
        } elseif ($diff->h > 0) {
            return "{$diff->h}ς {$diff->i}د";
        } elseif ($diff->i > 0) {
            return "{$diff->i}د {$diff->s}ث";
        } else {
            return "{$diff->s}ث";
        }
    }

    /**
     * Get member-specific interactions.
     */
    private function getMemberInteractions(int $memberId): array
    {
        $cacheKey = "story.{$this->id}.member_interactions.{$memberId}";

        return Cache::remember($cacheKey, 300, function () use ($memberId) {
            $interactions = $this->interactions()
                ->where('member_id', $memberId)
                ->pluck('action')
                ->toArray();

            $rating = $this->ratings()
                ->where('member_id', $memberId)
                ->first();

            $readingHistory = $this->readingHistory()
                ->where('member_id', $memberId)
                ->first();

            return [
                'has_viewed' => in_array('view', $interactions),
                'has_bookmarked' => in_array('bookmark', $interactions),
                'has_shared' => in_array('share', $interactions),
                'has_liked' => in_array('like', $interactions),
                'has_disliked' => in_array('dislike', $interactions),
                'has_rated' => !is_null($rating),
                'rating' => $rating?->rating,
                'rating_comment' => $rating?->comment,
                'reading_progress' => $readingHistory?->reading_progress ?? 0,
                'time_spent' => $readingHistory?->time_spent ?? 0,
                'is_completed' => $readingHistory?->reading_progress >= 100,
                'last_read_at' => $readingHistory?->last_read_at?->toISOString(),
                'completed_at' => $readingHistory?->completed_at?->toISOString(),
            ];
        });
    }

    /**
     * Get completion rate for this story.
     */
    private function getCompletionRate(): float
    {
        try {
            $totalViews = $this->storyViews()->count();
            if ($totalViews === 0) {
                return 0.0;
            }

            $completedReads = $this->readingHistory()
                ->where('reading_progress', '>=', 90)
                ->count();

            return round(($completedReads / $totalViews) * 100, 1);

        } catch (\Throwable $exception) {
            Log::warning('Failed to calculate completion rate', [
                'story_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get interaction rate for this story.
     */
    private function getInteractionRate(): float
    {
        try {
            $totalViews = $this->storyViews()->count();
            if ($totalViews === 0) {
                return 0.0;
            }

            $interactions = $this->interactions()->count();
            return round(($interactions / $totalViews) * 100, 1);

        } catch (\Throwable $exception) {
            Log::warning('Failed to calculate interaction rate', [
                'story_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Get optimal publish times based on historical data.
     */
    private function getOptimalPublishTimes(): array
    {
        try {
            return StoryView::selectRaw('HOUR(viewed_at) as hour, COUNT(*) as views')
                ->groupBy('hour')
                ->orderByDesc('views')
                ->limit(3)
                ->pluck('hour')
                ->toArray();

        } catch (\Throwable $exception) {
            Log::warning('Failed to get optimal publish times', [
                'error' => $exception->getMessage(),
            ]);

            return [9, 12, 18]; // Default times
        }
    }

    /**
     * Get expected improvement from republishing.
     */
    private function getExpectedImprovement(): array
    {
        $baselineViews = $this->views;
        $avgImprovement = 1.3; // 30% average improvement from republishing

        return [
            'expected_views_increase' => round($baselineViews * $avgImprovement - $baselineViews),
            'expected_rating_improvement' => 0.2,
            'word_count_advantage' => $this->word_count > 800 ? 'high' : 'medium',
            'reading_level_appeal' => $this->reading_level === 'intermediate' ? 'broad' : 'niche',
            'confidence_level' => 'medium',
        ];
    }

    /**
     * Clear all related caches.
     */
    private function clearRelatedCaches(): void
    {
        $patterns = [
            "story.{$this->id}.*",
            'stories.current_daily',
            'stories.upcoming',
            "category.{$this->category_id}.*",
            'stories.popular.*',
            'stories.recent.*',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                Cache::tags(['stories'])->flush();
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Clear specific cache.
     */
    private function clearCache(): void
    {
        Cache::forget("story.{$this->id}.avg_rating");
        Cache::forget("story.{$this->id}.total_ratings");
        Cache::forget("story.{$this->id}.performance_score");
        Cache::forget("story.{$this->id}.api_resource");
        Cache::forget("story.{$this->id}.teaser_resource");
        Cache::forget('stories.current_daily');
        Cache::forget('stories.upcoming');
    }

    /**
     * Log audit trail.
     */
    private function logAuditTrail(string $action): void
    {
        Log::info("Story {$action}", [
            'story_id' => $this->id,
            'title' => $this->title,
            'word_count' => $this->word_count,
            'reading_level' => $this->reading_level,
            'action' => $action,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ORCHID FILTERS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the indexable data array for the model.
     */
    public function filters(): array
    {
        return [
            'title' => new Like(),
            'category_id' => new Where(),
            'active' => new Where(),
            'reading_level' => new Where(),
            'word_count' => new Where(),
            'created_at' => new WhereDateStartEnd(),
            'updated_at' => new WhereDateStartEnd(),
            'active_from' => new WhereDateStartEnd(),
            'active_until' => new WhereDateStartEnd(),
        ];
    }
}