<?php

namespace App\Services;

use App\Models\{Story, StoryView, StoryRatingAggregate};
use Illuminate\Support\Facades\{Cache, DB};
use Carbon\Carbon;

/**
 * Performance Metrics Service
 * 
 * Centralized service for calculating and managing story performance metrics
 */
class PerformanceMetricsService
{
    /**
     * Calculate comprehensive performance metrics for a story.
     */
    public function calculateStoryMetrics(int $storyId): array
    {
        $story = Story::findOrFail($storyId);
        
        return [
            'basic_metrics' => $this->getBasicMetrics($story),
            'engagement_metrics' => $this->getEngagementMetrics($story),
            'reading_metrics' => $this->getReadingMetrics($story),
            'trending_metrics' => $this->getTrendingMetrics($story),
            'performance_score' => $this->calculatePerformanceScore($story),
            'recommendations' => $this->getRecommendations($story),
        ];
    }

    /**
     * Get basic story metrics.
     */
    private function getBasicMetrics(Story $story): array
    {
        $ratingAggregate = $story->ratingAggregate;
        
        return [
            'views' => $story->views,
            'word_count' => $story->word_count,
            'reading_time' => $story->reading_time_minutes,
            'reading_level' => $story->reading_level,
            'average_rating' => $ratingAggregate?->average_rating ?? 0,
            'total_ratings' => $ratingAggregate?->total_ratings ?? 0,
            'rating_distribution' => $ratingAggregate?->getRatingDistribution() ?? [],
        ];
    }

    /**
     * Get engagement metrics.
     */
    private function getEngagementMetrics(Story $story): array
    {
        $totalViews = $story->storyViews()->count();
        $totalRatings = $story->ratings()->count();
        $totalBookmarks = $story->interactions()->where('action', 'bookmark')->count();
        $totalShares = $story->interactions()->where('action', 'share')->count();
        
        $engagementRate = $totalViews > 0 
            ? (($totalRatings + $totalBookmarks + $totalShares) / $totalViews) * 100 
            : 0;

        return [
            'total_interactions' => $totalRatings + $totalBookmarks + $totalShares,
            'ratings_count' => $totalRatings,
            'bookmarks_count' => $totalBookmarks,
            'shares_count' => $totalShares,
            'engagement_rate' => round($engagementRate, 2),
            'interactions_per_view' => $totalViews > 0 ? round(($totalRatings + $totalBookmarks + $totalShares) / $totalViews, 3) : 0,
        ];
    }

    /**
     * Get reading metrics.
     */
    private function getReadingMetrics(Story $story): array
    {
        $totalReaders = $story->readingHistory()->distinct('member_id')->count();
        $completedReaders = $story->readingHistory()
            ->where('reading_progress', '>=', 100)
            ->distinct('member_id')
            ->count();
        
        $avgProgress = $story->readingHistory()->avg('reading_progress') ?? 0;
        $avgTimeSpent = $story->readingHistory()->avg('time_spent') ?? 0;
        
        $completionRate = $totalReaders > 0 ? ($completedReaders / $totalReaders) * 100 : 0;

        return [
            'total_readers' => $totalReaders,
            'completed_readers' => $completedReaders,
            'completion_rate' => round($completionRate, 2),
            'average_progress' => round($avgProgress, 2),
            'average_time_spent' => round($avgTimeSpent / 60, 2), // Convert to minutes
            'bounce_rate' => $this->calculateBounceRate($story),
        ];
    }

    /**
     * Get trending metrics.
     */
    private function getTrendingMetrics(Story $story): array
    {
        $recentViews = $story->storyViews()
            ->where('viewed_at', '>=', now()->subDays(7))
            ->count();
        
        $totalViews = $story->storyViews()->count();
        $recentRatings = $story->ratings()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        
        $viewsTrend = $totalViews > 0 ? ($recentViews / $totalViews) * 100 : 0;
        $trendingScore = $viewsTrend + ($recentRatings * 10);

        return [
            'recent_views' => $recentViews,
            'views_trend' => round($viewsTrend, 2),
            'recent_ratings' => $recentRatings,
            'trending_score' => round($trendingScore, 2),
            'is_trending' => $trendingScore >= 75,
            'momentum' => $this->calculateMomentum($story),
        ];
    }

    /**
     * Calculate overall performance score.
     */
    private function calculatePerformanceScore(Story $story): array
    {
        $basicMetrics = $this->getBasicMetrics($story);
        $engagementMetrics = $this->getEngagementMetrics($story);
        $readingMetrics = $this->getReadingMetrics($story);
        $trendingMetrics = $this->getTrendingMetrics($story);

        // Normalized scores (0-100)
        $viewsScore = min(($basicMetrics['views'] / 100) * 30, 30);
        $ratingScore = ($basicMetrics['average_rating'] / 5) * 20;
        $completionScore = ($readingMetrics['completion_rate'] / 100) * 25;
        $engagementScore = min($engagementMetrics['engagement_rate'] * 0.15, 15);
        $freshnessScore = max(10 - ($story->created_at->diffInDays(now()) / 30), 0);

        $totalScore = $viewsScore + $ratingScore + $completionScore + $engagementScore + $freshnessScore;

        return [
            'overall_score' => round($totalScore, 2),
            'component_scores' => [
                'views' => round($viewsScore, 2),
                'rating' => round($ratingScore, 2),
                'completion' => round($completionScore, 2),
                'engagement' => round($engagementScore, 2),
                'freshness' => round($freshnessScore, 2),
            ],
            'performance_level' => $this->getPerformanceLevel($totalScore),
            'percentile_rank' => $this->calculatePercentileRank($story, $totalScore),
        ];
    }

    /**
     * Calculate bounce rate.
     */
    private function calculateBounceRate(Story $story): float
    {
        $totalViews = $story->storyViews()->count();
        
        if ($totalViews === 0) return 0;
        
        $shortSessions = $story->readingHistory()
            ->where('reading_progress', '<', 10)
            ->count();
        
        return round(($shortSessions / $totalViews) * 100, 2);
    }

    /**
     * Calculate momentum.
     */
    private function calculateMomentum(Story $story): string
    {
        $currentWeekViews = $story->storyViews()
            ->where('viewed_at', '>=', now()->subWeek())
            ->count();
        
        $previousWeekViews = $story->storyViews()
            ->whereBetween('viewed_at', [now()->subWeeks(2), now()->subWeek()])
            ->count();
        
        if ($previousWeekViews === 0) {
            return $currentWeekViews > 0 ? 'growing' : 'stagnant';
        }
        
        $change = (($currentWeekViews - $previousWeekViews) / $previousWeekViews) * 100;
        
        if ($change > 20) return 'accelerating';
        if ($change > 0) return 'growing';
        if ($change > -20) return 'stable';
        return 'declining';
    }

    /**
     * Get performance level.
     */
    private function getPerformanceLevel(float $score): string
    {
        if ($score >= 85) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 50) return 'average';
        return 'poor';
    }

    /**
     * Calculate percentile rank.
     */
    private function calculatePercentileRank(Story $story, float $score): int
    {
        $totalStories = Story::where('active', true)->count();
        
        if ($totalStories === 0) return 0;
        
        $betterStories = Story::where('active', true)
            ->get()
            ->filter(function ($s) use ($score) {
                return $s->performance_score > $score;
            })
            ->count();
        
        return round((($totalStories - $betterStories) / $totalStories) * 100);
    }

    /**
     * Get recommendations.
     */
    private function getRecommendations(Story $story): array
    {
        $recommendations = [];
        $metrics = $this->calculateStoryMetrics($story->id);
        
        // View-based recommendations
        if ($metrics['basic_metrics']['views'] < 50) {
            $recommendations[] = [
                'type' => 'views',
                'priority' => 'high',
                'message' => 'Consider improving title and excerpt for better discoverability',
                'action' => 'optimize_metadata'
            ];
        }
        
        // Rating-based recommendations
        if ($metrics['basic_metrics']['average_rating'] < 3.5) {
            $recommendations[] = [
                'type' => 'rating',
                'priority' => 'high',
                'message' => 'Content quality may need improvement',
                'action' => 'review_content'
            ];
        }
        
        // Completion-based recommendations
        if ($metrics['reading_metrics']['completion_rate'] < 60) {
            $recommendations[] = [
                'type' => 'completion',
                'priority' => 'medium',
                'message' => 'Consider improving story structure or pacing',
                'action' => 'improve_structure'
            ];
        }
        
        // Engagement-based recommendations
        if ($metrics['engagement_metrics']['engagement_rate'] < 15) {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'medium',
                'message' => 'Add more interactive elements to boost engagement',
                'action' => 'add_interactions'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Get platform-wide performance statistics.
     */
    public function getPlatformStatistics(): array
    {
        return Cache::remember('platform_performance_stats', 3600, function () {
            $stories = Story::where('active', true)->get();
            
            $performanceDistribution = [
                'excellent' => 0,
                'good' => 0,
                'average' => 0,
                'poor' => 0,
            ];
            
            $totalPerformanceScore = 0;
            $totalCompletionRate = 0;
            $totalEngagementRate = 0;
            
            foreach ($stories as $story) {
                $level = $story->performance_level;
                $performanceDistribution[$level]++;
                
                $totalPerformanceScore += $story->performance_score;
                $totalCompletionRate += $story->completion_rate;
                $totalEngagementRate += $story->engagement_score;
            }
            
            $totalStories = $stories->count();
            
            return [
                'total_stories' => $totalStories,
                'performance_distribution' => $performanceDistribution,
                'averages' => [
                    'performance_score' => $totalStories > 0 ? round($totalPerformanceScore / $totalStories, 2) : 0,
                    'completion_rate' => $totalStories > 0 ? round($totalCompletionRate / $totalStories, 2) : 0,
                    'engagement_rate' => $totalStories > 0 ? round($totalEngagementRate / $totalStories, 2) : 0,
                ],
                'totals' => [
                    'views' => StoryView::count(),
                    'ratings' => DB::table('member_story_ratings')->count(),
                    'interactions' => DB::table('member_story_interactions')->count(),
                ],
            ];
        });
    }

    /**
     * Clear all performance caches.
     */
    public function clearAllCaches(): void
    {
        Cache::forget('platform_performance_stats');
        Cache::forget('platform_story_metrics');
        
        $patterns = [
            'story_performance_score_*',
            'story_completion_rate_*',
            'story_engagement_score_*',
            'story_trending_score_*',
            'story_detailed_metrics_*',
            'stories.top_performing_*',
            'stories.trending_*',
            'stories.needing_attention_*',
        ];
        
        foreach ($patterns as $pattern) {
            Cache::tags(['performance'])->flush();
        }
    }
}