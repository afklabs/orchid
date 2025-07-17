<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Orchid\Filters\{Filterable, Types\Where, Types\WhereDateStartEnd};
use Orchid\Screen\AsSource;

/**
 * Member Reading Statistics Model
 * 
 * Tracks daily reading statistics for members including word count,
 * stories completed, reading time, and streak management.
 * 
 * Features:
 * - Daily word count tracking
 * - Reading streak management
 * - Story completion tracking
 * - Reading time analytics
 * - Performance metrics
 * - Achievement triggers
 * 
 * Business Intelligence:
 * - Reading habit analysis
 * - Progress tracking
 * - Goal achievement monitoring
 * - User engagement metrics
 * 
 * @property int $id
 * @property int $member_id
 * @property \Carbon\Carbon $date
 * @property int $words_read
 * @property int $stories_completed
 * @property int $reading_time_minutes
 * @property int $reading_streak_days
 * @property \Carbon\Carbon|null $streak_start_date
 * @property \Carbon\Carbon|null $streak_end_date
 * @property int $longest_streak_days
 * @property string $reading_level
 * @property array|null $meta_data
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * 
 * @package App\Models
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class MemberReadingStatistics extends Model implements \Orchid\Screen\AsSource
{
    use HasFactory, Filterable, AsSource;

    /*
    |--------------------------------------------------------------------------
    | CONSTANTS & CONFIGURATION
    |--------------------------------------------------------------------------
    */

    /**
     * Cache TTL for different operations
     */
    private const CACHE_TTL = [
        'daily_stats' => 3600,     // 1 hour
        'weekly_stats' => 7200,    // 2 hours
        'monthly_stats' => 14400,  // 4 hours
        'streak_data' => 1800,     // 30 minutes
        'leaderboard' => 3600,     // 1 hour
    ];

    /**
     * Reading goals and milestones
     */
    private const READING_GOALS = [
        'daily' => [
            'beginner' => 500,
            'intermediate' => 1000,
            'advanced' => 2000,
            'expert' => 3000,
        ],
        'weekly' => [
            'beginner' => 3500,
            'intermediate' => 7000,
            'advanced' => 14000,
            'expert' => 21000,
        ],
        'monthly' => [
            'beginner' => 15000,
            'intermediate' => 30000,
            'advanced' => 60000,
            'expert' => 90000,
        ],
    ];

    /**
     * Reading equivalents for engagement
     */
    private const READING_EQUIVALENTS = [
        'short_story' => ['words' => 2000, 'name' => 'قصة قصيرة', 'pages' => 8],
        'novella' => ['words' => 20000, 'name' => 'رواية قصيرة', 'pages' => 80],
        'novel' => ['words' => 80000, 'name' => 'رواية كاملة', 'pages' => 320],
        'epic' => ['words' => 150000, 'name' => 'رواية ملحمية', 'pages' => 600],
    ];

    /**
     * Streak milestone rewards
     */
    private const STREAK_MILESTONES = [
        3 => 'streak_starter',
        7 => 'week_warrior',
        14 => 'fortnight_fighter',
        30 => 'monthly_master',
        60 => 'reading_champion',
        90 => 'quarter_king',
        180 => 'semester_scholar',
        365 => 'year_legend',
    ];

    /*
    |--------------------------------------------------------------------------
    | MODEL CONFIGURATION
    |--------------------------------------------------------------------------
    */

    /**
     * The database table used by the model.
     */
    protected $table = 'member_reading_statistics';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_id',
        'date',
        'words_read',
        'stories_completed',
        'reading_time_minutes',
        'reading_streak_days',
        'streak_start_date',
        'streak_end_date',
        'longest_streak_days',
        'reading_level',
        'meta_data',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'member_id' => 'integer',
        'date' => 'date',
        'words_read' => 'integer',
        'stories_completed' => 'integer',
        'reading_time_minutes' => 'integer',
        'reading_streak_days' => 'integer',
        'longest_streak_days' => 'integer',
        'streak_start_date' => 'date',
        'streak_end_date' => 'date',
        'meta_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'words_read' => 0,
        'stories_completed' => 0,
        'reading_time_minutes' => 0,
        'reading_streak_days' => 0,
        'longest_streak_days' => 0,
        'reading_level' => 'intermediate',
        'meta_data' => '{}',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'reading_goal_progress',
        'reading_equivalent',
        'words_per_minute',
        'efficiency_score',
        'streak_status',
        'next_milestone',
        'daily_ranking',
    ];

    /**
     * Fields available for filtering.
     */
    protected $allowedFilters = [
        'member_id',
        'date',
        'words_read',
        'stories_completed',
        'reading_streak_days',
        'reading_level',
    ];

    /**
     * Fields available for sorting.
     */
    protected $allowedSorts = [
        'date',
        'words_read',
        'stories_completed',
        'reading_time_minutes',
        'reading_streak_days',
        'longest_streak_days',
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
        static::creating(function (MemberReadingStatistics $stats) {
            $stats->validateUniqueDate();
            $stats->determineReadingLevel();
        });

        static::updating(function (MemberReadingStatistics $stats) {
            $stats->determineReadingLevel();
            $stats->updateStreakData();
        });

        static::saved(function (MemberReadingStatistics $stats) {
            $stats->clearRelatedCaches();
            $stats->checkAndTriggerAchievements();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the member that owns this statistic.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for today's statistics.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope for this week's statistics.
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope for this month's statistics.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('date', now()->month)
            ->whereYear('date', now()->year);
    }

    /**
     * Scope for date range.
     */
    public function scopeBetweenDates(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope for active readers (read something).
     */
    public function scopeActiveReaders(Builder $query): Builder
    {
        return $query->where('words_read', '>', 0);
    }

    /**
     * Scope for streak maintainers.
     */
    public function scopeStreakMaintainers(Builder $query, int $minimumStreak = 7): Builder
    {
        return $query->where('reading_streak_days', '>=', $minimumStreak);
    }

    /**
     * Scope for top performers.
     */
    public function scopeTopPerformers(Builder $query, string $metric = 'words_read'): Builder
    {
        return $query->orderByDesc($metric);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Get reading goal progress for today.
     */
    protected function readingGoalProgress(): Attribute
    {
        return Attribute::make(
            get: function () {
                $level = $this->reading_level;
                $dailyGoal = self::READING_GOALS['daily'][$level] ?? self::READING_GOALS['daily']['intermediate'];
                $progress = $this->words_read / $dailyGoal * 100;
                
                return [
                    'current' => $this->words_read,
                    'goal' => $dailyGoal,
                    'progress_percentage' => round(min(100, $progress), 1),
                    'remaining' => max(0, $dailyGoal - $this->words_read),
                    'exceeded' => $this->words_read > $dailyGoal,
                    'exceeded_by' => max(0, $this->words_read - $dailyGoal),
                ];
            }
        );
    }

    /**
     * Get reading equivalent for words read.
     */
    protected function readingEquivalent(): Attribute
    {
        return Attribute::make(
            get: function () {
                $wordCount = $this->words_read;
                
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
                    'pages' => max(1, round($wordCount / 250)), // 250 words per page
                    'description' => "{$wordCount} كلمة",
                ];
            }
        );
    }

    /**
     * Calculate words per minute.
     */
    protected function wordsPerMinute(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->reading_time_minutes === 0) {
                    return 0;
                }
                
                return round($this->words_read / $this->reading_time_minutes);
            }
        );
    }

    /**
     * Calculate efficiency score.
     */
    protected function efficiencyScore(): Attribute
    {
        return Attribute::make(
            get: function () {
                $cacheKey = "member.{$this->member_id}.efficiency_score.{$this->date->toDateString()}";
                
                return Cache::remember($cacheKey, self::CACHE_TTL['daily_stats'], function () {
                    return $this->calculateEfficiencyScore();
                });
            }
        );
    }

    /**
     * Get streak status.
     */
    protected function streakStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->reading_streak_days === 0) {
                    return 'inactive';
                }
                
                // Check if streak is active (read yesterday)
                $yesterday = static::where('member_id', $this->member_id)
                    ->whereDate('date', yesterday())
                    ->where('words_read', '>', 0)
                    ->exists();
                
                if ($yesterday || $this->date->isToday()) {
                    return 'active';
                }
                
                return 'broken';
            }
        );
    }

    /**
     * Get next milestone information.
     */
    protected function nextMilestone(): Attribute
    {
        return Attribute::make(
            get: function () {
                foreach (self::STREAK_MILESTONES as $days => $achievement) {
                    if ($this->reading_streak_days < $days) {
                        return [
                            'days' => $days,
                            'achievement' => $achievement,
                            'days_remaining' => $days - $this->reading_streak_days,
                            'progress_percentage' => round(($this->reading_streak_days / $days) * 100, 1),
                        ];
                    }
                }
                
                return null; // All milestones achieved
            }
        );
    }

    /**
     * Get daily ranking.
     */
    protected function dailyRanking(): Attribute
    {
        return Attribute::make(
            get: function () {
                $cacheKey = "rankings.daily.{$this->date->toDateString()}.member.{$this->member_id}";
                
                return Cache::remember($cacheKey, self::CACHE_TTL['leaderboard'], function () {
                    $rank = static::whereDate('date', $this->date)
                        ->where('words_read', '>', $this->words_read)
                        ->count() + 1;
                    
                    $totalReaders = static::whereDate('date', $this->date)
                        ->where('words_read', '>', 0)
                        ->count();
                    
                    return [
                        'rank' => $rank,
                        'total_readers' => $totalReaders,
                        'percentile' => $totalReaders > 0 ? round((($totalReaders - $rank + 1) / $totalReaders) * 100) : 0,
                    ];
                });
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CORE FUNCTIONALITY
    |--------------------------------------------------------------------------
    */

    /**
     * Record story completion with word count tracking.
     */
    public static function recordStoryCompletion(int $memberId, Story $story, int $timeSpent = 0): void
    {
        DB::transaction(function () use ($memberId, $story, $timeSpent) {
            $stats = static::firstOrCreate(
                [
                    'member_id' => $memberId,
                    'date' => today(),
                ],
                [
                    'words_read' => 0,
                    'stories_completed' => 0,
                    'reading_time_minutes' => 0,
                    'reading_streak_days' => 0,
                ]
            );

            // Update statistics
            $stats->increment('words_read', $story->word_count);
            $stats->increment('stories_completed');
            $stats->increment('reading_time_minutes', max(1, round($timeSpent / 60)));

            // Update streak
            $stats->updateReadingStreak();

            // Log completion
            Log::info('Story completion recorded in statistics', [
                'member_id' => $memberId,
                'story_id' => $story->id,
                'word_count' => $story->word_count,
                'date' => today()->toDateString(),
                'new_total_words' => $stats->words_read,
            ]);
        });
    }

    /**
     * Update reading streak calculation.
     */
    public function updateReadingStreak(): void
    {
        if ($this->words_read === 0) {
            return; // No reading, no streak
        }

        // Get yesterday's statistics
        $yesterday = static::where('member_id', $this->member_id)
            ->whereDate('date', $this->date->copy()->subDay())
            ->first();

        if ($yesterday && $yesterday->words_read > 0) {
            // Continue the streak
            $this->reading_streak_days = $yesterday->reading_streak_days + 1;
            
            // Update streak dates
            if (!$this->streak_start_date) {
                $this->streak_start_date = $yesterday->streak_start_date ?? $yesterday->date;
            }
        } else {
            // Start new streak
            $this->reading_streak_days = 1;
            $this->streak_start_date = $this->date;
            $this->streak_end_date = null;
        }

        // Update longest streak
        if ($this->reading_streak_days > $this->longest_streak_days) {
            $this->longest_streak_days = $this->reading_streak_days;
        }

        $this->save();
    }

    /**
     * Get member statistics for a period.
     */
    public static function getMemberStatistics(int $memberId, string $period = 'month'): array
    {
        $cacheKey = "member.{$memberId}.statistics.{$period}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL["{$period}ly_stats"] ?? 3600, function () use ($memberId, $period) {
            $query = static::where('member_id', $memberId);
            
            switch ($period) {
                case 'day':
                    $query->today();
                    break;
                case 'week':
                    $query->thisWeek();
                    break;
                case 'month':
                    $query->thisMonth();
                    break;
                case 'year':
                    $query->whereYear('date', now()->year);
                    break;
                default:
                    $query->thisMonth();
            }
            
            $stats = $query->get();
            
            return [
                'total_words' => $stats->sum('words_read'),
                'total_stories' => $stats->sum('stories_completed'),
                'total_time_minutes' => $stats->sum('reading_time_minutes'),
                'reading_days' => $stats->where('words_read', '>', 0)->count(),
                'current_streak' => static::getCurrentStreak($memberId),
                'longest_streak' => $stats->max('longest_streak_days') ?? 0,
                'daily_average' => $stats->count() > 0 ? round($stats->avg('words_read')) : 0,
                'completion_rate' => static::getCompletionRate($memberId, $period),
                'reading_level' => static::determineOverallReadingLevel($stats),
                'achievements_earned' => static::getEarnedAchievements($memberId, $period),
            ];
        });
    }

    /**
     * Get current active streak.
     */
    public static function getCurrentStreak(int $memberId): int
    {
        $latest = static::where('member_id', $memberId)
            ->whereDate('date', today())
            ->orWhereDate('date', yesterday())
            ->orderByDesc('date')
            ->first();
        
        if (!$latest || $latest->words_read === 0) {
            return 0;
        }
        
        // If the latest record is from today or yesterday with reading, streak is active
        if ($latest->date->isToday() || ($latest->date->isYesterday() && $latest->words_read > 0)) {
            return $latest->reading_streak_days;
        }
        
        return 0;
    }

    /**
     * Get global leaderboard.
     */
    public static function getLeaderboard(string $period = 'day', int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "leaderboard.{$period}.top_{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['leaderboard'], function () use ($period, $limit) {
            $query = static::with('member:id,name,avatar_url');
            
            switch ($period) {
                case 'day':
                    $query->today();
                    break;
                case 'week':
                    $query->thisWeek()
                        ->selectRaw('member_id, SUM(words_read) as total_words, SUM(stories_completed) as total_stories')
                        ->groupBy('member_id');
                    break;
                case 'month':
                    $query->thisMonth()
                        ->selectRaw('member_id, SUM(words_read) as total_words, SUM(stories_completed) as total_stories')
                        ->groupBy('member_id');
                    break;
                case 'all_time':
                    $query->selectRaw('member_id, SUM(words_read) as total_words, SUM(stories_completed) as total_stories')
                        ->groupBy('member_id');
                    break;
            }
            
            return $query->orderByDesc($period === 'day' ? 'words_read' : 'total_words')
                ->limit($limit)
                ->get();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate efficiency score based on multiple factors.
     */
    private function calculateEfficiencyScore(): float
    {
        $factors = [];
        
        // Words per minute efficiency (compared to average reading speed)
        $avgReadingSpeed = 200; // words per minute
        $wpmScore = min(100, ($this->words_per_minute / $avgReadingSpeed) * 100);
        $factors['speed'] = $wpmScore * 0.3;
        
        // Story completion efficiency
        $completionScore = $this->stories_completed > 0 ? 100 : 0;
        $factors['completion'] = $completionScore * 0.3;
        
        // Goal achievement
        $goalProgress = $this->reading_goal_progress['progress_percentage'];
        $factors['goal'] = min(100, $goalProgress) * 0.2;
        
        // Consistency (streak)
        $streakScore = min(100, ($this->reading_streak_days / 30) * 100);
        $factors['consistency'] = $streakScore * 0.2;
        
        return round(array_sum($factors), 1);
    }

    /**
     * Determine reading level based on performance.
     */
    private function determineReadingLevel(): void
    {
        // Base it on daily average words read over last 7 days
        $avgWords = static::where('member_id', $this->member_id)
            ->whereBetween('date', [now()->subDays(7), now()])
            ->avg('words_read') ?? 0;
        
        if ($avgWords >= self::READING_GOALS['daily']['expert']) {
            $this->reading_level = 'expert';
        } elseif ($avgWords >= self::READING_GOALS['daily']['advanced']) {
            $this->reading_level = 'advanced';
        } elseif ($avgWords >= self::READING_GOALS['daily']['intermediate']) {
            $this->reading_level = 'intermediate';
        } else {
            $this->reading_level = 'beginner';
        }
    }

    /**
     * Validate unique date per member.
     */
    private function validateUniqueDate(): void
    {
        $exists = static::where('member_id', $this->member_id)
            ->whereDate('date', $this->date)
            ->where('id', '!=', $this->id ?? 0)
            ->exists();
        
        if ($exists) {
            throw new \InvalidArgumentException(
                'Reading statistics already exist for this member on this date'
            );
        }
    }

    /**
     * Update streak data.
     */
    private function updateStreakData(): void
    {
        // Check if streak is broken
        if ($this->words_read === 0 && $this->reading_streak_days > 0) {
            $this->streak_end_date = $this->date;
            $this->reading_streak_days = 0;
        }
    }

    /**
     * Check and trigger achievements.
     */
    private function checkAndTriggerAchievements(): void
    {
        try {
            // Check streak milestones
            if (isset(self::STREAK_MILESTONES[$this->reading_streak_days])) {
                $achievement = self::STREAK_MILESTONES[$this->reading_streak_days];
                MemberReadingAchievements::awardAchievement(
                    $this->member_id,
                    $achievement,
                    ['streak_days' => $this->reading_streak_days]
                );
            }
            
            // Check daily word count achievements
            if ($this->words_read >= 5000) {
                MemberReadingAchievements::awardAchievement(
                    $this->member_id,
                    'bookworm',
                    ['words_read' => $this->words_read]
                );
            }
            
            // Check speed reading achievements
            if ($this->words_per_minute >= 300) {
                MemberReadingAchievements::awardAchievement(
                    $this->member_id,
                    'speed_reader',
                    ['wpm' => $this->words_per_minute]
                );
            }
            
        } catch (\Throwable $exception) {
            Log::warning('Failed to check achievements', [
                'member_id' => $this->member_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get equivalent description.
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
     * Get completion rate for period.
     */
    private static function getCompletionRate(int $memberId, string $period): float
    {
        $daysInPeriod = match($period) {
            'week' => 7,
            'month' => 30,
            'year' => 365,
            default => 30,
        };
        
        $readingDays = static::where('member_id', $memberId)
            ->whereBetween('date', [now()->subDays($daysInPeriod), now()])
            ->where('words_read', '>', 0)
            ->count();
        
        return round(($readingDays / $daysInPeriod) * 100, 1);
    }

    /**
     * Determine overall reading level from collection.
     */
    private static function determineOverallReadingLevel(\Illuminate\Database\Eloquent\Collection $stats): string
    {
        if ($stats->isEmpty()) {
            return 'beginner';
        }
        
        $avgWords = $stats->avg('words_read');
        
        if ($avgWords >= self::READING_GOALS['daily']['expert']) {
            return 'expert';
        } elseif ($avgWords >= self::READING_GOALS['daily']['advanced']) {
            return 'advanced';
        } elseif ($avgWords >= self::READING_GOALS['daily']['intermediate']) {
            return 'intermediate';
        }
        
        return 'beginner';
    }

    /**
     * Get earned achievements for period.
     */
    private static function getEarnedAchievements(int $memberId, string $period): int
    {
        // This will be implemented with MemberReadingAchievements model
        return 0;
    }

    /**
     * Clear related caches.
     */
    private function clearRelatedCaches(): void
    {
        $patterns = [
            "member.{$this->member_id}.statistics.*",
            "member.{$this->member_id}.efficiency_score.*",
            "rankings.daily.{$this->date->toDateString()}.*",
            "leaderboard.*",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                Cache::tags(['reading_statistics'])->flush();
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API RESOURCE METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Convert to API resource format.
     */
    public function toApiResource(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'words_read' => $this->words_read,
            'stories_completed' => $this->stories_completed,
            'reading_time_minutes' => $this->reading_time_minutes,
            'reading_streak_days' => $this->reading_streak_days,
            'reading_level' => $this->reading_level,
            'goal_progress' => $this->reading_goal_progress,
            'reading_equivalent' => $this->reading_equivalent,
            'words_per_minute' => $this->words_per_minute,
            'efficiency_score' => $this->efficiency_score,
            'streak_status' => $this->streak_status,
            'next_milestone' => $this->next_milestone,
            'daily_ranking' => $this->daily_ranking,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ORCHID SCREEN METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the indexable data array for Orchid.
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}