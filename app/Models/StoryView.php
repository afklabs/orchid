<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Story View Model
 * 
 * Tracks individual story views for analytics
 */
class StoryView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'story_id',
        'member_id',
        'device_id',
        'ip_address',
        'user_agent',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    /**
     * Get the story that was viewed.
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the member who viewed (if logged in).
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get view analytics for a story.
     */
    public static function getStoryAnalytics(int $storyId, string $period = 'week'): array
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            'year' => now()->subYear(),
            default => now()->subWeek(),
        };

        $views = static::where('story_id', $storyId)
            ->where('viewed_at', '>=', $startDate)
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as views, COUNT(DISTINCT device_id) as unique_views')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $hourlyViews = static::where('story_id', $storyId)
            ->where('viewed_at', '>=', $startDate)
            ->selectRaw('HOUR(viewed_at) as hour, COUNT(*) as views')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('views', 'hour');

        return [
            'total_views' => $views->sum('views'),
            'unique_views' => $views->sum('unique_views'),
            'daily_views' => $views->mapWithKeys(fn($item) => [$item->date => $item->views]),
            'hourly_distribution' => $hourlyViews,
            'peak_hour' => $hourlyViews->keys()->first(),
            'avg_daily_views' => $views->avg('views'),
        ];
    }

    /**
     * Get trending stories.
     */
    public static function getTrendingStories(int $limit = 10): array
    {
        return static::where('viewed_at', '>=', now()->subDays(7))
            ->select('story_id', DB::raw('COUNT(*) as recent_views'))
            ->groupBy('story_id')
            ->orderByDesc('recent_views')
            ->limit($limit)
            ->with('story')
            ->get()
            ->toArray();
    }

    /**
     * Get platform view statistics.
     */
    public static function getPlatformStats(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $week = now()->subWeek();
        $month = now()->subMonth();

        return [
            'today' => static::where('viewed_at', '>=', $today)->count(),
            'yesterday' => static::whereBetween('viewed_at', [$yesterday, $today])->count(),
            'this_week' => static::where('viewed_at', '>=', $week)->count(),
            'this_month' => static::where('viewed_at', '>=', $month)->count(),
            'unique_viewers_today' => static::where('viewed_at', '>=', $today)
                ->distinct('device_id')
                ->count(),
            'peak_viewing_hour' => static::where('viewed_at', '>=', $today)
                ->selectRaw('HOUR(viewed_at) as hour, COUNT(*) as views')
                ->groupBy('hour')
                ->orderByDesc('views')
                ->first()?->hour ?? 12,
        ];
    }
}