declare(strict_types=1);

namespace App\Http\Resources;
<?php

// File: app/Http/Resources/AnalyticsResource.php



use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Analytics Resource
 * 
 * Formats analytics data for API responses
 * to the Flutter mobile application.
 * 
 * @package App\Http\Resources
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class AnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'summary' => $this->formatSummary($this->summary ?? []),
            'reading_stats' => $this->formatReadingStats($this->reading_stats ?? []),
            'word_count_analytics' => $this->formatWordCountAnalytics($this->word_count_analytics ?? []),
            'engagement_metrics' => $this->formatEngagementMetrics($this->engagement_metrics ?? []),
            'reading_patterns' => $this->formatReadingPatterns($this->reading_patterns ?? []),
            'achievements_progress' => $this->formatAchievementsProgress($this->achievements_progress ?? []),
            'comparisons' => $this->formatComparisons($this->comparisons ?? []),
            'trends' => $this->formatTrends($this->trends ?? []),
            'recommendations' => $this->formatRecommendations($this->recommendations ?? []),
        ];
    }

    /**
     * Format summary data
     */
    private function formatSummary(array $summary): array
    {
        return [
            'total_words_read' => $summary['total_words_read'] ?? 0,
            'total_stories_completed' => $summary['total_stories_completed'] ?? 0,
            'total_reading_time' => $summary['total_reading_time'] ?? '0m',
            'average_daily_words' => $summary['average_daily_words'] ?? 0,
            'current_streak' => $summary['current_streak'] ?? 0,
            'longest_streak' => $summary['longest_streak'] ?? 0,
            'reading_days' => $summary['reading_days'] ?? 0,
            'reading_level' => $summary['reading_level'] ?? 'beginner',
            'period_completion_rate' => $summary['period_completion_rate'] ?? 0,
            
            // Display formatted values
            'display' => [
                'words_read' => $this->formatNumber($summary['total_words_read'] ?? 0),
                'stories_completed' => $this->formatNumber($summary['total_stories_completed'] ?? 0),
                'reading_time' => $summary['total_reading_time'] ?? '0m',
                'daily_average' => $this->formatNumber($summary['average_daily_words'] ?? 0),
                'current_streak' => ($summary['current_streak'] ?? 0) . ' days',
                'longest_streak' => ($summary['longest_streak'] ?? 0) . ' days',
                'completion_rate' => round($summary['period_completion_rate'] ?? 0, 1) . '%',
            ],
        ];
    }

    /**
     * Format reading stats
     */
    private function formatReadingStats(array $stats): array
    {
        return [
            'stories_started' => $stats['stories_started'] ?? 0,
            'stories_completed' => $stats['stories_completed'] ?? 0,
            'stories_in_progress' => $stats['stories_in_progress'] ?? 0,
            'completion_rate' => $stats['completion_rate'] ?? 0,
            'average_completion_time' => $stats['average_completion_time'] ?? 0,
            'favorite_categories' => $stats['favorite_categories'] ?? [],
            'reading_velocity' => $stats['reading_velocity'] ?? 0,
            'time_distribution' => $stats['time_distribution'] ?? [],
        ];
    }

    /**
     * Format word count analytics
     */
    private function formatWordCountAnalytics(array $analytics): array
    {
        return [
            'total_words' => $analytics['total_words'] ?? 0,
            'daily_average' => $analytics['daily_average'] ?? 0,
            'peak_day' => $analytics['peak_day'] ?? null,
            'reading_equivalents' => $analytics['reading_equivalents'] ?? [],
            'words_by_level' => $analytics['words_by_level'] ?? [],
            'words_by_category' => $analytics['words_by_category'] ?? [],
            'reading_speed_analysis' => $analytics['reading_speed_analysis'] ?? [],
            'word_count_trend' => $analytics['word_count_trend'] ?? [],
        ];
    }

    /**
     * Format engagement metrics
     */
    private function formatEngagementMetrics(array $metrics): array
    {
        return [
            'engagement_score' => $metrics['engagement_score'] ?? 0,
            'interaction_rate' => $metrics['interaction_rate'] ?? 0,
            'actions' => $metrics['actions'] ?? [],
            'average_rating_given' => $metrics['average_rating_given'] ?? 0,
            'engagement_level' => $metrics['engagement_level'] ?? 'low',
            'social_impact' => $metrics['social_impact'] ?? 0,
        ];
    }

    /**
     * Format reading patterns
     */
    private function formatReadingPatterns(array $patterns): array
    {
        return [
            'preferred_reading_times' => $patterns['preferred_reading_times'] ?? [],
            'reading_days_pattern' => $patterns['reading_days_pattern'] ?? [],
            'session_duration_pattern' => $patterns['session_duration_pattern'] ?? [],
            'category_preferences' => $patterns['category_preferences'] ?? [],
            'reading_level_progression' => $patterns['reading_level_progression'] ?? [],
            'consistency_score' => $patterns['consistency_score'] ?? 0,
        ];
    }

    /**
     * Format achievements progress
     */
    private function formatAchievementsProgress(array $progress): array
    {
        return collect($progress)->map(function ($achievement) {
            return [
                'achievement_type' => $achievement['achievement_type'] ?? '',
                'name' => $achievement['name'] ?? '',
                'description' => $achievement['description'] ?? '',
                'icon' => $achievement['icon'] ?? 'trophy',
                'current_level' => $achievement['current_level'] ?? 0,
                'next_level' => $achievement['next_level'] ?? null,
                'progress_percentage' => $achievement['progress_percentage'] ?? 0,
                'is_max_level' => $achievement['is_max_level'] ?? false,
            ];
        })->toArray();
    }

    /**
     * Format comparisons
     */
    private function formatComparisons(array $comparisons): array
    {
        return [
            'vs_average' => $comparisons['vs_average'] ?? [],
            'percentile_rank' => $comparisons['percentile_rank'] ?? [],
            'peer_group_comparison' => $comparisons['peer_group_comparison'] ?? [],
        ];
    }

    /**
     * Format trends
     */
    private function formatTrends(array $trends): array
    {
        return [
            'word_count_trend' => $trends['word_count_trend'] ?? [],
            'reading_speed_trend' => $trends['reading_speed_trend'] ?? [],
            'engagement_trend' => $trends['engagement_trend'] ?? [],
            'level_progression_trend' => $trends['level_progression_trend'] ?? [],
        ];
    }

    /**
     * Format recommendations
     */
    private function formatRecommendations(array $recommendations): array
    {
        return collect($recommendations)->map(function ($recommendation) {
            return [
                'type' => $recommendation['type'] ?? 'general',
                'priority' => $recommendation['priority'] ?? 'medium',
                'title' => $recommendation['title'] ?? '',
                'description' => $recommendation['description'] ?? '',
                'action' => $recommendation['action'] ?? '',
                'target_value' => $recommendation['target_value'] ?? null,
                'current_value' => $recommendation['current_value'] ?? null,
                'progress_percentage' => $this->calculateRecommendationProgress($recommendation),
            ];
        })->toArray();
    }

    /**
     * Calculate recommendation progress
     */
    private function calculateRecommendationProgress(array $recommendation): int
    {
        if (!isset($recommendation['current_value']) || !isset($recommendation['target_value'])) {
            return 0;
        }
        
        $current = $recommendation['current_value'];
        $target = $recommendation['target_value'];
        
        if ($target <= 0) {
            return 0;
        }
        
        return min(100, round(($current / $target) * 100));
    }

    /**
     * Format number for display
     */
    private function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }
        
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        
        return number_format($number);
    }
}
