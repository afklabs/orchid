<?php

// File: app/Http/Resources/MemberReadingStatisticsResource.php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

/**
 * Member Reading Statistics Resource
 * 
 * Formats member reading statistics data for API responses
 * to the Flutter mobile application.
 * 
 * @package App\Http\Resources
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class MemberReadingStatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'date' => $this->date,
            'words_read' => $this->words_read,
            'reading_time_minutes' => $this->reading_time_minutes,
            'stories_completed' => $this->stories_completed,
            'reading_streak' => $this->reading_streak,
            'longest_streak' => $this->longest_streak,
            'reading_level' => $this->reading_level,
            'efficiency_score' => $this->efficiency_score,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Computed attributes
            'words_per_minute' => $this->reading_time_minutes > 0 
                ? round($this->words_read / $this->reading_time_minutes, 1) 
                : 0,
            'reading_time_formatted' => $this->formatReadingTime($this->reading_time_minutes),
            'streak_status' => $this->getStreakStatus(),
            'level_progress' => $this->getLevelProgress(),
            
            // Relationships
            'member' => new MemberResource($this->whenLoaded('member')),
        ];
    }

    /**
     * Format reading time in minutes to human-readable format
     */
    private function formatReadingTime(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . 'm';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    /**
     * Get streak status
     */
    private function getStreakStatus(): array
    {
        $streak = $this->reading_streak;
        
        return [
            'current_streak' => $streak,
            'status' => match (true) {
                $streak == 0 => 'no_streak',
                $streak < 7 => 'building',
                $streak < 30 => 'strong',
                $streak < 100 => 'excellent',
                default => 'legendary',
            },
            'next_milestone' => $this->getNextStreakMilestone($streak),
        ];
    }

    /**
     * Get next streak milestone
     */
    private function getNextStreakMilestone(int $currentStreak): ?int
    {
        $milestones = [7, 14, 30, 60, 100, 365];
        
        foreach ($milestones as $milestone) {
            if ($currentStreak < $milestone) {
                return $milestone;
            }
        }
        
        return null;
    }

    /**
     * Get level progress
     */
    private function getLevelProgress(): array
    {
        $levels = ['beginner', 'elementary', 'intermediate', 'advanced', 'expert', 'master'];
        $currentIndex = array_search($this->reading_level, $levels);
        
        return [
            'current_level' => $this->reading_level,
            'level_index' => $currentIndex,
            'next_level' => $levels[$currentIndex + 1] ?? null,
            'progress_percentage' => $this->calculateLevelProgress(),
        ];
    }

    /**
     * Calculate level progress percentage
     */
    private function calculateLevelProgress(): int
    {
        // This would typically be calculated based on reading performance
        // For now, return a placeholder
        return 0;
    }
}
