<?php

namespace App\Orchid\Screens\Story;

use App\Models\Story;
use App\Services\ReadingAnalyticsService;
use Orchid\Screen\{Screen, Actions\Button, Actions\Link};
use Orchid\Support\Facades\Layout;
use Illuminate\Http\Request;

/**
 * Story Analytics Screen
 * 
 * Detailed analytics for individual stories including word count impact,
 * reading patterns, and performance metrics.
 */
class StoryAnalyticsScreen extends Screen
{
    /**
     * Story instance.
     */
    public Story $story;

    /**
     * Analytics service.
     */
    private ReadingAnalyticsService $analyticsService;

    /**
     * Constructor.
     */
    public function __construct(ReadingAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Query data for the screen.
     */
    public function query(Story $story, Request $request): array
    {
        $this->story = $story;
        $period = $request->get('period', 'month');

        $analytics = $this->analyticsService->getStoryAnalytics($story->id, $period);

        return [
            'story' => $story,
            'analytics' => $analytics,
            'period' => $period,
            'wordCountImpact' => $this->getWordCountImpact($story, $analytics),
            'readingPatterns' => $this->getReadingPatterns($story, $analytics),
            'performanceMetrics' => $this->getPerformanceMetrics($story, $analytics),
            'recommendations' => $this->getRecommendations($story, $analytics),
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Story Analytics: ' . $this->story->title;
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Comprehensive analytics for story performance and word count impact';
    }

    /**
     * Button commands.
     */
    public function commandBar(): array
    {
        return [
            Link::make('Edit Story')
                ->route('platform.stories.edit', $this->story)
                ->icon('pencil')
                ->class('btn btn-primary'),

            Link::make('View Story')
                ->href(route('stories.show', $this->story))
                ->icon('eye')
                ->target('_blank')
                ->class('btn btn-outline-primary'),

            Button::make('Export Report')
                ->method('exportReport')
                ->icon('cloud-download')
                ->class('btn btn-outline-info'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            // Story Overview
            Layout::metrics([
                'Word Count' => 'story.word_count',
                'Reading Level' => 'story.reading_level',
                'Reading Time' => 'story.reading_time_minutes',
                'Total Views' => 'story.views',
                'Total Likes' => 'story.likes',
                'Completion Rate' => 'story.completion_rate',
            ]),

            // Word Count Impact Analysis
            Layout::view('orchid.analytics.story.word-count-impact', [
                'wordCountImpact' => 'wordCountImpact',
                'story' => 'story',
            ]),

            // Reading Patterns
            Layout::view('orchid.analytics.story.reading-patterns', [
                'readingPatterns' => 'readingPatterns',
                'story' => 'story',
            ]),

            // Performance Metrics
            Layout::view('orchid.analytics.story.performance-metrics', [
                'performanceMetrics' => 'performanceMetrics',
                'story' => 'story',
            ]),

            // Recommendations
            Layout::view('orchid.analytics.story.recommendations', [
                'recommendations' => 'recommendations',
                'story' => 'story',
            ]),
        ];
    }

    /**
     * Export analytics report.
     */
    public function exportReport(Request $request): void
    {
        // TODO: Implement report export functionality
        Toast::info('Report export functionality will be implemented in the next phase.');
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get word count impact data.
     */
    private function getWordCountImpact(Story $story, array $analytics): array
    {
        return [
            'word_count_vs_engagement' => $analytics['word_count_vs_engagement'] ?? [],
            'optimal_word_count' => $analytics['optimal_word_count'] ?? 0,
            'word_count_percentile' => $analytics['word_count_percentile'] ?? 0,
            'similar_stories_performance' => $analytics['similar_stories_performance'] ?? [],
        ];
    }

    /**
     * Get reading patterns data.
     */
    private function getReadingPatterns(Story $story, array $analytics): array
    {
        return [
            'reading_time_distribution' => $analytics['reading_time_distribution'] ?? [],
            'peak_reading_hours' => $analytics['peak_reading_hours'] ?? [],
            'completion_patterns' => $analytics['completion_patterns'] ?? [],
            'device_preferences' => $analytics['device_preferences'] ?? [],
        ];
    }

    /**
     * Get performance metrics data.
     */
    private function getPerformanceMetrics(Story $story, array $analytics): array
    {
        return [
            'engagement_score' => $analytics['engagement_score'] ?? 0,
            'retention_rate' => $analytics['retention_rate'] ?? 0,
            'social_shares' => $analytics['social_shares'] ?? 0,
            'bounce_rate' => $analytics['bounce_rate'] ?? 0,
            'average_session_time' => $analytics['average_session_time'] ?? 0,
        ];
    }

    /**
     * Get recommendations data.
     */
    private function getRecommendations(Story $story, array $analytics): array
    {
        return [
            'word_count_recommendations' => $analytics['word_count_recommendations'] ?? [],
            'content_improvements' => $analytics['content_improvements'] ?? [],
            'publishing_suggestions' => $analytics['publishing_suggestions'] ?? [],
            'seo_recommendations' => $analytics['seo_recommendations'] ?? [],
        ];
    }
}
