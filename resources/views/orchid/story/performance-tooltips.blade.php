{{-- resources/views/orchid/story/performance-tooltips.blade.php --}}
{{-- Enhanced Performance Tooltips with Interactive Features --}}

<style>
/* Enhanced Performance Indicators */
.performance-indicators {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.performance-badge {
    font-size: 1.2rem;
    line-height: 1;
    cursor: help;
    transition: transform 0.2s ease;
}

.performance-badge:hover {
    transform: scale(1.1);
}

.performance-score {
    font-weight: 700;
    font-size: 0.9rem;
    margin: 2px 0;
}

.performance-level {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Star Rating Enhancements */
.star-rating {
    color: #ffd700;
    font-size: 1rem;
    line-height: 1;
    letter-spacing: 1px;
}

.star-rating.excellent {
    color: #28a745;
}

.star-rating.good {
    color: #ffc107;
}

.star-rating.poor {
    color: #dc3545;
}

/* Progress Bar Enhancements */
.completion-progress {
    width: 100%;
    max-width: 80px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 4px;
}

.completion-progress .progress-bar {
    height: 100%;
    transition: width 0.3s ease;
    border-radius: 4px;
}

.completion-progress .progress-bar.excellent {
    background: linear-gradient(90deg, #28a745, #20c997);
}

.completion-progress .progress-bar.good {
    background: linear-gradient(90deg, #007bff, #17a2b8);
}

.completion-progress .progress-bar.average {
    background: linear-gradient(90deg, #ffc107, #fd7e14);
}

.completion-progress .progress-bar.poor {
    background: linear-gradient(90deg, #dc3545, #e83e8c);
}

/* Performance Tooltip Enhancements */
.performance-tooltip {
    max-width: 300px;
    padding: 12px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    font-size: 0.875rem;
    line-height: 1.4;
}

.performance-tooltip h6 {
    margin: 0 0 8px 0;
    color: #495057;
    font-weight: 600;
}

.performance-tooltip .metric-row {
    display: flex;
    justify-content: space-between;
    margin: 4px 0;
}

.performance-tooltip .metric-label {
    color: #6c757d;
}

.performance-tooltip .metric-value {
    font-weight: 500;
    color: #212529;
}

.performance-tooltip .rating-distribution {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    font-size: 0.8rem;
}

.performance-tooltip .rating-bar {
    display: flex;
    align-items: center;
    gap: 2px;
}

.performance-tooltip .rating-bar-fill {
    width: 20px;
    height: 4px;
    background: #ffd700;
    border-radius: 2px;
    opacity: 0.3;
}

.performance-tooltip .rating-bar-fill.active {
    opacity: 1;
}

/* Word Count Enhancements */
.word-count-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.word-count-number {
    font-weight: 600;
    font-size: 0.95rem;
    color: #495057;
}

.reading-time {
    font-size: 0.75rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 2px;
}

.reading-time::before {
    content: '📖';
    font-size: 0.8rem;
}

/* Views Enhancement */
.views-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.views-number {
    font-weight: 600;
    font-size: 0.95rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 4px;
}

.views-icon {
    font-size: 1rem;
    opacity: 0.8;
}

/* Status Badge Enhancements */
.status-badge {
    position: relative;
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-badge.inactive {
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #dee2e6;
}

.status-badge.draft {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-badge.scheduled {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.status-badge.active::before {
    content: '●';
    color: #28a745;
    margin-right: 4px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .performance-indicators {
        gap: 2px;
    }
    
    .performance-badge {
        font-size: 1rem;
    }
    
    .performance-score {
        font-size: 0.8rem;
    }
    
    .performance-level {
        font-size: 0.7rem;
    }
    
    .completion-progress {
        max-width: 60px;
        height: 6px;
    }
    
    .word-count-number,
    .views-number {
        font-size: 0.85rem;
    }
    
    .reading-time {
        font-size: 0.7rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .performance-tooltip {
        background: #343a40;
        border-color: #495057;
        color: #fff;
    }
    
    .performance-tooltip h6 {
        color: #f8f9fa;
    }
    
    .performance-tooltip .metric-label {
        color: #adb5bd;
    }
    
    .performance-tooltip .metric-value {
        color: #f8f9fa;
    }
    
    .word-count-number,
    .views-number {
        color: #f8f9fa;
    }
    
    .reading-time {
        color: #adb5bd;
    }
}

/* Enhanced Animations */
.performance-metrics-row {
    transition: all 0.3s ease;
}

.performance-metrics-row:hover {
    background: rgba(0, 123, 255, 0.05);
    transform: translateY(-1px);
}

.performance-metrics-row:hover .performance-badge {
    transform: scale(1.1);
}

.performance-metrics-row:hover .completion-progress .progress-bar {
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.3);
}

.performance-metrics-row:hover .star-rating {
    text-shadow: 0 0 8px rgba(255, 215, 0, 0.5);
}

/* Loading States */
.performance-loading {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Performance Insights */
.performance-insights {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 8px 12px;
    margin-top: 8px;
    border-radius: 0 4px 4px 0;
}

.performance-insights h7 {
    font-weight: 600;
    color: #495057;
    margin: 0 0 4px 0;
    font-size: 0.8rem;
}

.performance-insights ul {
    margin: 0;
    padding-left: 16px;
    font-size: 0.75rem;
    color: #6c757d;
}

.performance-insights li {
    margin: 2px 0;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 4px;
    margin-top: 8px;
}

.quick-action-btn {
    padding: 2px 6px;
    font-size: 0.7rem;
    border: 1px solid #dee2e6;
    background: #fff;
    color: #495057;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    background: #e9ecef;
    transform: translateY(-1px);
}

/* Accessibility Enhancements */
.performance-badge[aria-label]:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

.completion-progress[aria-label]:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* Print Styles */
@media print {
    .performance-badge {
        color: #000 !important;
    }
    
    .performance-tooltip {
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .completion-progress .progress-bar {
        background: #000 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced Performance Tooltips
    const performanceTooltips = {
        init() {
            this.initializeTooltips();
            this.initializeProgressBars();
            this.initializePerformanceUpdates();
        },

        initializeTooltips() {
            // Rating tooltips
            document.querySelectorAll('[data-rating-tooltip]').forEach(element => {
                const data = JSON.parse(element.dataset.ratingTooltip);
                
                tippy(element, {
                    content: this.createRatingTooltip(data),
                    allowHTML: true,
                    placement: 'top',
                    theme: 'light-border',
                    interactive: true,
                    maxWidth: 350,
                    animation: 'scale-subtle',
                });
            });

            // Completion tooltips
            document.querySelectorAll('[data-completion-tooltip]').forEach(element => {
                const data = JSON.parse(element.dataset.completionTooltip);
                
                tippy(element, {
                    content: this.createCompletionTooltip(data),
                    allowHTML: true,
                    placement: 'top',
                    theme: 'light-border',
                    interactive: true,
                    maxWidth: 300,
                    animation: 'scale-subtle',
                });
            });

            // Performance tooltips
            document.querySelectorAll('[data-performance-tooltip]').forEach(element => {
                const data = JSON.parse(element.dataset.performanceTooltip);
                
                tippy(element, {
                    content: this.createPerformanceTooltip(data),
                    allowHTML: true,
                    placement: 'top',
                    theme: 'light-border',
                    interactive: true,
                    maxWidth: 400,
                    animation: 'scale-subtle',
                });
            });
        },

        createRatingTooltip(data) {
            const distribution = data.distribution || {};
            const total = data.total || 0;
            
            let distributionHTML = '';
            if (total > 0) {
                for (let i = 5; i >= 1; i--) {
                    const count = distribution[i] || 0;
                    const percentage = total > 0 ? (count / total * 100).toFixed(1) : 0;
                    
                    distributionHTML += `
                        <div class="rating-bar">
                            <span>${i}★</span>
                            <div class="rating-bar-fill ${count > 0 ? 'active' : ''}"></div>
                            <span>${count} (${percentage}%)</span>
                        </div>
                    `;
                }
            }

            return `
                <div class="performance-tooltip">
                    <h6>⭐ Rating Details</h6>
                    <div class="metric-row">
                        <span class="metric-label">Average Rating:</span>
                        <span class="metric-value">${data.average}/5</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Total Ratings:</span>
                        <span class="metric-value">${data.total}</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Last Updated:</span>
                        <span class="metric-value">${data.updated_at}</span>
                    </div>
                    ${distributionHTML ? `
                        <div class="rating-distribution">
                            <h7>Rating Distribution</h7>
                            ${distributionHTML}
                        </div>
                    ` : ''}
                    <div class="performance-insights">
                        <h7>💡 Insights</h7>
                        <ul>
                            <li>Ratings help improve story recommendations</li>
                            <li>Average rating impacts story visibility</li>
                            <li>Encourage readers to rate stories</li>
                        </ul>
                    </div>
                </div>
            `;
        },

        createCompletionTooltip(data) {
            const insights = this.getCompletionInsights(data.percentage);
            
            return `
                <div class="performance-tooltip">
                    <h6>📊 Completion Details</h6>
                    <div class="metric-row">
                        <span class="metric-label">Completion Rate:</span>
                        <span class="metric-value">${data.percentage}%</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Completed Readers:</span>
                        <span class="metric-value">${data.completed}</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Total Readers:</span>
                        <span class="metric-value">${data.total}</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Avg. Reading Time:</span>
                        <span class="metric-value">${data.avg_time || 'N/A'}</span>
                    </div>
                    <div class="performance-insights">
                        <h7>💡 Insights</h7>
                        <ul>
                            ${insights.map(insight => `<li>${insight}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            `;
        },

        createPerformanceTooltip(data) {
            const recommendations = this.getPerformanceRecommendations(data.score);
            
            return `
                <div class="performance-tooltip">
                    <h6>${data.badge} Performance Score</h6>
                    <div class="metric-row">
                        <span class="metric-label">Overall Score:</span>
                        <span class="metric-value">${data.score}/100</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Performance Level:</span>
                        <span class="metric-value">${data.level}</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Views Impact:</span>
                        <span class="metric-value">${data.views_score || 0}/30</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Completion Impact:</span>
                        <span class="metric-value">${data.completion_score || 0}/25</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Rating Impact:</span>
                        <span class="metric-value">${data.rating_score || 0}/20</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Popularity Impact:</span>
                        <span class="metric-value">${data.popularity_score || 0}/15</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Freshness Impact:</span>
                        <span class="metric-value">${data.freshness_score || 0}/10</span>
                    </div>
                    <div class="performance-insights">
                        <h7>🚀 Recommendations</h7>
                        <ul>
                            ${recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="window.open('/platform/stories/${data.story_id}/analytics', '_blank')">
                            📈 View Analytics
                        </button>
                        <button class="quick-action-btn" onclick="window.open('/platform/stories/${data.story_id}/edit', '_blank')">
                            ✏️ Edit Story
                        </button>
                    </div>
                </div>
            `;
        },

        getCompletionInsights(percentage) {
            const insights = [];
            
            if (percentage >= 80) {
                insights.push("Excellent engagement! Readers love this story");
                insights.push("Consider featuring this story prominently");
            } else if (percentage >= 60) {
                insights.push("Good completion rate for this content type");
                insights.push("Monitor for sustained performance");
            } else if (percentage >= 40) {
                insights.push("Average completion - room for improvement");
                insights.push("Consider reviewing story structure");
            } else {
                insights.push("Low completion rate needs attention");
                insights.push("Review story length and pacing");
                insights.push("Check if content matches target audience");
            }
            
            return insights;
        },

        getPerformanceRecommendations(score) {
            const recommendations = [];
            
            if (score >= 80) {
                recommendations.push("Excellent performance! Use as template");
                recommendations.push("Share promotion strategies");
                recommendations.push("Monitor for sustained excellence");
            } else if (score >= 60) {
                recommendations.push("Good performance with growth potential");
                recommendations.push("Focus on increasing reader engagement");
                recommendations.push("Optimize for higher completion rates");
            } else if (score >= 40) {
                recommendations.push("Average performance needs improvement");
                recommendations.push("Review content quality and relevance");
                recommendations.push("Enhance story promotion");
            } else {
                recommendations.push("Poor performance requires immediate attention");
                recommendations.push("Consider content revision or removal");
                recommendations.push("Analyze reader feedback and behavior");
            }
            
            return recommendations;
        },

        initializeProgressBars() {
            // Animate progress bars on page load
            document.querySelectorAll('.completion-progress .progress-bar').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        },

        initializePerformanceUpdates() {
            // Auto-refresh performance data every 5 minutes
            setInterval(() => {
                this.refreshPerformanceData();
            }, 300000);
        },

        refreshPerformanceData() {
            // Refresh performance metrics without page reload
            fetch('/platform/stories/performance-metrics', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                this.updatePerformanceMetrics(data);
            })
            .catch(error => {
                console.error('Error refreshing performance data:', error);
            });
        },

        updatePerformanceMetrics(data) {
            // Update performance metrics in real-time
            data.forEach(story => {
                const row = document.querySelector(`[data-story-id="${story.id}"]`);
                if (row) {
                    this.updateStoryMetrics(row, story);
                }
            });
        },

        updateStoryMetrics(row, story) {
            // Update individual story metrics
            const ratingElement = row.querySelector('.rating-display');
            const completionElement = row.querySelector('.completion-display');
            const performanceElement = row.querySelector('.performance-display');
            
            if (ratingElement) {
                ratingElement.innerHTML = this.formatRating(story.rating);
            }
            
            if (completionElement) {
                completionElement.innerHTML = this.formatCompletion(story.completion);
            }
            
            if (performanceElement) {
                performanceElement.innerHTML = this.formatPerformance(story.performance);
            }
        },

        formatRating(rating) {
            // Format rating display
            const stars = '★'.repeat(Math.floor(rating.average)) + '☆'.repeat(5 - Math.floor(rating.average));
            return `<div class="star-rating">${stars}</div><small>${rating.average} (${rating.total})</small>`;
        },

        formatCompletion(completion) {
            // Format completion display
            const percentage = completion.percentage;
            const progressClass = percentage >= 70 ? 'excellent' : percentage >= 50 ? 'good' : percentage >= 30 ? 'average' : 'poor';
            
            return `
                <div class="completion-progress">
                    <div class="progress-bar ${progressClass}" style="width: ${percentage}%"></div>
                </div>
                <small>${percentage}%</small>
            `;
        },

        formatPerformance(performance) {
            // Format performance display
            const colors = {
                'excellent': 'success',
                'good': 'primary',
                'average': 'warning',
                'poor': 'danger'
            };
            
            const color = colors[performance.level] || 'secondary';
            
            return `
                <div class="performance-indicators">
                    <div class="performance-badge">${performance.badge}</div>
                    <div class="performance-score text-${color}">${performance.score}/100</div>
                    <div class="performance-level text-${color}">${performance.level}</div>
                </div>
            `;
        }
    };

    // Initialize performance tooltips
    performanceTooltips.init();

    // Handle bulk actions
    document.getElementById('bulk-select-all')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="selected[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Handle individual checkbox changes
    document.querySelectorAll('input[name="selected[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allCheckboxes = document.querySelectorAll('input[name="selected[]"]');
            const checkedCheckboxes = document.querySelectorAll('input[name="selected[]"]:checked');
            const selectAllCheckbox = document.getElementById('bulk-select-all');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            }
        });
    });

    // Performance alerts
    document.querySelectorAll('[data-performance-alert]').forEach(element => {
        const alertType = element.dataset.performanceAlert;
        
        if (alertType === 'trending') {
            element.style.animation = 'pulse 2s infinite';
        } else if (alertType === 'declining') {
            element.style.border = '2px solid #dc3545';
            element.style.borderRadius = '4px';
        }
    });

    // Auto-save filter preferences
    document.querySelectorAll('[data-filter]').forEach(filter => {
        filter.addEventListener('change', function() {
            const filterData = {
                name: this.name,
                value: this.value
            };
            
            localStorage.setItem('story_list_filters', JSON.stringify(filterData));
        });
    });

    // Load saved filter preferences
    const savedFilters = localStorage.getItem('story_list_filters');
    if (savedFilters) {
        const filterData = JSON.parse(savedFilters);
        const filterElement = document.querySelector(`[name="${filterData.name}"]`);
        if (filterElement) {
            filterElement.value = filterData.value;
        }
    }
});
</script>