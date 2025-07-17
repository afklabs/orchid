
declare(strict_types=1);
namespace App\Http\Resources;

<?php

// File: app/Http/Resources/MemberResource.php

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Member Resource
 * 
 * Formats member data for API responses
 * to the Flutter mobile application.
 * 
 * @package App\Http\Resources
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class MemberResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'country' => $this->country,
            'reading_level' => $this->reading_level,
            'preferences' => $this->preferences,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Display information
            'display_info' => [
                'name' => $this->name,
                'level' => ucfirst($this->reading_level ?? 'beginner'),
                'country' => $this->country,
                'member_since' => $this->created_at?->format('M Y'),
                'status_badge' => $this->getStatusBadge(),
            ],
            
            // Profile completeness
            'profile_completeness' => $this->calculateProfileCompleteness(),
            
            // Privacy settings
            'privacy' => [
                'show_in_leaderboard' => $this->preferences['show_in_leaderboard'] ?? true,
                'allow_friend_requests' => $this->preferences['allow_friend_requests'] ?? true,
                'share_reading_stats' => $this->preferences['share_reading_stats'] ?? true,
            ],
        ];
    }

    /**
     * Get status badge
     */
    private function getStatusBadge(): array
    {
        return match ($this->status) {
            'active' => ['text' => 'Active', 'color' => '#28a745'],
            'inactive' => ['text' => 'Inactive', 'color' => '#6c757d'],
            'suspended' => ['text' => 'Suspended', 'color' => '#dc3545'],
            default => ['text' => 'Unknown', 'color' => '#6c757d'],
        };
    }

    /**
     * Calculate profile completeness
     */
    private function calculateProfileCompleteness(): array
    {
        $fields = [
            'name' => !empty($this->name),
            'email' => !empty($this->email),
            'phone' => !empty($this->phone),
            'date_of_birth' => !empty($this->date_of_birth),
            'country' => !empty($this->country),
            'reading_level' => !empty($this->reading_level),
        ];
        
        $completed = array_sum($fields);
        $total = count($fields);
        $percentage = round(($completed / $total) * 100);
        
        return [
            'percentage' => $percentage,
            'completed_fields' => $completed,
            'total_fields' => $total,
            'missing_fields' => array_keys(array_filter($fields, fn($v) => !$v)),
        ];
    }
}