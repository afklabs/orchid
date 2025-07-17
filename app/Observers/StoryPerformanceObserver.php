<?php

namespace App\Observers;

use App\Models\{Story, StoryRatingAggregate, MemberStoryRating};
use Illuminate\Support\Facades\Cache;

/**
 * Story Performance Observer
 * 
 * Automatically updates performance metrics when related data changes
 */
class StoryPerformanceObserver
{
    /**
     * Handle the MemberStoryRating "created" event.
     */
    public function ratingCreated(MemberStoryRating $rating): void
    {
        $this->updateStoryRatingAggregate($rating->story_id);
        $this->clearStoryPerformanceCache($rating->story_id);
    }

    /**
     * Handle the MemberStoryRating "updated" event.
     */
    public function ratingUpdated(MemberStoryRating $rating): void
    {
        $this->updateStoryRatingAggregate($rating->story_id);
        $this->clearStoryPerformanceCache($rating->story_id);
    }

    /**
     * Handle the MemberStoryRating "deleted" event.
     */
    public function ratingDeleted(MemberStoryRating $rating): void
    {
        $this->updateStoryRatingAggregate($rating->story_id);
        $this->clearStoryPerformanceCache($rating->story_id);
    }

    /**
     * Update story rating aggregate.
     */
    private function updateStoryRatingAggregate(int $storyId): void
    {
        try {
            StoryRatingAggregate::updateForStory($storyId);
        } catch (\Exception $e) {
            \Log::error('Failed to update story rating aggregate', [
                'story_id' => $storyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear story performance cache.
     */
    private function clearStoryPerformanceCache(int $storyId): void
    {
        $cacheKeys = [
            "story_performance_score_{$storyId}",
            "story_completion_rate_{$storyId}",
            "story_engagement_score_{$storyId}",
            "story_trending_score_{$storyId}",
            "story_detailed_metrics_{$storyId}",
            "story.{$storyId}.avg_rating",
            "story.{$storyId}.total_ratings",
            "story.{$storyId}.api_resource",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear platform-level caches
        Cache::forget('platform_story_metrics');
        Cache::forget('stories.top_performing_10');
        Cache::forget('stories.trending_10');
        Cache::forget('stories.needing_attention_10');
    }
}