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


