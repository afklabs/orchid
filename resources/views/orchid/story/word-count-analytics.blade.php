{{-- resources/views/orchid/story/word-count-analytics.blade.php --}}
<div class="bg-white rounded-lg shadow-sm border p-4 mb-4">
    <div class="row">
        <div class="col-md-12">
            <h5 class="mb-3">
                <i class="icon-chart text-primary me-2"></i>
                Word Count Analytics
            </h5>
        </div>
    </div>

    <div class="row">
        {{-- Real-time Word Count Display --}}
        <div class="col-md-3">
            <div class="metric-card bg-primary text-white rounded p-3 mb-3">
                <div class="metric-number" id="word-count-display">
                    {{ number_format($wordCountData['word_count'] ?? 0) }}
                </div>
                <div class="metric-label">Words</div>
                <div class="metric-progress">
                    <div class="progress progress-sm">
                        <div class="progress-bar" role="progressbar" 
                             style="width: {{ min(100, (($wordCountData['word_count'] ?? 0) / 1000) * 100) }}%"></div>
                    </div>
                    <small class="text-light">Target: 1,000 words</small>
                </div>
            </div>
        </div>

        {{-- Reading Level --}}
        <div class="col-md-3">
            <div class="metric-card bg-success text-white rounded p-3 mb-3">
                <div class="metric-number" id="reading-level-display">
                    {{ ucfirst($wordCountData['reading_level'] ?? 'Intermediate') }}
                </div>
                <div class="metric-label">Reading Level</div>
                <div class="metric-info">
                    <small class="text-light">
                        @switch($wordCountData['reading_level'] ?? 'intermediate')
                            @case('beginner')
                                ≤500 words
                                @break
                            @case('intermediate')
                                501-1500 words
                                @break
                            @case('advanced')
                                >1500 words
                                @break
                        @endswitch
                    </small>
                </div>
            </div>
        </div>

        {{-- Reading Time --}}
        <div class="col-md-3">
            <div class="metric-card bg-info text-white rounded p-3 mb-3">
                <div class="metric-number" id="reading-time-display">
                    {{ $wordCountData['estimated_reading_time'] ?? 1 }}
                </div>
                <div class="metric-label">Minutes</div>
                <div class="metric-info">
                    <small class="text-light">Estimated reading time</small>
                </div>
            </div>
        </div>

        {{-- Readability Score --}}
        <div class="col-md-3">
            <div class="metric-card bg-warning text-white rounded p-3 mb-3">
                <div class="metric-number" id="readability-display">
                    {{ round($wordCountData['readability_score'] ?? 0, 1) }}%
                </div>
                <div class="metric-label">Readability</div>
                <div class="metric-info">
                    <small class="text-light">Content clarity score</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Statistics --}}
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Detailed Content Analysis</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>Word Count Analytics</h6>
                </div>
                <div class="card-body">
                    <div class="metric-row">
                        <span>Word Count:</span>
                        <strong>{{ number_format($story->word_count ?? 0) }}</strong>
                    </div>
                    <div class="metric-row">
                        <span>Reading Level:</span>
                        <strong>{{ ucfirst($story->reading_level ?? 'intermediate') }}</strong>
                    </div>
                    <div class="metric-row">
                        <span>Est. Reading Time:</span>
                        <strong>{{ $story->reading_time_minutes ?? 0 }} min</strong>
                    </div>
                    <div class="metric-row">
                        <span>Actual Reading Time:</span>
                        <strong>{{ round($performanceMetrics['reading_time_actual'] ?? 0, 1) }} min</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Performance Trends --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6>Performance Trends</h6>
                </div>
                <div class="card-body">
                    <canvas id="performanceTrendsChart" width="800" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.metric-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.metric-row:last-child {
    border-bottom: none;
}

.metric-row span {
    color: #666;
}

.metric-row strong {
    color: #333;
}
</style>

{{-- resources/views/orchid/story/publishing-history.blade.php --}}
<div class="publishing-history-section">
    <div class="row">
        <div class="col-md-12">
            <h5 class="mb-3">
                <i class="icon-clock text-secondary me-2"></i>
                Publishing History
            </h5>
        </div>
    </div>

    @if(!empty($publishingData) && count($publishingData) > 0)
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6>Recent Publishing Actions</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Admin</th>
                                    <th>Status Change</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($publishingData as $history)
                                <tr>
                                    <td>
                                        {{ \Carbon\Carbon::parse($history['created_at'])->format('M j, Y H:i') }}
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $history['action'] === 'published' ? 'success' : 'warning' }}">
                                            {{ ucfirst($history['action']) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $history['user']['name'] ?? 'System' }}
                                    </td>
                                    <td>
                                        @if($history['previous_active_status'] !== $history['new_active_status'])
                                            <span class="text-{{ $history['new_active_status'] ? 'success' : 'danger' }}">
                                                {{ $history['previous_active_status'] ? 'Active' : 'Inactive' }} 
                                                → 
                                                {{ $history['new_active_status'] ? 'Active' : 'Inactive' }}
                                            </span>
                                        @else
                                            <span class="text-muted">No change</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $history['reason'] ?? 'No reason provided' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info">
                <i class="icon-info me-2"></i>
                No publishing history available for this story.
            </div>
        </div>
    </div>
    @endif

    {{-- Publishing Actions --}}
    @if($story->exists)
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6>Quick Publishing Actions</h6>
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        @if(!$story->active)
                        <button type="button" class="btn btn-success" onclick="publishStory()">
                            <i class="icon-check me-1"></i>
                            Publish Now
                        </button>
                        @else
                        <button type="button" class="btn btn-warning" onclick="unpublishStory()">
                            <i class="icon-pause me-1"></i>
                            Unpublish
                        </button>
                        @endif
                        
                        <button type="button" class="btn btn-info" onclick="schedulePublishing()">
                            <i class="icon-calendar me-1"></i>
                            Schedule Publishing
                        </button>
                        
                        <button type="button" class="btn btn-secondary" onclick="extendPublishing()">
                            <i class="icon-clock me-1"></i>
                            Extend Publishing
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Publishing Actions JavaScript --}}
<script>
function publishStory() {
    if (confirm('Are you sure you want to publish this story immediately?')) {
        fetch(`/admin/story/{{ $story->id }}/quick-publish`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error publishing story: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while publishing the story.');
        });
    }
}

function unpublishStory() {
    if (confirm('Are you sure you want to unpublish this story?')) {
        fetch(`/admin/story/{{ $story->id }}/quick-unpublish`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error unpublishing story: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while unpublishing the story.');
        });
    }
}

function schedulePublishing() {
    // TODO: Implement scheduling modal
    alert('Scheduling functionality will be implemented in the next phase.');
}

function extendPublishing() {
    // TODO: Implement extend publishing modal
    alert('Extend publishing functionality will be implemented in the next phase.');
}
</script>">
                            <div class="stat-item">
                                <label>Characters:</label>
                                <span id="character-count">{{ number_format($wordCountData['character_count'] ?? 0) }}</span>
                            </div>
                            <div class="stat-item">
                                <label>Paragraphs:</label>
                                <span id="paragraph-count">{{ $wordCountData['paragraph_count'] ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-item">
                                <label>Sentences:</label>
                                <span id="sentence-count">{{ $wordCountData['sentence_count'] ?? 0 }}</span>
                            </div>
                            <div class="stat-item">
                                <label>Unique Words:</label>
                                <span id="unique-words">{{ $wordCountData['unique_words_count'] ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-item">
                                <label>Avg Words/Sentence:</label>
                                <span id="avg-words-sentence">{{ $wordCountData['average_words_per_sentence'] ?? 0 }}</span>
                            </div>
                            <div class="stat-item">
                                <label>Complexity Score:</label>
                                <span id="complexity-score">{{ round(($wordCountData['complexity_score'] ?? 0) * 100, 1) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Real-time Analysis JavaScript --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    let analysisTimeout;
    
    // Get content editor element
    const contentEditor = document.querySelector('[name="story[content]"]');
    
    if (contentEditor) {
        // Monitor content changes
        contentEditor.addEventListener('input', function() {
            clearTimeout(analysisTimeout);
            analysisTimeout = setTimeout(updateWordCountAnalysis, 1000);
        });
        
        // For Quill editor
        if (window.quill) {
            window.quill.on('text-change', function() {
                clearTimeout(analysisTimeout);
                analysisTimeout = setTimeout(updateWordCountAnalysis, 1000);
            });
        }
    }
    
    function updateWordCountAnalysis() {
        const content = getContentText();
        
        if (!content) {
            return;
        }
        
        fetch('/admin/api/analyze-content', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ content: content })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAnalyticsDisplay(data.analysis);
            }
        })
        .catch(error => {
            console.error('Error analyzing content:', error);
        });
    }
    
    function getContentText() {
        if (window.quill) {
            return window.quill.getText();
        }
        
        const contentEditor = document.querySelector('[name="story[content]"]');
        return contentEditor ? contentEditor.value : '';
    }
    
    function updateAnalyticsDisplay(analysis) {
        // Update word count
        document.getElementById('word-count-display').textContent = 
            new Intl.NumberFormat().format(analysis.word_count);
        
        // Update reading level
        document.getElementById('reading-level-display').textContent = 
            analysis.reading_level.charAt(0).toUpperCase() + analysis.reading_level.slice(1);
        
        // Update reading time
        document.getElementById('reading-time-display').textContent = 
            analysis.estimated_reading_time;
        
        // Update readability
        document.getElementById('readability-display').textContent = 
            Math.round(analysis.readability_score * 10) / 10 + '%';
        
        // Update detailed stats
        document.getElementById('character-count').textContent = 
            new Intl.NumberFormat().format(analysis.character_count);
        document.getElementById('paragraph-count').textContent = 
            analysis.paragraph_count;
        document.getElementById('sentence-count').textContent = 
            analysis.sentence_count;
        document.getElementById('unique-words').textContent = 
            analysis.unique_words_count;
        document.getElementById('avg-words-sentence').textContent = 
            analysis.average_words_per_sentence;
        document.getElementById('complexity-score').textContent = 
            Math.round(analysis.complexity_score * 100) + '%';
        
        // Update progress bar
        const progressBar = document.querySelector('.progress-bar');
        const percentage = Math.min(100, (analysis.word_count / 1000) * 100);
        progressBar.style.width = percentage + '%';
        
        // Update reading level in hidden field
        const readingLevelField = document.querySelector('[name="story[reading_level]"]');
        if (readingLevelField) {
            readingLevelField.value = analysis.reading_level;
        }
    }
});
</script>

<style>
.metric-card {
    text-align: center;
    border-radius: 8px;
}

.metric-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1.2;
}

.metric-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.metric-progress {
    margin-top: 0.5rem;
}

.progress-sm {
    height: 4px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 0.25rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-item label {
    font-weight: 500;
    color: #666;
}

.stat-item span {
    font-weight: 600;
    color: #333;
}
</style>

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