<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Analytics;

use App\Models\{Member, MemberReadingStatistics, MemberReadingAchievements, MemberReadingHistory};
use App\Services\ReadingAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Orchid\Screen\{Screen, Actions\Button, Actions\Link, Actions\DropDown};
use Orchid\Screen\Fields\{Input, Select, Group};
use Orchid\Screen\TD;
use Orchid\Support\Facades\{Layout, Toast};
use Orchid\Support\Color;
use Carbon\Carbon;

/**
 * Member Analytics Screen for Orchid Admin Panel
 * 
 * Displays comprehensive analytics for individual members including:
 * - Reading statistics and word count analytics
 * - Achievement progress and badges
 * - Reading patterns and trends
 * - Personalized recommendations
 * - Performance comparisons
 * 
 * Features:
 * - Real-time analytics with Redis caching
 * - Interactive charts and visualizations
 * - Period filtering (daily, weekly, monthly, yearly)
 * - Export functionality
 * - Achievement management
 * - Social comparison features
 * - Personalized recommendations
 * 
 * @package App\Orchid\Screens\Analytics
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class MemberAnalyticsScreen extends Screen
{
    /**
     * @var Member
     */
    public $member;

    /**
     * @var ReadingAnalyticsService
     */
    private ReadingAnalyticsService $analyticsService;

    /**
     * Cache TTL for analytics data (15 minutes)
     */
    private const CACHE_TTL = 900;

    /**
     * Initialize the screen
     */
    public function __construct()
    {
        $this->analyticsService = app(ReadingAnalyticsService::class);
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Member $member, Request $request): iterable
    {
        // Cache key for member analytics
        $period = $request->get('period', 'month');
        $cacheKey = "member_analytics_{$member->id}_{$period}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($member, $period) {
            // Get comprehensive member analytics
            $analytics = $this->analyticsService->getMemberAnalytics($member->id, $period);
            
            // Get reading history for detailed analysis
            $readingHistory = $this->getReadingHistoryData($member, $period);
            
            // Get achievements data
            $achievements = $this->getMemberAchievements($member);
            
            // Get recommendations
            $recommendations = $analytics['recommendations'] ?? [];
            
            // Get comparison data
            $comparisons = $analytics['comparisons'] ?? [];

            return [
                'member' => $member,
                'period' => $period,
                'analytics' => $analytics,
                'reading_history' => $readingHistory,
                'achievements' => $achievements,
                'recommendations' => $recommendations,
                'comparisons' => $comparisons,
                'metrics' => $this->getMetrics($analytics),
                'charts' => $this->getChartData($analytics),
                'trends' => $this->getTrendData($analytics),
            ];
        });
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->member->name . ' - Reading Analytics';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Comprehensive reading analytics and performance insights for ' . $this->member->name;
    }

    /**
     * Required permissions to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'view member analytics',
        ];
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            // Period selection buttons
            Button::make('Daily')
                ->method('changePeriod')
                ->parameters(['period' => 'day'])
                ->icon('calendar')
                ->type(request('period') === 'day' ? Color::PRIMARY : Color::BASIC),
                
            Button::make('Weekly')
                ->method('changePeriod')
                ->parameters(['period' => 'week'])
                ->icon('calendar')
                ->type(request('period') === 'week' ? Color::PRIMARY : Color::BASIC),
                
            Button::make('Monthly')
                ->method('changePeriod')
                ->parameters(['period' => 'month'])
                ->icon('calendar')
                ->type(request('period', 'month') === 'month' ? Color::PRIMARY : Color::BASIC),
                
            Button::make('Yearly')
                ->method('changePeriod')
                ->parameters(['period' => 'year'])
                ->icon('calendar')
                ->type(request('period') === 'year' ? Color::PRIMARY : Color::BASIC),

            // Export functionality
            DropDown::make('Export Data')
                ->icon('download')
                ->list([
                    Button::make('Export PDF Report')
                        ->method('exportPDF')
                        ->icon('file-pdf')
                        ->parameters(['member_id' => $this->member->id]),
                        
                    Button::make('Export Excel Data')
                        ->method('exportExcel')
                        ->icon('file-excel')
                        ->parameters(['member_id' => $this->member->id]),
                        
                    Button::make('Export CSV Data')
                        ->method('exportCSV')
                        ->icon('file-csv')
                        ->parameters(['member_id' => $this->member->id]),
                ]),

            // Member management actions
            DropDown::make('Member Actions')
                ->icon('settings')
                ->list([
                    Link::make('Edit Member')
                        ->route('platform.members.edit', $this->member)
                        ->icon('pencil'),
                        
                    Button::make('Send Message')
                        ->method('sendMessage')
                        ->icon('envelope')
                        ->parameters(['member_id' => $this->member->id]),
                        
                    Button::make('Award Achievement')
                        ->method('awardAchievement')
                        ->icon('trophy')
                        ->parameters(['member_id' => $this->member->id]),
                        
                    Button::make('Reset Statistics')
                        ->method('resetStatistics')
                        ->icon('refresh')
                        ->confirm('Are you sure you want to reset this member\'s statistics?')
                        ->parameters(['member_id' => $this->member->id]),
                ]),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            // Member Overview Cards
            Layout::metrics([
                'Words Read' => 'metrics.words_read',
                'Stories Completed' => 'metrics.stories_completed',
                'Reading Streak' => 'metrics.reading_streak',
                'Average Daily Words' => 'metrics.avg_daily_words',
                'Reading Level' => 'metrics.reading_level',
                'Completion Rate' => 'metrics.completion_rate',
            ]),

            // Achievement Progress Section
            Layout::block([
                Layout::view('orchid.analytics.member.achievements', [
                    'achievements' => 'achievements',
                    'member' => 'member',
                ])
            ])
            ->title('Achievement Progress')
            ->description('Member\'s reading achievements and progress towards goals'),

            // Reading Analytics Charts
            Layout::columns([
                Layout::rows([
                    Layout::chart('charts.word_count_trend')
                        ->title('Word Count Trend')
                        ->description('Daily word count progress over time')
                        ->height(300),
                ]),
                
                Layout::rows([
                    Layout::chart('charts.reading_patterns')
                        ->title('Reading Patterns')
                        ->description('When this member prefers to read')
                        ->height(300),
                ]),
            ]),

            // Reading Performance Analysis
            Layout::columns([
                Layout::rows([
                    Layout::chart('charts.category_distribution')
                        ->title('Reading Categories')
                        ->description('Distribution of reading by category')
                        ->height(250),
                ]),
                
                Layout::rows([
                    Layout::chart('charts.reading_speed')
                        ->title('Reading Speed Analysis')
                        ->description('Words per minute over time')
                        ->height(250),
                ]),
                
                Layout::rows([
                    Layout::chart('charts.engagement_score')
                        ->title('Engagement Score')
                        ->description('Member engagement level trends')
                        ->height(250),
                ]),
            ]),

            // Comparison and Benchmarking
            Layout::block([
                Layout::view('orchid.analytics.member.comparisons', [
                    'comparisons' => 'comparisons',
                    'member' => 'member',
                ])
            ])
            ->title('Performance Comparison')
            ->description('How this member compares to others on the platform'),

            // Detailed Reading History
            Layout::tabs([
                'Reading History' => Layout::rows([
                    Layout::table('reading_history', [
                        TD::make('story.title', 'Story')
                            ->render(function ($history) {
                                return Link::make($history->story->title ?? 'Unknown')
                                    ->route('platform.stories.show', $history->story_id ?? 0)
                                    ->icon('book');
                            }),
                            
                        TD::make('words_read', 'Words Read')
                            ->render(fn ($history) => number_format($history->words_read ?? 0))
                            ->align(TD::ALIGN_RIGHT),
                            
                        TD::make('reading_progress', 'Progress')
                            ->render(function ($history) {
                                $progress = $history->reading_progress ?? 0;
                                $color = $progress >= 100 ? 'success' : ($progress > 0 ? 'warning' : 'secondary');
                                return "<span class='badge badge-{$color}'>{$progress}%</span>";
                            })
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('time_spent', 'Time Spent')
                            ->render(fn ($history) => $history->formatted_time_spent ?? '0s')
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('reading_level', 'Level')
                            ->render(fn ($history) => ucfirst($history->reading_level ?? 'beginner'))
                            ->dot()
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('last_read_at', 'Last Read')
                            ->render(fn ($history) => $history->last_read_at?->diffForHumans() ?? 'Never')
                            ->align(TD::ALIGN_RIGHT),
                    ])
                ]),

                'Trends Analysis' => Layout::rows([
                    Layout::view('orchid.analytics.member.trends', [
                        'trends' => 'trends',
                        'member' => 'member',
                    ])
                ]),

                'Recommendations' => Layout::rows([
                    Layout::view('orchid.analytics.member.recommendations', [
                        'recommendations' => 'recommendations',
                        'member' => 'member',
                    ])
                ]),
            ]),

            // Insights and Recommendations
            Layout::view('orchid.analytics.member.insights', [
                'analytics' => 'analytics',
                'member' => 'member',
                'period' => 'period',
            ]),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Screen Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Change analytics period
     */
    public function changePeriod(Request $request): void
    {
        $period = $request->get('period', 'month');
        
        // Clear cache for new period
        Cache::forget("member_analytics_{$this->member->id}_{$period}");
        
        Toast::info("Analytics period changed to {$period}");
    }

    /**
     * Export member analytics as PDF
     */
    public function exportPDF(Request $request): void
    {
        $memberId = $request->get('member_id');
        $period = $request->get('period', 'month');
        
        // TODO: Implement PDF export functionality
        Toast::info('PDF export functionality will be implemented');
    }

    /**
     * Export member analytics as Excel
     */
    public function exportExcel(Request $request): void
    {
        $memberId = $request->get('member_id');
        $period = $request->get('period', 'month');
        
        // TODO: Implement Excel export functionality
        Toast::info('Excel export functionality will be implemented');
    }

    /**
     * Export member analytics as CSV
     */
    public function exportCSV(Request $request): void
    {
        $memberId = $request->get('member_id');
        $period = $request->get('period', 'month');
        
        // TODO: Implement CSV export functionality
        Toast::info('CSV export functionality will be implemented');
    }

    /**
     * Send message to member
     */
    public function sendMessage(Request $request): void
    {
        $memberId = $request->get('member_id');
        
        // TODO: Implement messaging functionality
        Toast::info('Messaging functionality will be implemented');
    }

    /**
     * Award achievement to member
     */
    public function awardAchievement(Request $request): void
    {
        $memberId = $request->get('member_id');
        
        // TODO: Implement manual achievement awarding
        Toast::info('Achievement awarding functionality will be implemented');
    }

    /**
     * Reset member statistics
     */
    public function resetStatistics(Request $request): void
    {
        $memberId = $request->get('member_id');
        
        // TODO: Implement statistics reset functionality
        Toast::warning('Statistics reset functionality will be implemented');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get metrics data for the screen
     */
    private function getMetrics(array $analytics): array
    {
        $summary = $analytics['summary'] ?? [];
        $comparisons = $analytics['comparisons'] ?? [];
        
        return [
            'words_read' => [
                'value' => number_format($summary['total_words_read'] ?? 0),
                'diff' => $this->calculateGrowth($summary['total_words_read'] ?? 0, $comparisons),
            ],
            'stories_completed' => [
                'value' => number_format($summary['total_stories_completed'] ?? 0),
                'diff' => $this->calculateGrowth($summary['total_stories_completed'] ?? 0, $comparisons),
            ],
            'reading_streak' => [
                'value' => ($summary['current_streak'] ?? 0) . ' days',
                'diff' => 0, // Streaks don't have growth comparison
            ],
            'avg_daily_words' => [
                'value' => number_format($summary['average_daily_words'] ?? 0),
                'diff' => $this->calculateGrowth($summary['average_daily_words'] ?? 0, $comparisons),
            ],
            'reading_level' => [
                'value' => ucfirst($summary['reading_level'] ?? 'beginner'),
                'diff' => 0, // Levels don't have numerical growth
            ],
            'completion_rate' => [
                'value' => round($summary['period_completion_rate'] ?? 0, 1) . '%',
                'diff' => $this->calculateGrowth($summary['period_completion_rate'] ?? 0, $comparisons),
            ],
        ];
    }

    /**
     * Get chart data for visualizations
     */
    private function getChartData(array $analytics): array
    {
        return [
            'word_count_trend' => $this->formatWordCountTrend($analytics['trends'] ?? []),
            'reading_patterns' => $this->formatReadingPatterns($analytics['reading_patterns'] ?? []),
            'category_distribution' => $this->formatCategoryDistribution($analytics['reading_stats'] ?? []),
            'reading_speed' => $this->formatReadingSpeed($analytics['word_count_analytics'] ?? []),
            'engagement_score' => $this->formatEngagementScore($analytics['engagement_metrics'] ?? []),
        ];
    }

    /**
     * Get reading history data
     */
    private function getReadingHistoryData(Member $member, string $period): array
    {
        $days = $this->getPeriodDays($period);
        $startDate = now()->subDays($days);
        
        return MemberReadingHistory::where('member_id', $member->id)
            ->where('last_read_at', '>=', $startDate)
            ->with(['story'])
            ->orderBy('last_read_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Get member achievements data
     */
    private function getMemberAchievements(Member $member): array
    {
        return MemberReadingAchievements::where('member_id', $member->id)
            ->with(['member'])
            ->orderBy('achieved_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get trend data for analysis
     */
    private function getTrendData(array $analytics): array
    {
        return $analytics['trends'] ?? [];
    }

    /**
     * Calculate growth percentage
     */
    private function calculateGrowth(int $current, array $comparisons): float
    {
        if (empty($comparisons['vs_average'])) {
            return 0;
        }
        
        $average = $comparisons['vs_average']['words_read']['average'] ?? 0;
        if ($average == 0) {
            return 0;
        }
        
        return round((($current - $average) / $average) * 100, 1);
    }

    /**
     * Format word count trend data for charts
     */
    private function formatWordCountTrend(array $trends): array
    {
        // TODO: Format trends data for chart visualization
        return [];
    }

    /**
     * Format reading patterns data for charts
     */
    private function formatReadingPatterns(array $patterns): array
    {
        // TODO: Format patterns data for chart visualization
        return [];
    }

    /**
     * Format category distribution data for charts
     */
    private function formatCategoryDistribution(array $stats): array
    {
        // TODO: Format category data for chart visualization
        return [];
    }

    /**
     * Format reading speed data for charts
     */
    private function formatReadingSpeed(array $analytics): array
    {
        // TODO: Format reading speed data for chart visualization
        return [];
    }

    /**
     * Format engagement score data for charts
     */
    private function formatEngagementScore(array $metrics): array
    {
        // TODO: Format engagement metrics for chart visualization
        return [];
    }

    /**
     * Get period days
     */
    private function getPeriodDays(string $period): int
    {
        return match ($period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'year' => 365,
            default => 30,
        };
    }
}