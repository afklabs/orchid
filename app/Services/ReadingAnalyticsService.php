<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Member, Story, MemberReadingStatistics, MemberReadingHistory, MemberStoryInteraction};
use Illuminate\Support\Facades\{Cache, DB, Log};
use Illuminate\Database\Eloquent\Collection;
use Carbon\{Carbon, CarbonPeriod};

/**
 * Reading Analytics Service
 * 
 * Comprehensive analytics service for reading statistics, word count tracking,
 * and performance insights. Provides detailed analytics for individual members
 * and global platform metrics.
 * 
 * Features:
 * - Reading statistics calculation
 * - Period-based analytics (day/week/month/year)
 * - Reading equivalent calculations
 * - Performance insights and trends
 * - Engagement metrics
 * - Predictive analytics
 * - Benchmarking and comparisons
 * 
 * @package App\Services
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class ReadingAnalyticsService
{
    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */

    /**
     * Cache TTL configurations
     */
    private const CACHE_TTL = [
        'member_analytics' => 3600,      // 1 hour
        'global_analytics' => 7200,      // 2 hours
        'trends' => 14400,              // 4 hours
        'predictions' => 86400,         // 24 hours
    ];

    /**
     * Analytics periods
     */
    private const PERIODS = [
        'day' => 1,
        'week' => 7,
        'month' => 30,
        'quarter' => 90,
        'year' => 365,
    ];

    /**
     * Reading speed benchmarks (words per minute)
     */
    private const READING_SPEEDS = [
        'slow' => 150,
        'average' => 200,
        'fast' => 300,
        'speed_reader' => 400,
    ];

    /**
     * Engagement thresholds
     */
    private const ENGAGEMENT_LEVELS = [
        'low' => 30,
        'medium' => 60,
        'high' => 80,
        'excellent' => 95,
    ];

    /*
    |--------------------------------------------------------------------------
    | MEMBER ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Get comprehensive member reading analytics.
     */
    public function getMemberAnalytics(int $memberId, string $period = 'month'): array
    {
        $cacheKey = "analytics.member.{$memberId}.{$period}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['member_analytics'], function () use ($memberId, $period) {
            $member = Member::findOrFail($memberId);
            
            return [
                'summary' => $this->getMemberSummary($memberId, $period),
                'reading_stats' => $this->getMemberReadingStats($memberId, $period),
                'word_count_analytics' => $this->getMemberWordCountAnalytics($memberId, $period),
                'engagement_metrics' => $this->getMemberEngagementMetrics($memberId, $period),
                'reading_patterns' => $this->getMemberReadingPatterns($memberId, $period),
                'achievements_progress' => $this->getMemberAchievementsProgress($memberId),
                'comparisons' => $this->getMemberComparisons($memberId, $period),
                'trends' => $this->getMemberTrends($memberId, $period),
                'recommendations' => $this->getMemberRecommendations($memberId, $period),
            ];
        });
    }

    /**
     * Get member summary statistics.
     */
    private function getMemberSummary(int $memberId, string $period): array
    {
        $stats = MemberReadingStatistics::getMemberStatistics($memberId, $period);
        
        return [
            'total_words_read' => $stats['total_words'],
            'total_stories_completed' => $stats['total_stories'],
            'total_reading_time' => $this->formatReadingTime($stats['total_time_minutes']),
            'average_daily_words' => $stats['daily_average'],
            'current_streak' => $stats['current_streak'],
            'longest_streak' => $stats['longest_streak'],
            'reading_days' => $stats['reading_days'],
            'reading_level' => $stats['reading_level'],
            'period_completion_rate' => $stats['completion_rate'],
        ];
    }

    /**
     * Get detailed member reading statistics.
     */
    private function getMemberReadingStats(int $memberId, string $period): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $readingHistory = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', $startDate)
            ->with('story')
            ->get();
        
        $completedStories = $readingHistory->where('reading_progress', '>=', 100);
        $inProgressStories = $readingHistory->whereBetween('reading_progress', [1, 99]);
        
        return [
            'stories_started' => $readingHistory->count(),
            'stories_completed' => $completedStories->count(),
            'stories_in_progress' => $inProgressStories->count(),
            'completion_rate' => $readingHistory->count() > 0 
                ? round(($completedStories->count() / $readingHistory->count()) * 100, 1) 
                : 0,
            'average_completion_time' => $this->calculateAverageCompletionTime($completedStories),
            'favorite_categories' => $this->getFavoriteCategories($readingHistory),
            'reading_velocity' => $this->calculateReadingVelocity($memberId, $period),
            'time_distribution' => $this->getTimeDistribution($readingHistory),
        ];
    }

    /**
     * Get member word count analytics.
     */
    private function getMemberWordCountAnalytics(int $memberId, string $period): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $statistics = MemberReadingStatistics::where('member_id', $memberId)
            ->where('date', '>=', $startDate)
            ->get();
        
        $totalWords = $statistics->sum('words_read');
        $readingDays = $statistics->where('words_read', '>', 0)->count();
        
        return [
            'total_words' => $totalWords,
            'daily_average' => $readingDays > 0 ? round($totalWords / $readingDays) : 0,
            'peak_day' => $this->getPeakReadingDay($statistics),
            'reading_equivalents' => $this->calculateReadingEquivalents($totalWords),
            'words_by_level' => $this->getWordsByReadingLevel($memberId, $startDate),
            'words_by_category' => $this->getWordsByCategory($memberId, $startDate),
            'reading_speed_analysis' => $this->analyzeReadingSpeed($statistics),
            'word_count_trend' => $this->getWordCountTrend($statistics),
        ];
    }

    /**
     * Get member engagement metrics.
     */
    private function getMemberEngagementMetrics(int $memberId, string $period): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $interactions = MemberStoryInteraction::where('member_id', $memberId)
            ->where('created_at', '>=', $startDate)
            ->get();
        
        $ratings = DB::table('member_story_ratings')
            ->where('member_id', $memberId)
            ->where('created_at', '>=', $startDate)
            ->get();
        
        return [
            'engagement_score' => $this->calculateEngagementScore($memberId, $period),
            'interaction_rate' => $this->calculateInteractionRate($memberId, $period),
            'actions' => [
                'likes' => $interactions->where('action', 'like')->count(),
                'bookmarks' => $interactions->where('action', 'bookmark')->count(),
                'shares' => $interactions->where('action', 'share')->count(),
                'ratings' => $ratings->count(),
            ],
            'average_rating_given' => round($ratings->avg('rating') ?? 0, 1),
            'engagement_level' => $this->determineEngagementLevel($memberId, $period),
            'social_impact' => $this->calculateSocialImpact($memberId, $period),
        ];
    }

    /**
     * Get member reading patterns.
     */
    private function getMemberReadingPatterns(int $memberId, string $period): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $readingData = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', $startDate)
            ->get();
        
        return [
            'preferred_reading_times' => $this->getPreferredReadingTimes($readingData),
            'reading_days_pattern' => $this->getReadingDaysPattern($memberId, $startDate),
            'session_duration_pattern' => $this->getSessionDurationPattern($readingData),
            'category_preferences' => $this->getCategoryPreferences($memberId, $startDate),
            'reading_level_progression' => $this->getReadingLevelProgression($memberId, $startDate),
            'consistency_score' => $this->calculateConsistencyScore($memberId, $period),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GLOBAL ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Get global platform analytics.
     */
    public function getGlobalAnalytics(string $period = 'month'): array
    {
        $cacheKey = "analytics.global.{$period}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL['global_analytics'], function () use ($period) {
            return [
                'platform_summary' => $this->getPlatformSummary($period),
                'reading_statistics' => $this->getGlobalReadingStatistics($period),
                'engagement_metrics' => $this->getGlobalEngagementMetrics($period),
                'content_performance' => $this->getContentPerformance($period),
                'member_segments' => $this->getMemberSegments($period),
                'trends_and_insights' => $this->getGlobalTrends($period),
            ];
        });
    }

    /**
     * Get platform summary.
     */
    private function getPlatformSummary(string $period): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $activeMembers = MemberReadingStatistics::where('date', '>=', $startDate)
            ->distinct('member_id')
            ->count('member_id');
        
        $totalWords = MemberReadingStatistics::where('date', '>=', $startDate)
            ->sum('words_read');
        
        $totalStories = MemberReadingStatistics::where('date', '>=', $startDate)
            ->sum('stories_completed');
        
        return [
            'active_members' => $activeMembers,
            'total_words_read' => $totalWords,
            'total_stories_completed' => $totalStories,
            'average_words_per_member' => $activeMembers > 0 ? round($totalWords / $activeMembers) : 0,
            'platform_reading_time' => $this->calculatePlatformReadingTime($period),
            'engagement_rate' => $this->calculatePlatformEngagementRate($period),
            'growth_metrics' => $this->calculateGrowthMetrics($period),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TRENDS AND PREDICTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Get member reading trends.
     */
    private function getMemberTrends(int $memberId, string $period): array
    {
        $data = $this->getTrendData($memberId, $period);
        
        return [
            'word_count_trend' => $this->analyzeWordCountTrend($data),
            'reading_time_trend' => $this->analyzeReadingTimeTrend($data),
            'engagement_trend' => $this->analyzeEngagementTrend($data),
            'streak_trend' => $this->analyzeStreakTrend($data),
            'prediction' => $this->predictNextPeriod($memberId, $data),
        ];
    }

    /**
     * Predict next period performance.
     */
    private function predictNextPeriod(int $memberId, array $historicalData): array
    {
        // Simple linear regression for prediction
        $wordCounts = array_column($historicalData, 'words_read');
        $trend = $this->calculateTrend($wordCounts);
        
        $lastValue = end($wordCounts) ?: 0;
        $predictedValue = max(0, $lastValue + $trend);
        
        return [
            'predicted_words' => round($predictedValue),
            'confidence_level' => $this->calculatePredictionConfidence($historicalData),
            'recommended_goal' => $this->calculateRecommendedGoal($memberId, $predictedValue),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RECOMMENDATIONS ENGINE
    |--------------------------------------------------------------------------
    */

    /**
     * Get personalized recommendations.
     */
    private function getMemberRecommendations(int $memberId, string $period): array
    {
        $analytics = $this->getMemberAnalytics($memberId, $period);
        $recommendations = [];
        
        // Reading goal recommendations
        if ($analytics['summary']['average_daily_words'] < 500) {
            $recommendations[] = [
                'type' => 'goal',
                'priority' => 'high',
                'title' => 'زيادة هدف القراءة اليومي',
                'description' => 'حاول قراءة 500 كلمة يومياً لتحسين مهاراتك',
                'action' => 'increase_daily_goal',
                'target_value' => 500,
            ];
        }
        
        // Streak recommendations
        if ($analytics['summary']['current_streak'] === 0) {
            $recommendations[] = [
                'type' => 'streak',
                'priority' => 'medium',
                'title' => 'ابدأ سلسلة قراءة جديدة',
                'description' => 'اقرأ كل يوم لبناء عادة القراءة',
                'action' => 'start_streak',
            ];
        }
        
        // Category diversity recommendations
        $favoriteCategories = $analytics['reading_stats']['favorite_categories'] ?? [];
        if (count($favoriteCategories) < 3) {
            $recommendations[] = [
                'type' => 'diversity',
                'priority' => 'low',
                'title' => 'استكشف فئات جديدة',
                'description' => 'جرب قراءة قصص من فئات مختلفة لتوسيع آفاقك',
                'action' => 'explore_categories',
            ];
        }
        
        // Reading speed recommendations
        $readingSpeed = $analytics['word_count_analytics']['reading_speed_analysis']['average_wpm'] ?? 200;
        if ($readingSpeed < self::READING_SPEEDS['average']) {
            $recommendations[] = [
                'type' => 'speed',
                'priority' => 'medium',
                'title' => 'حسّن سرعة القراءة',
                'description' => 'مارس تقنيات القراءة السريعة لزيادة كفاءتك',
                'action' => 'improve_reading_speed',
                'current_value' => $readingSpeed,
                'target_value' => self::READING_SPEEDS['average'],
            ];
        }
        
        return array_slice($recommendations, 0, 5); // Top 5 recommendations
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get period start date.
     */
    private function getPeriodStartDate(string $period): Carbon
    {
        $days = self::PERIODS[$period] ?? 30;
        return now()->subDays($days)->startOfDay();
    }

    /**
     * Format reading time.
     */
    private function formatReadingTime(int $minutes): array
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return [
            'total_minutes' => $minutes,
            'hours' => $hours,
            'minutes' => $remainingMinutes,
            'formatted' => $hours > 0 
                ? "{$hours}h {$remainingMinutes}m" 
                : "{$remainingMinutes}m",
        ];
    }

    /**
     * Calculate reading equivalents.
     */
    private function calculateReadingEquivalents(int $totalWords): array
    {
        $equivalents = [];
        
        // Books equivalent (average book = 80,000 words)
        $books = $totalWords / 80000;
        $equivalents['books'] = [
            'count' => round($books, 1),
            'description' => $books >= 1 ? "{$books} كتب" : "جزء من كتاب",
        ];
        
        // Pages equivalent (250 words per page)
        $pages = $totalWords / 250;
        $equivalents['pages'] = [
            'count' => round($pages),
            'description' => "{$pages} صفحة",
        ];
        
        // News articles equivalent (800 words per article)
        $articles = $totalWords / 800;
        $equivalents['articles'] = [
            'count' => round($articles),
            'description' => "{$articles} مقالة",
        ];
        
        return $equivalents;
    }

    /**
     * Calculate average completion time.
     */
    private function calculateAverageCompletionTime(Collection $completedStories): array
    {
        if ($completedStories->isEmpty()) {
            return ['minutes' => 0, 'formatted' => '0m'];
        }
        
        $avgMinutes = round($completedStories->avg('time_spent') / 60);
        
        return $this->formatReadingTime($avgMinutes);
    }

    /**
     * Get favorite categories.
     */
    private function getFavoriteCategories(Collection $readingHistory): array
    {
        return $readingHistory->groupBy('story.category_id')
            ->map->count()
            ->sortDesc()
            ->take(5)
            ->mapWithKeys(function ($count, $categoryId) {
                $category = Category::find($categoryId);
                return [$category?->name ?? 'Unknown' => $count];
            })
            ->toArray();
    }

    /**
     * Calculate reading velocity.
     */
    private function calculateReadingVelocity(int $memberId, string $period): array
    {
        $days = self::PERIODS[$period] ?? 30;
        $stats = MemberReadingStatistics::where('member_id', $memberId)
            ->where('date', '>=', now()->subDays($days))
            ->get();
        
        $totalWords = $stats->sum('words_read');
        $activeDays = $stats->where('words_read', '>', 0)->count();
        
        return [
            'words_per_day' => $activeDays > 0 ? round($totalWords / $activeDays) : 0,
            'words_per_week' => round($totalWords / ($days / 7)),
            'trend' => $this->calculateTrend($stats->pluck('words_read')->toArray()),
        ];
    }

    /**
     * Calculate trend from data points.
     */
    private function calculateTrend(array $dataPoints): float
    {
        if (count($dataPoints) < 2) {
            return 0;
        }
        
        // Simple linear regression
        $n = count($dataPoints);
        $sumX = array_sum(array_keys($dataPoints));
        $sumY = array_sum($dataPoints);
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($dataPoints as $x => $y) {
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        
        if ($denominator == 0) {
            return 0;
        }
        
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        
        return round($slope, 2);
    }

    /**
     * Get peak reading day.
     */
    private function getPeakReadingDay(Collection $statistics): array
    {
        $peak = $statistics->sortByDesc('words_read')->first();
        
        if (!$peak) {
            return ['date' => null, 'words' => 0];
        }
        
        return [
            'date' => $peak->date->toDateString(),
            'words' => $peak->words_read,
            'day_name' => $peak->date->format('l'),
        ];
    }

    /**
     * Get time distribution.
     */
    private function getTimeDistribution(Collection $readingHistory): array
    {
        $distribution = [];
        
        foreach ($readingHistory as $history) {
            $hour = $history->last_read_at->hour;
            $period = $this->getTimePeriod($hour);
            
            if (!isset($distribution[$period])) {
                $distribution[$period] = 0;
            }
            
            $distribution[$period]++;
        }
        
        return $distribution;
    }

    /**
     * Get time period name.
     */
    private function getTimePeriod(int $hour): string
    {
        if ($hour >= 5 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'afternoon';
        } elseif ($hour >= 17 && $hour < 21) {
            return 'evening';
        } else {
            return 'night';
        }
    }

    /**
     * Calculate engagement score.
     */
    private function calculateEngagementScore(int $memberId, string $period): float
    {
        $startDate = $this->getPeriodStartDate($period);
        
        // Get all reading activities
        $readingDays = MemberReadingStatistics::where('member_id', $memberId)
            ->where('date', '>=', $startDate)
            ->where('words_read', '>', 0)
            ->count();
        
        $totalDays = $startDate->diffInDays(now());
        $consistencyScore = $totalDays > 0 ? ($readingDays / $totalDays) * 40 : 0;
        
        // Get interaction rate
        $interactions = MemberStoryInteraction::where('member_id', $memberId)
            ->where('created_at', '>=', $startDate)
            ->count();
        
        $storiesRead = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', $startDate)
            ->count();
        
        $interactionScore = $storiesRead > 0 ? min(30, ($interactions / $storiesRead) * 30) : 0;
        
        // Get completion rate
        $completionRate = MemberReadingHistory::where('member_id', $memberId)
            ->where('last_read_at', '>=', $startDate)
            ->where('reading_progress', '>=', 100)
            ->count();
        
        $completionScore = $storiesRead > 0 ? ($completionRate / $storiesRead) * 30 : 0;
        
        return round($consistencyScore + $interactionScore + $completionScore, 1);
    }

    /**
     * Determine engagement level.
     */
    private function determineEngagementLevel(int $memberId, string $period): string
    {
        $score = $this->calculateEngagementScore($memberId, $period);
        
        foreach (array_reverse(self::ENGAGEMENT_LEVELS, true) as $level => $threshold) {
            if ($score >= $threshold) {
                return $level;
            }
        }
        
        return 'low';
    }

    /**
     * Get member comparisons.
     */
    private function getMemberComparisons(int $memberId, string $period): array
    {
        $memberStats = MemberReadingStatistics::getMemberStatistics($memberId, $period);
        
        // Get average statistics for all members
        $startDate = $this->getPeriodStartDate($period);
        $avgStats = MemberReadingStatistics::where('date', '>=', $startDate)
            ->selectRaw('AVG(words_read) as avg_words, AVG(stories_completed) as avg_stories')
            ->first();
        
        return [
            'vs_average' => [
                'words_read' => [
                    'member' => $memberStats['total_words'],
                    'average' => round($avgStats->avg_words ?? 0),
                    'percentage' => $avgStats->avg_words > 0 
                        ? round(($memberStats['total_words'] / $avgStats->avg_words) * 100) 
                        : 0,
                ],
                'stories_completed' => [
                    'member' => $memberStats['total_stories'],
                    'average' => round($avgStats->avg_stories ?? 0),
                    'percentage' => $avgStats->avg_stories > 0 
                        ? round(($memberStats['total_stories'] / $avgStats->avg_stories) * 100) 
                        : 0,
                ],
            ],
            'percentile_rank' => $this->calculatePercentileRank($memberId, $period),
            'peer_group_comparison' => $this->getPeerGroupComparison($memberId, $period),
        ];
    }

    /**
     * Calculate percentile rank.
     */
    private function calculatePercentileRank(int $memberId, string $period): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $memberWords = MemberReadingStatistics::where('member_id', $memberId)
            ->where('date', '>=', $startDate)
            ->sum('words_read');
        
        $totalMembers = MemberReadingStatistics::where('date', '>=', $startDate)
            ->distinct('member_id')
            ->count('member_id');
        
        $rank = MemberReadingStatistics::where('date', '>=', $startDate)
            ->groupBy('member_id')
            ->havingRaw('SUM(words_read) > ?', [$memberWords])
            ->count();
        
        $percentile = $totalMembers > 0 
            ? round((($totalMembers - $rank) / $totalMembers) * 100) 
            : 0;
        
        return [
            'percentile' => $percentile,
            'rank' => $rank + 1,
            'total_members' => $totalMembers,
        ];
    }

    /**
     * Analyze reading speed.
     */
    private function analyzeReadingSpeed(Collection $statistics): array
    {
        $totalWords = $statistics->sum('words_read');
        $totalMinutes = $statistics->sum('reading_time_minutes');
        
        if ($totalMinutes === 0) {
            return [
                'average_wpm' => 0,
                'speed_category' => 'unknown',
                'improvement_potential' => 0,
            ];
        }
        
        $averageWpm = round($totalWords / $totalMinutes);
        $speedCategory = $this->categorizeReadingSpeed($averageWpm);
        
        return [
            'average_wpm' => $averageWpm,
            'speed_category' => $speedCategory,
            'improvement_potential' => $this->calculateSpeedImprovement($averageWpm),
            'comparison_to_average' => round(($averageWpm / self::READING_SPEEDS['average']) * 100),
        ];
    }

    /**
     * Categorize reading speed.
     */
    private function categorizeReadingSpeed(int $wpm): string
    {
        if ($wpm >= self::READING_SPEEDS['speed_reader']) {
            return 'speed_reader';
        } elseif ($wpm >= self::READING_SPEEDS['fast']) {
            return 'fast';
        } elseif ($wpm >= self::READING_SPEEDS['average']) {
            return 'average';
        } else {
            return 'slow';
        }
    }

    /**
     * Calculate speed improvement potential.
     */
    private function calculateSpeedImprovement(int $currentWpm): int
    {
        $targetWpm = self::READING_SPEEDS['fast'];
        
        if ($currentWpm >= $targetWpm) {
            return 0;
        }
        
        return round((($targetWpm - $currentWpm) / $currentWpm) * 100);
    }

    /**
     * Get peer group comparison.
     */
    private function getPeerGroupComparison(int $memberId, string $period): array
    {
        // Find members with similar reading levels
        $member = Member::find($memberId);
        $readingLevel = MemberReadingStatistics::where('member_id', $memberId)
            ->latest()
            ->value('reading_level') ?? 'intermediate';
        
        $peerStats = MemberReadingStatistics::where('reading_level', $readingLevel)
            ->where('date', '>=', $this->getPeriodStartDate($period))
            ->where('member_id', '!=', $memberId)
            ->selectRaw('AVG(words_read) as avg_words, AVG(reading_streak_days) as avg_streak')
            ->first();
        
        return [
            'reading_level' => $readingLevel,
            'peer_average_words' => round($peerStats->avg_words ?? 0),
            'peer_average_streak' => round($peerStats->avg_streak ?? 0),
        ];
    }
}