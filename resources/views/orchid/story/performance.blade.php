{{-- resources/views/orchid/story/performance.blade.php --}}
<div class="performance-section">
    <div class="row">
        <div class="col-md-12">
            <h5 class="mb-3">
                <i class="icon-speedometer text-warning me-2"></i>
                Performance Metrics
            </h5>
        </div>
    </div>

    {{-- Key Performance Indicators --}}
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>Engagement Metrics</h6>
                </div>
                <div class="card-body">
                    <div class="metric-row">
                        <span>Views:</span>
                        <strong>{{ number_format($performanceMetrics['views'] ?? 0) }}</strong>
                    </div>
                    <div class="metric-row">
                        <span>Likes:</span>
                        <strong>{{ number_format($performanceMetrics['likes'] ?? 0) }}</strong>
                    </div>
                    <div class="metric-row">
                        <span>Comments:</span>
                        <strong>{{ number_format($performanceMetrics['comments_count'] ?? 0) }}</strong>
                    </div>
                    <div class="metric-row">
                        <span>Shares:</span>
                        <strong>{{ number_format($performanceMetrics['social_shares'] ?? 0) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>Reading Performance</h6>
                </div>
                <div class="card-body">
                    <div class="metric-row">
                        <span>Completion Rate:</span>
                        <strong>{{ round($performanceMetrics['completion_rate'] ?? 0, 1) }}%</strong>
                    </div>
                    <div class="metric-row">
                        <span>Avg Rating:</span>
                        <strong>{{ round($performanceMetrics['avg_rating'] ?? 0, 1) }}/5</strong>
                    </div>
                    <div class="metric-row">
                        <span>Total Ratings:</span>
                        <strong>{{ number_format($performanceMetrics['total_ratings'] ?? 0) }}</strong>
                    </div>
                    <div class="metric-row">
                        <span>Bounce Rate:</span>
                        <strong>{{ round($performanceMetrics['bounce_rate'] ?? 0, 1) }}%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4