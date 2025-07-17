{{-- resources/views/orchid/story/analytics.blade.php --}}
<div class="analytics-section">
    <div class="row">
        <div class="col-md-12">
            <h5 class="mb-3">
                <i class="icon-graph text-info me-2"></i>
                Story Analytics
            </h5>
        </div>
    </div>

    {{-- Performance Metrics --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary">{{ number_format($story->views ?? 0) }}</h3>
                    <p class="text-muted">Views</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success">{{ number_format($story->likes ?? 0) }}</h3>
                    <p class="text-muted">Likes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info">{{ round($story->completion_rate ?? 0, 1) }}%</h3>
                    <p class="text-muted">Completion Rate</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning">{{ round($story->avg_rating ?? 0, 1) }}</h3>
                    <p class="text-muted">Avg Rating</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Reading Analytics --}}
    @if(!empty($analyticsData))
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6>Reading Patterns</h6>
                </div>
                <div class="card-body">
                    <canvas id="readingPatternsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6>Word Count Impact</h6>
                </div>
                <div class="card-body">
                    <div class="stat-item">
                        <label>Total Words Read:</label>
                        <span>{{ number_format($analyticsData['total_words_read'] ?? 0) }}</span>
                    </div>
                    <div class="stat-item">
                        <label>Unique Readers:</label>
                        <span>{{ number_format($analyticsData['unique_readers'] ?? 0) }}</span>
                    </div>
                    <div class="stat-item">
                        <label>Avg Reading Time:</label>
                        <span>{{ $analyticsData['avg_reading_time'] ?? 0 }} min</span>
                    </div>
                    <div class="stat-item">
                        <label>Engagement Score:</label>
                        <span>{{ round($analyticsData['engagement_score'] ?? 0, 1) }}%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
