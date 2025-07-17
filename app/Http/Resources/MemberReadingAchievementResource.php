declare(strict_types=1);

namespace App\Http\Resources;
<?php

// File: app/Http/Resources/MemberReadingAchievementResource.php



use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Member Reading Achievement Resource
 * 
 * Formats member reading achievement data for API responses
 * to the Flutter mobile application.
 * 
 * @package App\Http\Resources
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class MemberReadingAchievementResource extends JsonResource
{
    /**
     * Achievement type configurations
     */
    private const ACHIEVEMENT_TYPES = [
        'daily_reader' => ['icon' => 'calendar', 'color' => '#007bff'],
        'word_master' => ['icon' => 'book', 'color' => '#28a745'],
        'speed_reader' => ['icon' => 'zap', 'color' => '#ffc107'],
        'streak_keeper' => ['icon' => 'fire', 'color' => '#dc3545'],
        'level_climber' => ['icon' => 'trending-up', 'color' => '#17a2b8'],
        'category_explorer' => ['icon' => 'compass', 'color' => '#6c757d'],
        'engagement_star' => ['icon' => 'star', 'color' => '#ffc107'],
        'completion_champion' => ['icon' => 'trophy', 'color' => '#28a745'],
        'early_bird' => ['icon' => 'sunrise', 'color' => '#007bff'],
        'night_owl' => ['icon' => 'moon', 'color' => '#343a40'],
    ];

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
            'achievement_type' => $this->achievement_type,
            'level' => $this->level,
            'points_awarded' => $this->points_awarded,
            'achieved_at' => $this->achieved_at?->toISOString(),
            'is_claimed' => $this->is_claimed,
            'claimed_at' => $this->claimed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Achievement information
            'achievement_info' => $this->getAchievementInfo(),
            'display_info' => $this->getDisplayInfo(),
            'reward_info' => $this->getRewardInfo(),
            
            // Relationships
            'member' => new MemberResource($this->whenLoaded('member')),
        ];
    }

    /**
     * Get achievement information
     */
    private function getAchievementInfo(): array
    {
        $typeConfig = self::ACHIEVEMENT_TYPES[$this->achievement_type] ?? [];
        
        return [
            'type' => $this->achievement_type,
            'type_name' => $this->getAchievementTypeName(),
            'description' => $this->getAchievementDescription(),
            'icon' => $typeConfig['icon'] ?? 'trophy',
            'color' => $typeConfig['color'] ?? '#007bff',
            'category' => $this->getAchievementCategory(),
        ];
    }

    /**
     * Get display information
     */
    private function getDisplayInfo(): array
    {
        return [
            'title' => $this->getAchievementTitle(),
            'subtitle' => $this->getAchievementSubtitle(),
            'badge_text' => "Level {$this->level}",
            'points_text' => "{$this->points_awarded} points",
            'date_text' => $this->achieved_at?->format('M d, Y'),
            'time_ago' => $this->achieved_at?->diffForHumans(),
        ];
    }

    /**
     * Get reward information
     */
    private function getRewardInfo(): array
    {
        return [
            'points' => $this->points_awarded,
            'is_claimable' => !$this->is_claimed,
            'claim_status' => $this->is_claimed ? 'claimed' : 'available',
            'claimed_date' => $this->claimed_at?->format('M d, Y'),
            'unlock_features' => $this->getUnlockedFeatures(),
        ];
    }

    /**
     * Get achievement type name
     */
    private function getAchievementTypeName(): string
    {
        return match ($this->achievement_type) {
            'daily_reader' => 'Daily Reader',
            'word_master' => 'Word Master',
            'speed_reader' => 'Speed Reader',
            'streak_keeper' => 'Streak Keeper',
            'level_climber' => 'Level Climber',
            'category_explorer' => 'Category Explorer',
            'engagement_star' => 'Engagement Star',
            'completion_champion' => 'Completion Champion',
            'early_bird' => 'Early Bird',
            'night_owl' => 'Night Owl',
            default => 'Unknown Achievement',
        };
    }

    /**
     * Get achievement description
     */
    private function getAchievementDescription(): string
    {
        return match ($this->achievement_type) {
            'daily_reader' => 'Read consistently every day',
            'word_master' => 'Read a large number of words',
            'speed_reader' => 'Achieve high reading speeds',
            'streak_keeper' => 'Maintain long reading streaks',
            'level_climber' => 'Progress through reading levels',
            'category_explorer' => 'Read stories from different categories',
            'engagement_star' => 'Actively engage with the platform',
            'completion_champion' => 'Complete stories consistently',
            'early_bird' => 'Read consistently in the morning',
            'night_owl' => 'Read consistently in the evening',
            default => 'Special achievement unlocked',
        };
    }

    /**
     * Get achievement category
     */
    private function getAchievementCategory(): string
    {
        return match ($this->achievement_type) {
            'daily_reader', 'streak_keeper' => 'consistency',
            'word_master', 'completion_champion' => 'progress',
            'speed_reader' => 'performance',
            'level_climber' => 'advancement',
            'category_explorer' => 'exploration',
            'engagement_star' => 'social',
            'early_bird', 'night_owl' => 'habits',
            default => 'general',
        };
    }

    /**
     * Get achievement title
     */
    private function getAchievementTitle(): string
    {
        $levelTitles = [
            'daily_reader' => ['Week Warrior', 'Month Master', 'Consistency Champion', 'Dedication Expert', 'Daily Legend'],
            'word_master' => ['Word Seeker', 'Word Explorer', 'Word Champion', 'Word Master', 'Word Legend'],
            'speed_reader' => ['Quick Reader', 'Fast Reader', 'Speed Reader', 'Lightning Reader', 'Speed Master'],
            'streak_keeper' => ['Streak Starter', 'Streak Builder', 'Streak Maintainer', 'Streak Champion', 'Streak Legend'],
            'level_climber' => ['Level Learner', 'Level Builder', 'Level Climber', 'Level Master', 'Level Legend'],
            'category_explorer' => ['Genre Starter', 'Genre Explorer', 'Genre Adventurer', 'Genre Master', 'Genre Legend'],
            'engagement_star' => ['Engagement Starter', 'Engagement Builder', 'Engagement Champion', 'Engagement Master', 'Engagement Legend'],
            'completion_champion' => ['Story Finisher', 'Story Completer', 'Story Champion', 'Story Master', 'Story Legend'],
            'early_bird' => ['Morning Reader', 'Dawn Warrior', 'Early Bird', 'Sunrise Champion', 'Dawn Legend'],
            'night_owl' => ['Evening Reader', 'Night Reader', 'Night Owl', 'Midnight Champion', 'Night Legend'],
        ];

        $titles = $levelTitles[$this->achievement_type] ?? [];
        return $titles[$this->level - 1] ?? "Level {$this->level}";
    }

    /**
     * Get achievement subtitle
     */
    private function getAchievementSubtitle(): string
    {
        return $this->getAchievementTypeName() . " • Level {$this->level}";
    }

    /**
     * Get unlocked features
     */
    private function getUnlockedFeatures(): array
    {
        // This would typically return features unlocked by this achievement
        // For now, return empty array
        return [];
    }
}

