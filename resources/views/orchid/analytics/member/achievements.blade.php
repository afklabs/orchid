<?php
// File: resources/views/orchid/analytics/member/achievements.blade.php
?>
@php
    $achievements = $achievements ?? [];
    $member = $member ?? null;
    
    // Group achievements by type
    $achievementsByType = collect($achievements)->groupBy('achievement_type');
    
    // Achievement type information
    $achievementTypes = [
        'daily_reader' => ['name' => 'Daily Reader', 'icon' => 'calendar', 'color' => 'primary'],
        'word_master' => ['name' => 'Word Master', 'icon' => 'book', 'color' => 'success'],
        'speed_reader' => ['name' => 'Speed Reader', 'icon' => 'zap', 'color' => 'warning'],
        'streak_keeper' => ['name' => 'Streak Keeper', 'icon' => 'fire', 'color' => 'danger'],
        'level_climber' => ['name' => 'Level Climber', 'icon' => 'trending-up', 'color' => 'info'],
        'category_explorer' => ['name' => 'Category Explorer', 'icon' => 'compass', 'color' => 'secondary'],
        'engagement_star' => ['name' => 'Engagement Star', 'icon' => 'star', 'color' => 'warning'],
        'completion_champion' => ['name' => 'Completion Champion', 'icon' => 'trophy', 'color' => 'success'],
        'early_bird' => ['name' => 'Early Bird', 'icon' => 'sunrise', 'color' => 'primary'],
        'night_owl' => ['name' => 'Night Owl', 'icon' => 'moon', 'color' => 'dark'],
    ];
@endphp

<div class="row">
    @foreach($achievementTypes as $type => $typeInfo)
        @php
            $typeAchievements = $achievementsByType[$type] ?? collect();
            $latestAchievement = $typeAchievements->sortByDesc('level')->first();
            $currentLevel = $latestAchievement ? $latestAchievement['level'] : 0;
            $maxLevel = 5;
        @endphp
        
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="achievement-icon me-3">
                            <i class="icon-{{ $typeInfo['icon'] }} text-{{ $typeInfo['color'] }}" style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h6 class="card-title mb-0">{{ $typeInfo['name'] }}</h6>
                            <small class="text-muted">Level {{ $currentLevel }}/{{ $maxLevel }}</small>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mb-3">
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-{{ $typeInfo['color'] }}" 
                                 style="width: {{ ($currentLevel / $maxLevel) * 100 }}%">
                            </div>
                        </div>
                    </div>
                    
                    @if($latestAchievement)
                        <div class="achievement-details">
                            <p class="mb-2">
                                <strong>{{ $latestAchievement['points_awarded'] }}</strong> points earned
                            </p>
                            <small class="text-muted">
                                Achieved {{ \Carbon\Carbon::parse($latestAchievement['achieved_at'])->diffForHumans() }}
                            </small>
                        </div>
                    @else
                        <div class="text-muted">
                            <small>No achievements yet</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<?php
// File: resources/views/orchid/analytics/member/comparisons.blade.php
?>
@php
    $comparisons = $comparisons ?? [];
    $member = $member ?? null;
    $vsAverage = $comparisons['vs_average'] ?? [];
    $percentileRank = $comparisons['percentile_rank'] ?? [];
    $peerGroup = $comparisons['peer_group_comparison'] ?? [];
@endphp

<div class="row">
    <!-- Vs Average Comparison -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">Platform Average Comparison</h6>
            </div>
            <div class="card-body">
                @if(!empty($vsAverage))
                    <div class="comparison-metric mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Words Read</span>
                            <span class="badge badge-{{ $vsAverage['words_read']['percentage'] > 100 ? 'success' : 'warning' }}">
                                {{ $vsAverage['words_read']['percentage'] }}% of average
                            </span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                You: {{ number_format($vsAverage['words_read']['member']) }} | 
                                Average: {{ number_format($vsAverage['words_read']['average']) }}
                            </small>
                        </div>
                    </div>
                    
                    <div class="comparison-metric mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Stories Completed</span>
                            <span class="badge badge-{{ $vsAverage['stories_completed']['percentage'] > 100 ? 'success' : 'warning' }}">
                                {{ $vsAverage['stories_completed']['percentage'] }}% of average
                            </span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                You: {{ $vsAverage['stories_completed']['member'] }} | 
                                Average: {{ $vsAverage['stories_completed']['average'] }}
                            </small>
                        </div>
                    </div>
                @else
                    <div class="text-muted">
                        <small>Comparison data not available</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Percentile Ranking -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">Percentile Ranking</h6>
            </div>
            <div class="card-body">
                @if(!empty($percentileRank))
                    <div class="text-center mb-3">
                        <div class="percentile-circle">
                            <span class="percentile-value">{{ $percentileRank['words_percentile'] ?? 0 }}</span>
                            <small class="text-muted">percentile</small>
                        </div>
                    </div>
                    
                    <div class="percentile-details">
                        <div class="mb-2">
                            <strong>Rank:</strong> {{ $percentileRank['rank'] ?? 'N/A' }} 
                            of {{ $percentileRank['total_members'] ?? 'N/A' }} members
                        </div>
                        <div class="mb-2">
                            <strong>Better than:</strong> {{ $percentileRank['better_than_percent'] ?? 0 }}% of members
                        </div>
                    </div>
                @else
                    <div class="text-muted">
                        <small>Ranking data not available</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<?php
// File: resources/views/orchid/analytics/member/trends.blade.php
?>
@php
    $trends = $trends ?? [];
    $member = $member ?? null;
@endphp

<div class="row">
    <!-- Word Count Trends -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">Word Count Trends</h6>
            </div>
            <div class="card-body">
                @if(!empty($trends['word_count_trend']))
                    <div class="trend-chart mb-3">
                        <!-- Chart will be rendered here by JavaScript -->
                        <canvas id="wordCountTrendChart" width="100%" height="200"></canvas>
                    </div>
                    
                    <div class="trend-insights">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <strong>{{ $trends['word_count_trend']['peak_day'] ?? 'N/A' }}</strong>
                                    <br><small class="text-muted">Peak Day</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <strong>{{ $trends['word_count_trend']['average_growth'] ?? 0 }}%</strong>
                                    <br><small class="text-muted">Growth Rate</small>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-muted">
                        <small>Trend data not available</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Reading Patterns -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0">Reading Patterns</h6>
            </div>
            <div class="card-body">
                @if(!empty($trends['reading_patterns']))
                    <div class="pattern-analysis">
                        <div class="mb-3">
                            <strong>Preferred Reading Time:</strong>
                            <span class="badge badge-primary">
                                {{ $trends['reading_patterns']['preferred_time'] ?? 'N/A' }}
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Most Active Day:</strong>
                            <span class="badge badge-success">
                                {{ $trends['reading_patterns']['most_active_day'] ?? 'N/A' }}
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Consistency Score:</strong>
                            <span class="badge badge-info">
                                {{ $trends['reading_patterns']['consistency_score'] ?? 0 }}%
                            </span>
                        </div>
                    </div>
                @else
                    <div class="text-muted">
                        <small>Pattern data not available</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<?php
// File: resources/views/orchid/analytics/member/recommendations.blade.php
?>
@php
    $recommendations = $recommendations ?? [];
    $member = $member ?? null;
    
    // Priority colors
    $priorityColors = [
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'info',
    ];
@endphp

<div class="row">
    @forelse($recommendations as $recommendation)
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-{{ $priorityColors[$recommendation['priority']] ?? 'secondary' }}">
                <div class="card-header bg-{{ $priorityColors[$recommendation['priority']] ?? 'secondary' }} text-white">
                    <h6 class="card-title mb-0">
                        <i class="icon-{{ $this->getRecommendationIcon($recommendation['type']) }} me-2"></i>
                        {{ $recommendation['title'] }}
                    </h6>
                    <small class="opacity-75">{{ ucfirst($recommendation['priority']) }} Priority</small>
                </div>
                <div class="card-body">
                    <p class="card-text">{{ $recommendation['description'] }}</p>
                    
                    @if(isset($recommendation['current_value']) && isset($recommendation['target_value']))
                        <div class="progress-info mb-3">
                            <div class="d-flex justify-content-between">
                                <small>Current: {{ $recommendation['current_value'] }}</small>
                                <small>Target: {{ $recommendation['target_value'] }}</small>
                            </div>
                            <div class="progress mt-1">
                                <div class="progress-bar" 
                                     style="width: {{ min(($recommendation['current_value'] / $recommendation['target_value']) * 100, 100) }}%">
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <div class="recommendation-actions">
                        <button class="btn btn-sm btn-outline-{{ $priorityColors[$recommendation['priority']] ?? 'secondary' }}" 
                                onclick="implementRecommendation('{{ $recommendation['action'] }}', {{ $member['id'] ?? 0 }})">
                            Implement
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" 
                                onclick="dismissRecommendation('{{ $recommendation['type'] }}', {{ $member['id'] ?? 0 }})">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-info">
                <i class="icon-info-circle me-2"></i>
                Great job! No recommendations at this time. Keep up the excellent reading habits!
            </div>
        </div>
    @endforelse
</div>

<?php
// File: resources/views/orchid/analytics/member/insights.blade.php
?>
@php
    $analytics = $analytics ?? [];
    $member = $member ?? null;
    $period = $period ?? 'month';
    
    $summary = $analytics['summary'] ?? [];
    $wordAnalytics = $analytics['word_count_analytics'] ?? [];
    $engagement = $analytics['engagement_metrics'] ?? [];
    $patterns = $analytics['reading_patterns'] ?? [];
@endphp

<div class="insights-section">
    <div class="row">
        <!-- Key Insights -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="icon-lightbulb me-2"></i>Key Insights
                    </h5>
                </div>
                <div class="card-body">
                    <div class="insights-grid">
                        @if(!empty($summary))
                            <div class="insight-item mb-3">
                                <div class="insight-icon">
                                    <i class="icon-trending-up text-success"></i>
                                </div>
                                <div class="insight-content">
                                    <strong>Reading Progress:</strong> 
                                    {{ $member['name'] ?? 'This member' }} has read 
                                    {{ number_format($summary['total_words_read'] ?? 0) }} words 
                                    this {{ $period }}, completing {{ $summary['total_stories_completed'] ?? 0 }} stories.
                                </div>
                            </div>
                        @endif
                        
                        @if(!empty($wordAnalytics['reading_speed_analysis']))
                            <div class="insight-item mb-3">
                                <div class="insight-icon">
                                    <i class="icon-zap text-warning"></i>
                                </div>
                                <div class="insight-content">
                                    <strong>Reading Speed:</strong> 
                                    Average reading speed is {{ $wordAnalytics['reading_speed_analysis']['average_wpm'] ?? 0 }} 
                                    words per minute, which is {{ $this->getSpeedCategory($wordAnalytics['reading_speed_analysis']['average_wpm'] ?? 0) }}.
                                </div>
                            </div>
                        @endif
                        
                        @if(!empty($engagement['engagement_level']))
                            <div class="insight-item mb-3">
                                <div class="insight-icon">
                                    <i class="icon-heart text-danger"></i>
                                </div>
                                <div class="insight-content">
                                    <strong>Engagement:</strong> 
                                    {{ $member['name'] ?? 'This member' }} shows 
                                    {{ $engagement['engagement_level'] }} engagement with 
                                    {{ $engagement['engagement_score'] ?? 0 }}% interaction rate.
                                </div>
                            </div>
                        @endif
                        
                        @if(!empty($patterns['consistency_score']))
                            <div class="insight-item mb-3">
                                <div class="insight-icon">
                                    <i class="icon-calendar text-info"></i>
                                </div>
                                <div class="insight-content">
                                    <strong>Consistency:</strong> 
                                    Reading consistency score is {{ $patterns['consistency_score'] ?? 0 }}%, 
                                    {{ $this->getConsistencyMessage($patterns['consistency_score'] ?? 0) }}.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="icon-bar-chart me-2"></i>Quick Stats
                    </h5>
                </div>
                <div class="card-body">
                    <div class="quick-stats">
                        @if(!empty($summary))
                            <div class="stat-item mb-3">
                                <div class="stat-value">{{ $summary['current_streak'] ?? 0 }}</div>
                                <div class="stat-label">Day Streak</div>
                            </div>
                            
                            <div class="stat-item mb-3">
                                <div class="stat-value">{{ $summary['reading_level'] ?? 'beginner' }}</div>
                                <div class="stat-label">Reading Level</div>
                            </div>
                            
                            <div class="stat-item mb-3">
                                <div class="stat-value">{{ round($summary['period_completion_rate'] ?? 0, 1) }}%</div>
                                <div class="stat-label">Completion Rate</div>
                            </div>
                        @endif
                        
                        @if(!empty($wordAnalytics['reading_equivalents']))
                            <div class="stat-item mb-3">
                                <div class="stat-value">{{ $wordAnalytics['reading_equivalents']['books'] ?? 0 }}</div>
                                <div class="stat-label">Book Equivalents</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.achievement-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(0, 123, 255, 0.1);
}

.percentile-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 8px solid #007bff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.percentile-value {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
}

.insight-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.insight-icon {
    font-size: 1.5rem;
    margin-top: 5px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: rgba(0, 123, 255, 0.05);
    border-radius: 8px;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.recommendation-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.progress-info {
    background: rgba(0, 123, 255, 0.05);
    padding: 10px;
    border-radius: 5px;
}
</style>

<script>
function implementRecommendation(action, memberId) {
    // TODO: Implement recommendation action
    console.log('Implementing recommendation:', action, 'for member:', memberId);
    alert('Recommendation implementation will be added');
}

function dismissRecommendation(type, memberId) {
    // TODO: Implement recommendation dismissal
    console.log('Dismissing recommendation:', type, 'for member:', memberId);
    alert('Recommendation dismissal will be added');
}
</script>