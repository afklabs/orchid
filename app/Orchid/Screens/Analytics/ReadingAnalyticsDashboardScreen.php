<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Analytics;

use App\Models\{Member, MemberReadingStatistics, MemberReadingAchievements, Story};
use App\Services\ReadingAnalyticsService;
use Orchid\Screen\{Screen, Actions\Button, Actions\Link};
use Orchid\Screen\Layouts\{Metric, Chart, Table};
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Reading Analytics Dashboard Screen
 * 
 * Comprehensive analytics dashboard for word count tracking system,
 * providing insights into reading habits, achievements, and platform metrics.
 * 
 * @package App\Orchid\Screens\Analytics
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class ReadingAnalyticsDashboardScreen extends Screen
{
    /**
     * Analytics service instance.
     */
    private ReadingAnalyticsService $analyticsService;

    /**
     * Selected period for analytics.
     */
    private string $period = 'month';

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
    public function query(Request $request): array
    {
        $this->period = $request->get('period', 'month');
        
        $globalAnalytics = $this->analyticsService->getGlobalAnalytics($this->period);
        
        return [
            'metrics' => $this->getMetrics($globalAnalytics),
            'charts' => $this->getCharts($globalAnalytics),
            'topReaders' => $this->getTopReaders(),
            'topStories' => $this->getTopStories(),
            'recentAchievements' => $this->getRecentAchievements(),
            'period' => $this->period,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Reading Analytics Dashboard';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Comprehensive analytics for word count tracking and reading statistics';
    }

    /**
     * Button commands.
     */
    public function commandBar(): array
    {
        return [
            Button::make('Daily')
                ->method('changePeriod')
                ->parameters(['period' => 'day'])
                ->class($this->period === 'day' ? 'btn btn-primary' : 'btn btn-default'),
                
            Button::make('Weekly')
                ->method('changePeriod')
                ->parameters(['period' => 'week'])
                ->class($this->period === 'week' ? 'btn btn-primary' : 'btn btn-default'),
                
            Button::make('Monthly')
                ->method('changePeriod')
                ->parameters(['period' => 'month'])
                ->class($this->period === 'month' ? 'btn btn-primary' : 'btn btn-default'),
                
            Button::make('Yearly')
                ->method('changePeriod')
                ->parameters(['period' => 'year'])
                ->class($this->period === 'year' ? 'btn btn-primary' : 'btn btn-default'),
                
            Button::make('Export Report')
                ->icon('cloud-download')
                ->method('exportAnalytics')
                ->class('btn btn-success'),
                
            Link::make('Member Analytics')
                ->icon('user')
                ->route('platform.analytics.members')
                ->class('btn btn-info'),
        ];
    }

    /**
     * Views.
     */
    public function layout(): array
    {
        return [
            // Key Metrics
            Layout::metrics([
                'Active Readers' => 'metrics.active_readers',
                'Total Words Read' => 'metrics.total_words',
                'Stories Completed' => 'metrics.stories_completed',
                'Avg Words/Reader' => 'metrics.avg_words_per_reader',
                'Engagement Rate' => 'metrics.engagement_rate',
                'Growth Rate' => 'metrics.growth_rate',
            ]),

            // Charts Row
            Layout::columns([
                Layout::rows([
                    Layout::chart('charts.daily_words')
                        ->title('Daily Words Read')
                        ->description('Word count trends over time')
                        ->height(300),
                ])->canSee($this->period !== 'day'),

                Layout::rows([
                    Layout::chart('charts.reading_levels')
                        ->title('Reading Levels Distribution')
                        ->description('Member distribution by reading level')
                        ->height(300),
                ]),
            ]),

            // Reading Patterns and Engagement
            Layout::columns([
                Layout::rows([
                    Layout::chart('charts.reading_times')
                        ->title('Reading Time Patterns')
                        ->description('When members prefer to read')
                        ->height(250),
                ]),

                Layout::rows([
                    Layout::chart('charts.category_performance')
                        ->title('Category Performance')
                        ->description('Word count by story category')
                        ->height(250),
                ]),
            ]),

            // Leaderboards and Tables
            Layout::tabs([
                'Top Readers' => Layout::rows([
                    Layout::table('topReaders', [
                        TD::make('rank', '#')
                            ->width('50px')
                            ->render(fn ($reader, $index) => $index + 1),
                            
                        TD::make('member.name', 'Reader')
                            ->render(function ($reader) {
                                return Link::make($reader->member->name)
                                    ->route('platform.members.analytics', $reader->member_id)
                                    ->icon('user');
                            }),
                            
                        TD::make('total_words', 'Words Read')
                            ->render(fn ($reader) => number_format($reader->total_words))
                            ->align(TD::ALIGN_RIGHT),
                            
                        TD::make('stories_completed', 'Stories')
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('reading_streak', 'Streak')
                            ->render(fn ($reader) => $reader->reading_streak . ' days')
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('reading_level', 'Level')
                            ->render(fn ($reader) => ucfirst($reader->reading_level))
                            ->dot()
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('efficiency_score', 'Efficiency')
                            ->render(fn ($reader) => $reader->efficiency_score . '%')
                            ->align(TD::ALIGN_RIGHT),
                    ]),
                ]),

                'Top Stories' => Layout::rows([
                    Layout::table('topStories', [
                        TD::make('rank', '#')
                            ->width('50px')
                            ->render(fn ($story, $index) => $index + 1),
                            
                        TD::make('title', 'Story')
                            ->render(function ($story) {
                                return Link::make($story->title)
                                    ->route('platform.stories.edit', $story->id);
                            }),
                            
                        TD::make('word_count', 'Words')
                            ->render(fn ($story) => number_format($story->word_count))
                            ->align(TD::ALIGN_RIGHT),
                            
                        TD::make('reading_level', 'Level')
                            ->dot()
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('unique_readers', 'Readers')
                            ->align(TD::ALIGN_CENTER),
                            
                        TD::make('completion_rate', 'Completion')
                            ->render(fn ($story) => $story->completion_rate . '%')
                            ->align(TD::ALIGN_RIGHT),
                            
                        TD::make('avg_time', 'Avg Time')
                            ->render(fn ($story) => $story->avg_time . 'm')
                            ->align(TD::ALIGN_RIGHT),
                    ]),
                ]),

                'Recent Achievements' => Layout::rows([
                    Layout::table('recentAchievements', [
                        TD::make('member.name', 'Member')
                            ->render(function ($achievement) {
                                return Link::make($achievement->member->name)
                                    ->route('platform.members.analytics', $achievement->member_id);
                            }),
                            
                        TD::make('achievement_info.name', 'Achievement')
                            ->render(function ($achievement) {
                                $info = $achievement->achievement_info;
                                return "<i class='icon-{$info['icon']}'></i> {$info['name']}";
                            }),
                            
                        TD::make('level', 'Level')
                            ->align(TD::ALIGN_CENTER)
                            ->render(fn ($achievement) => "Level {$achievement->level}"),
                            
                        TD::make('points_awarded', 'Points')
                            ->align(TD::ALIGN_RIGHT),
                            
                        TD::make('achieved_at', 'Achieved')
                            ->render(fn ($achievement) => $achievement->achieved_at->diffForHumans())
                            ->align(TD::ALIGN_RIGHT),
                    ]),
                ]),
            ]),

            // Reading Insights
            Layout::view('orchid.analytics.insights', [
                'period' => $this->period,
            ]),
        ];
    }

    /**
     * Get metrics data.
     */
    private function getMetrics(array $analytics): array
    {
        $summary = $analytics['platform_summary'];
        
        return [
            'active_readers' => [
                'value' => number_format($summary['active_members']),
                'diff' => $summary['growth_metrics']['members_growth'] ?? 0,
            ],
            'total_words' => [
                'value' => $this->formatLargeNumber($summary['total_words_read']),
                'diff' => $summary['growth_metrics']['words_growth'] ?? 0,
            ],
            'stories_completed' => [
                'value' => number_format($summary['total_stories_completed']),
                'diff' => $summary['growth_metrics']['stories_growth'] ?? 0,
            ],
            'avg_words_per_reader' => [
                'value' => number_format($summary['average_words_per_member']),
            ],
            'engagement_rate' => [
                'value' => $summary['engagement_rate'] . '%',
                'diff' => $summary['growth_metrics']['engagement_growth'] ?? 0,
            ],
            'growth_rate' => [
                'value' => ($summary['growth_metrics']['overall_growth'] ?? 0) . '%',
                'diff' => 0,
            ],
        ];
    }

    /**
     * Get charts data.
     */
    private function getCharts(array $analytics): array
    {
        $charts = [];
        
        // Daily words read chart
        $dailyData = $this->getDailyWordData();
        $charts['daily_words'] = [
            'name' => 'Words Read',
            'values' => $dailyData['values'],
            'labels' => $dailyData['labels'],
        ];
        
        // Reading levels distribution
        $levelsData = $this->getReadingLevelsData();
        $charts['reading_levels'] = [
            'name' => 'Members',
            'values' => $levelsData['values'],
            'labels' => $levelsData['labels'],
            'type' => 'pie',
        ];
        
        // Reading time patterns
        $timeData = $this->getReadingTimePatterns();
        $charts['reading_times'] = [
            'name' => 'Sessions',
            'values' => $timeData['values'],
            'labels' => $timeData['labels'],
            'type' => 'bar',
        ];
        
        // Category performance
        $categoryData = $this->getCategoryPerformance();
        $charts['category_performance'] = [
            'name' => 'Words Read',
            'values' => $categoryData['values'],
            'labels' => $categoryData['labels'],
            'type' => 'bar',
        ];
        
        return $charts;
    }

    /**
     * Get top readers for leaderboard.
     */
    private function getTopReaders(): \Illuminate\Database\Eloquent\Collection
    {
        return MemberReadingStatistics::getLeaderboard($this->period, 10);
    }

    /**
     * Get top performing stories.
     */
    private function getTopStories(): \Illuminate\Database\Eloquent\Collection
    {
        $startDate = $this->getPeriodStartDate();
        
        return Story::select('stories.*')
            ->selectRaw('COUNT(DISTINCT mrh.member_id) as unique_readers')
            ->selectRaw('COUNT(CASE WHEN mrh.reading_progress >= 100 THEN 1 END) as completions')
            ->selectRaw('(COUNT(CASE WHEN mrh.reading_progress >= 100 THEN 1 END) / COUNT(DISTINCT mrh.member_id) * 100) as completion_rate')
            ->selectRaw('AVG(mrh.time_spent / 60) as avg_time')
            ->leftJoin('member_reading_history as mrh', 'stories.id', '=', 'mrh.story_id')
            ->where('mrh.last_read_at', '>=', $startDate)
            ->groupBy('stories.id')
            ->orderByDesc('unique_readers')
            ->limit(10)
            ->get();
    }

    /**
     * Get recent achievements.
     */
    private function getRecentAchievements(): \Illuminate\Database\Eloquent\Collection
    {
        return MemberReadingAchievements::with('member:id,name,avatar_url')
            ->where('achieved_at', '>=', now()->subDays(7))
            ->orderByDesc('achieved_at')
            ->limit(10)
            ->get();
    }

    /**
     * Get daily word count data for chart.
     */
    private function getDailyWordData(): array
    {
        $days = $this->period === 'day' ? 24 : min(30, $this->getPeriodDays());
        $data = [];
        $labels = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            if ($this->period === 'day') {
                $hour = now()->subHours($i);
                $labels[] = $hour->format('H:00');
                
                $words = MemberReadingStatistics::whereDate('date', $hour->toDateString())
                    ->whereRaw('HOUR(created_at) = ?', [$hour->hour])
                    ->sum('words_read');
            } else {
                $date = now()->subDays($i);
                $labels[] = $date->format('M d');
                
                $words = MemberReadingStatistics::whereDate('date', $date)
                    ->sum('words_read');
            }
            
            $data[] = $words;
        }
        
        return [
            'values' => $data,
            'labels' => $labels,
        ];
    }

    /**
     * Get reading levels distribution data.
     */
    private function getReadingLevelsData(): array
    {
        $startDate = $this->getPeriodStartDate();
        
        $levels = MemberReadingStatistics::where('date', '>=', $startDate)
            ->select('reading_level')
            ->selectRaw('COUNT(DISTINCT member_id) as count')
            ->groupBy('reading_level')
            ->pluck('count', 'reading_level')
            ->toArray();
        
        return [
            'labels' => ['Beginner', 'Intermediate', 'Advanced', 'Expert'],
            'values' => [
                $levels['beginner'] ?? 0,
                $levels['intermediate'] ?? 0,
                $levels['advanced'] ?? 0,
                $levels['expert'] ?? 0,
            ],
        ];
    }

    /**
     * Get reading time patterns data.
     */
    private function getReadingTimePatterns(): array
    {
        $startDate = $this->getPeriodStartDate();
        
        $patterns = MemberReadingHistory::where('last_read_at', '>=', $startDate)
            ->selectRaw('HOUR(last_read_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
        
        $data = array_fill(0, 24, 0);
        $labels = [];
        
        foreach ($patterns as $pattern) {
            $data[$pattern->hour] = $pattern->count;
        }
        
        // Group into time periods
        $morningReads = array_sum(array_slice($data, 5, 7));    // 5 AM - 12 PM
        $afternoonReads = array_sum(array_slice($data, 12, 5)); // 12 PM - 5 PM
        $eveningReads = array_sum(array_slice($data, 17, 4));   // 5 PM - 9 PM
        $nightReads = array_sum(array_slice($data, 21, 3)) +    // 9 PM - 12 AM
                      array_sum(array_slice($data, 0, 5));      // 12 AM - 5 AM
        
        return [
            'labels' => ['Morning', 'Afternoon', 'Evening', 'Night'],
            'values' => [$morningReads, $afternoonReads, $eveningReads, $nightReads],
        ];
    }

    /**
     * Get category performance data.
     */
    private function getCategoryPerformance(): array
    {
        $startDate = $this->getPeriodStartDate();
        
        $categories = Story::join('member_reading_history as mrh', 'stories.id', '=', 'mrh.story_id')
            ->join('categories', 'stories.category_id', '=', 'categories.id')
            ->where('mrh.last_read_at', '>=', $startDate)
            ->where('mrh.reading_progress', '>=', 100)
            ->select('categories.name')
            ->selectRaw('SUM(stories.word_count) as total_words')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_words')
            ->limit(5)
            ->get();
        
        return [
            'labels' => $categories->pluck('name')->toArray(),
            'values' => $categories->pluck('total_words')->toArray(),
        ];
    }

    /**
     * Change analytics period.
     */
    public function changePeriod(Request $request): void
    {
        session(['analytics_period' => $request->get('period', 'month')]);
        
        $this->alert('success', 'Analytics period updated');
    }

    /**
     * Export analytics report.
     */
    public function exportAnalytics(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $analytics = $this->analyticsService->getGlobalAnalytics($this->period);
        
        $filename = 'reading_analytics_' . $this->period . '_' . now()->format('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($analytics) {
            $handle = fopen('php://output', 'w');
            
            // Headers
            fputcsv($handle, ['Metric', 'Value', 'Change %']);
            
            // Platform Summary
            fputcsv($handle, ['Platform Summary', '', '']);
            fputcsv($handle, ['Active Readers', $analytics['platform_summary']['active_members'], '']);
            fputcsv($handle, ['Total Words Read', $analytics['platform_summary']['total_words_read'], '']);
            fputcsv($handle, ['Stories Completed', $analytics['platform_summary']['total_stories_completed'], '']);
            fputcsv($handle, ['Average Words per Reader', $analytics['platform_summary']['average_words_per_member'], '']);
            
            // Add more sections as needed
            
            fclose($handle);
        }, $filename);
    }

    /**
     * Get period start date.
     */
    private function getPeriodStartDate(): Carbon
    {
        return match($this->period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    /**
     * Get number of days in period.
     */
    private function getPeriodDays(): int
    {
        return match($this->period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            'year' => 365,
            default => 30,
        };
    }

    /**
     * Format large numbers for display.
     */
    private function formatLargeNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        
        return number_format($number);
    }

    /**
     * Permission check.
     */
    public function permission(): ?iterable
    {
        return [
            'analytics.view',
        ];
    }
}