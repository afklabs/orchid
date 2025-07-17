<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Member, MemberReadingAchievements, MemberReadingStatistics};
use App\Http\Resources\MemberReadingAchievementResource;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Validator, Log, DB};
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * Achievements API Controller
 * 
 * Manages reading achievements system for the Flutter mobile application.
 * Provides endpoints for achievement tracking, progress monitoring,
 * and reward claiming functionality.
 * 
 * Endpoints:
 * - GET /api/v1/achievements/member/{id} - Get member achievements
 * - GET /api/v1/achievements/member/{id}/progress - Get achievement progress
 * - GET /api/v1/achievements/available - Get available achievements
 * - POST /api/v1/achievements/claim - Claim achievement reward
 * - GET /api/v1/achievements/leaderboard - Get achievement leaderboard
 * - GET /api/v1/achievements/recent - Get recent achievements
 * 
 * Achievement Types:
 * - daily_reader: Daily reading consistency
 * - word_master: Word count milestones
 * - speed_reader: Reading speed achievements
 * - streak_keeper: Reading streak achievements
 * - level_climber: Reading level progression
 * - category_explorer: Category diversity
 * - engagement_star: Platform engagement
 * - completion_champion: Story completion
 * - early_bird: Morning reading habits
 * - night_owl: Evening reading habits
 * 
 * @package App\Http\Controllers\Api\V1
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class AchievementsController extends Controller
{
    /**
     * Cache TTL configuration
     */
    private const CACHE_TTL = [
        'member_achievements' => 1800,    // 30 minutes
        'achievement_progress' => 900,    // 15 minutes
        'available_achievements' => 3600, // 1 hour
        'leaderboard' => 1800,           // 30 minutes
    ];

    /**
     * Achievement definitions
     */
    private const ACHIEVEMENT_TYPES = [
        'daily_reader' => [
            'name' => 'Daily Reader',
            'description' => 'Read every day consistently',
            'icon' => 'calendar',
            'levels' => [
                1 => ['requirement' => 7, 'points' => 50, 'title' => 'Week Warrior'],
                2 => ['requirement' => 30, 'points' => 200, 'title' => 'Month Master'],
                3 => ['requirement' => 60, 'points' => 500, 'title' => 'Consistency Champion'],
                4 => ['requirement' => 100, 'points' => 1000, 'title' => 'Dedication Expert'],
                5 => ['requirement' => 365, 'points' => 2500, 'title' => 'Daily Legend'],
            ],
        ],
        'word_master' => [
            'name' => 'Word Master',
            'description' => 'Read a large number of words',
            'icon' => 'book',
            'levels' => [
                1 => ['requirement' => 10000, 'points' => 100, 'title' => 'Word Seeker'],
                2 => ['requirement' => 50000, 'points' => 300, 'title' => 'Word Explorer'],
                3 => ['requirement' => 150000, 'points' => 700, 'title' => 'Word Champion'],
                4 => ['requirement' => 500000, 'points' => 1500, 'title' => 'Word Master'],
                5 => ['requirement' => 1000000, 'points' => 3000, 'title' => 'Word Legend'],
            ],
        ],
        'speed_reader' => [
            'name' => 'Speed Reader',
            'description' => 'Achieve high reading speeds',
            'icon' => 'zap',
            'levels' => [
                1 => ['requirement' => 250, 'points' => 75, 'title' => 'Quick Reader'],
                2 => ['requirement' => 350, 'points' => 200, 'title' => 'Fast Reader'],
                3 => ['requirement' => 450, 'points' => 400, 'title' => 'Speed Reader'],
                4 => ['requirement' => 600, 'points' => 800, 'title' => 'Lightning Reader'],
                5 => ['requirement' => 800, 'points' => 1600, 'title' => 'Speed Master'],
            ],
        ],
        'streak_keeper' => [
            'name' => 'Streak Keeper',
            'description' => 'Maintain long reading streaks',
            'icon' => 'fire',
            'levels' => [
                1 => ['requirement' => 5, 'points' => 50, 'title' => 'Streak Starter'],
                2 => ['requirement' => 15, 'points' => 150, 'title' => 'Streak Builder'],
                3 => ['requirement' => 30, 'points' => 350, 'title' => 'Streak Maintainer'],
                4 => ['requirement' => 50, 'points' => 700, 'title' => 'Streak Champion'],
                5 => ['requirement' => 100, 'points' => 1500, 'title' => 'Streak Legend'],
            ],
        ],
        'level_climber' => [
            'name' => 'Level Climber',
            'description' => 'Progress through reading levels',
            'icon' => 'trending-up',
            'levels' => [
                1 => ['requirement' => 'elementary', 'points' => 100, 'title' => 'Level Learner'],
                2 => ['requirement' => 'intermediate', 'points' => 200, 'title' => 'Level Builder'],
                3 => ['requirement' => 'advanced', 'points' => 400, 'title' => 'Level Climber'],
                4 => ['requirement' => 'expert', 'points' => 800, 'title' => 'Level Master'],
                5 => ['requirement' => 'master', 'points' => 1600, 'title' => 'Level Legend'],
            ],
        ],
        'category_explorer' => [
            'name' => 'Category Explorer',
            'description' => 'Read stories from different categories',
            'icon' => 'compass',
            'levels' => [
                1 => ['requirement' => 3, 'points' => 50, 'title' => 'Genre Starter'],
                2 => ['requirement' => 5, 'points' => 125, 'title' => 'Genre Explorer'],
                3 => ['requirement' => 8, 'points' => 250, 'title' => 'Genre Adventurer'],
                4 => ['requirement' => 12, 'points' => 500, 'title' => 'Genre Master'],
                5 => ['requirement' => 15, 'points' => 1000, 'title' => 'Genre Legend'],
            ],
        ],
        'engagement_star' => [
            'name' => 'Engagement Star',
            'description' => 'Actively engage with the platform',
            'icon' => 'star',
            'levels' => [
                1 => ['requirement' => 10, 'points' => 25, 'title' => 'Engagement Starter'],
                2 => ['requirement' => 50, 'points' => 100, 'title' => 'Engagement Builder'],
                3 => ['requirement' => 150, 'points' => 250, 'title' => 'Engagement Champion'],
                4 => ['requirement' => 400, 'points' => 500, 'title' => 'Engagement Master'],
                5 => ['requirement' => 1000, 'points' => 1000, 'title' => 'Engagement Legend'],
            ],
        ],
        'completion_champion' => [
            'name' => 'Completion Champion',
            'description' => 'Complete stories consistently',
            'icon' => 'trophy',
            'levels' => [
                1 => ['requirement' => 10, 'points' => 100, 'title' => 'Story Finisher'],
                2 => ['requirement' => 50, 'points' => 300, 'title' => 'Story Completer'],
                3 => ['requirement' => 150, 'points' => 600, 'title' => 'Story Champion'],
                4 => ['requirement' => 400, 'points' => 1200, 'title' => 'Story Master'],
                5 => ['requirement' => 1000, 'points' => 2500, 'title' => 'Story Legend'],
            ],
        ],
        'early_bird' => [
            'name' => 'Early Bird',
            'description' => 'Read consistently in the morning',
            'icon' => 'sunrise',
            'levels' => [
                1 => ['requirement' => 5, 'points' => 50, 'title' => 'Morning Reader'],
                2 => ['requirement' => 15, 'points' => 150, 'title' => 'Dawn Warrior'],
                3 => ['requirement' => 30, 'points' => 300, 'title' => 'Early Bird'],
                4 => ['requirement' => 60, 'points' => 600, 'title' => 'Sunrise Champion'],
                5 => ['requirement' => 120, 'points' => 1200, 'title' => 'Dawn Legend'],
            ],
        ],
        'night_owl' => [
            'name' => 'Night Owl',
            'description' => 'Read consistently in the evening',
            'icon' => 'moon',
            'levels' => [
                1 => ['requirement' => 5, 'points' => 50, 'title' => 'Evening Reader'],
                2 => ['requirement' => 15, 'points' => 150, 'title' => 'Night Reader'],
                3 => ['requirement' => 30, 'points' => 300, 'title' => 'Night Owl'],
                4 => ['requirement' => 60, 'points' => 600, 'title' => 'Midnight Champion'],
                5 => ['requirement' => 120, 'points' => 1200, 'title' => 'Night Legend'],
            ],
        ],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:120,1'); // Higher limit for achievements
        $this->middleware('verified.device');
    }

    /**
     * Get member achievements
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberAchievements(int $id, Request $request): JsonResponse
    {
        try {
            // Validate member access
            if (!$this->validateMemberAccess($id, $request)) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Get member achievements with caching
            $cacheKey = "achievements:member:{$id}";
            
            $achievements = Cache::remember($cacheKey, self::CACHE_TTL['member_achievements'], function () use ($id) {
                return MemberReadingAchievements::where('member_id', $id)
                    ->with('member')
                    ->orderBy('achieved_at', 'desc')
                    ->get()
                    ->map(function ($achievement) {
                        $achievementInfo = $this->getAchievementInfo($achievement->achievement_type, $achievement->level);
                        
                        return [
                            'id' => $achievement->id,
                            'achievement_type' => $achievement->achievement_type,
                            'level' => $achievement->level,
                            'points_awarded' => $achievement->points_awarded,
                            'achieved_at' => $achievement->achieved_at,
                            'is_claimed' => $achievement->is_claimed,
                            'claimed_at' => $achievement->claimed_at,
                            'achievement_info' => $achievementInfo,
                        ];
                    });
            });

            return $this->successResponse($achievements->toArray(), 'Member achievements retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving member achievements', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve achievements', 500);
        }
    }

    /**
     * Get achievement progress for a member
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getAchievementProgress(int $id, Request $request): JsonResponse
    {
        try {
            // Validate member access
            if (!$this->validateMemberAccess($id, $request)) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Get achievement progress with caching
            $cacheKey = "achievement_progress:member:{$id}";
            
            $progress = Cache::remember($cacheKey, self::CACHE_TTL['achievement_progress'], function () use ($id) {
                $member = Member::find($id);
                $currentAchievements = MemberReadingAchievements::where('member_id', $id)
                    ->get()
                    ->groupBy('achievement_type');

                $progressData = [];
                
                foreach (self::ACHIEVEMENT_TYPES as $type => $typeInfo) {
                    $currentLevel = $currentAchievements->get($type)?->max('level') ?? 0;
                    $nextLevel = $currentLevel + 1;
                    
                    if ($nextLevel <= 5) {
                        $nextLevelInfo = $typeInfo['levels'][$nextLevel];
                        $currentProgress = $this->calculateProgress($id, $type, $nextLevelInfo['requirement']);
                        
                        $progressData[$type] = [
                            'achievement_type' => $type,
                            'name' => $typeInfo['name'],
                            'description' => $typeInfo['description'],
                            'icon' => $typeInfo['icon'],
                            'current_level' => $currentLevel,
                            'next_level' => $nextLevel,
                            'next_level_info' => $nextLevelInfo,
                            'current_progress' => $currentProgress,
                            'progress_percentage' => $this->calculateProgressPercentage($currentProgress, $nextLevelInfo['requirement']),
                            'is_max_level' => false,
                        ];
                    } else {
                        $progressData[$type] = [
                            'achievement_type' => $type,
                            'name' => $typeInfo['name'],
                            'description' => $typeInfo['description'],
                            'icon' => $typeInfo['icon'],
                            'current_level' => $currentLevel,
                            'next_level' => null,
                            'next_level_info' => null,
                            'current_progress' => null,
                            'progress_percentage' => 100,
                            'is_max_level' => true,
                        ];
                    }
                }
                
                return $progressData;
            });

            return $this->successResponse($progress, 'Achievement progress retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving achievement progress', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve achievement progress', 500);
        }
    }

    /**
     * Get available achievements
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableAchievements(Request $request): JsonResponse
    {
        try {
            // Get available achievements with caching
            $cacheKey = "available_achievements";
            
            $achievements = Cache::remember($cacheKey, self::CACHE_TTL['available_achievements'], function () {
                $availableAchievements = [];
                
                foreach (self::ACHIEVEMENT_TYPES as $type => $typeInfo) {
                    $availableAchievements[$type] = [
                        'type' => $type,
                        'name' => $typeInfo['name'],
                        'description' => $typeInfo['description'],
                        'icon' => $typeInfo['icon'],
                        'levels' => $typeInfo['levels'],
                        'max_level' => 5,
                        'total_points' => array_sum(array_column($typeInfo['levels'], 'points')),
                    ];
                }
                
                return $availableAchievements;
            });

            return $this->successResponse($achievements, 'Available achievements retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving available achievements', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve available achievements', 500);
        }
    }

    /**
     * Claim achievement reward
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function claimAchievementReward(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'achievement_id' => ['required', 'integer', 'exists:member_reading_achievements,id'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid achievement ID', 422, $validator->errors());
            }

            $achievementId = $request->get('achievement_id');
            $achievement = MemberReadingAchievements::find($achievementId);

            // Validate member access
            if (!$this->validateMemberAccess($achievement->member_id, $request)) {
                return $this->errorResponse('Achievement not found or access denied', 404);
            }

            // Check if already claimed
            if ($achievement->is_claimed) {
                return $this->errorResponse('Achievement reward already claimed', 400);
            }

            // Claim the reward
            DB::transaction(function () use ($achievement) {
                $achievement->update([
                    'is_claimed' => true,
                    'claimed_at' => now(),
                ]);

                // TODO: Add reward processing logic here
                // For example: add points to member account, unlock features, etc.
            });

            // Clear cache
            Cache::forget("achievements:member:{$achievement->member_id}");

            return $this->successResponse([
                'points_awarded' => $achievement->points_awarded,
                'claimed_at' => $achievement->claimed_at,
            ], 'Achievement reward claimed successfully');
            
        } catch (\Exception $e) {
            Log::error('Error claiming achievement reward', [
                'error' => $e->getMessage(),
                'achievement_id' => $request->get('achievement_id')
            ]);
            
            return $this->errorResponse('Failed to claim achievement reward', 500);
        }
    }

    /**
     * Get achievement leaderboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAchievementLeaderboard(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'year', 'all'])],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $period = $request->get('period', 'month');
            $limit = $request->get('limit', 50);

            // Get leaderboard with caching
            $cacheKey = "achievement_leaderboard:{$period}:{$limit}";
            
            $leaderboard = Cache::remember($cacheKey, self::CACHE_TTL['leaderboard'], function () use ($period, $limit) {
                $query = MemberReadingAchievements::select('member_id')
                    ->selectRaw('COUNT(*) as total_achievements')
                    ->selectRaw('SUM(points_awarded) as total_points')
                    ->selectRaw('MAX(level) as highest_level')
                    ->groupBy('member_id');

                if ($period !== 'all') {
                    $startDate = $this->getPeriodStartDate($period);
                    $query->where('achieved_at', '>=', $startDate);
                }

                return $query->orderBy('total_points', 'desc')
                    ->orderBy('total_achievements', 'desc')
                    ->limit($limit)
                    ->with('member:id,name,reading_level')
                    ->get()
                    ->map(function ($achievement, $index) {
                        return [
                            'rank' => $index + 1,
                            'member_id' => $achievement->member_id,
                            'member_name' => $achievement->member->name ?? 'Unknown',
                            'reading_level' => $achievement->member->reading_level ?? 'beginner',
                            'total_achievements' => $achievement->total_achievements,
                            'total_points' => $achievement->total_points,
                            'highest_level' => $achievement->highest_level,
                        ];
                    });
            });

            return $this->successResponse($leaderboard->toArray(), 'Achievement leaderboard retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving achievement leaderboard', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve achievement leaderboard', 500);
        }
    }

    /**
     * Get recent achievements
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecentAchievements(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'member_id' => ['sometimes', 'integer', 'exists:members,id'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $limit = $request->get('limit', 20);
            $memberId = $request->get('member_id');

            // Get recent achievements
            $query = MemberReadingAchievements::with('member:id,name,reading_level')
                ->orderBy('achieved_at', 'desc')
                ->limit($limit);

            if ($memberId) {
                // Validate member access if filtering by member
                if (!$this->validateMemberAccess($memberId, $request)) {
                    return $this->errorResponse('Member not found or access denied', 404);
                }
                $query->where('member_id', $memberId);
            }

            $achievements = $query->get()->map(function ($achievement) {
                $achievementInfo = $this->getAchievementInfo($achievement->achievement_type, $achievement->level);
                
                return [
                    'id' => $achievement->id,
                    'member_id' => $achievement->member_id,
                    'member_name' => $achievement->member->name ?? 'Unknown',
                    'achievement_type' => $achievement->achievement_type,
                    'level' => $achievement->level,
                    'points_awarded' => $achievement->points_awarded,
                    'achieved_at' => $achievement->achieved_at,
                    'achievement_info' => $achievementInfo,
                ];
            });

            return $this->successResponse($achievements->toArray(), 'Recent achievements retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving recent achievements', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve recent achievements', 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Validate member access
     */
    private function validateMemberAccess(int $memberId, Request $request): bool
    {
        $authenticatedMember = auth()->user();
        
        // Allow access if it's the same member or admin
        return $authenticatedMember->id === $memberId || $authenticatedMember->hasRole('admin');
    }

    /**
     * Get achievement information
     */
    private function getAchievementInfo(string $type, int $level): array
    {
        $typeInfo = self::ACHIEVEMENT_TYPES[$type] ?? [];
        $levelInfo = $typeInfo['levels'][$level] ?? [];
        
        return [
            'name' => $typeInfo['name'] ?? 'Unknown Achievement',
            'description' => $typeInfo['description'] ?? '',
            'icon' => $typeInfo['icon'] ?? 'trophy',
            'level_title' => $levelInfo['title'] ?? "Level {$level}",
            'level_requirement' => $levelInfo['requirement'] ?? 0,
            'level_points' => $levelInfo['points'] ?? 0,
        ];
    }

    /**
     * Calculate progress for achievement
     */
    private function calculateProgress(int $memberId, string $type, $requirement): int
    {
        return match ($type) {
            'daily_reader' => MemberReadingStatistics::getCurrentStreak($memberId),
            'word_master' => MemberReadingStatistics::getTotalWordsRead($memberId),
            'speed_reader' => MemberReadingStatistics::getAverageReadingSpeed($memberId),
            'streak_keeper' => MemberReadingStatistics::getLongestStreak($memberId),
            'level_climber' => $this->getReadingLevelProgress($memberId, $requirement),
            'category_explorer' => $this->getCategoriesRead($memberId),
            'engagement_star' => $this->getEngagementActions($memberId),
            'completion_champion' => MemberReadingStatistics::getTotalStoriesCompleted($memberId),
            'early_bird' => $this->getMorningReadingSessions($memberId),
            'night_owl' => $this->getEveningReadingSessions($memberId),
            default => 0,
        };
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgressPercentage(int $current, $requirement): int
    {
        if (is_string($requirement)) {
            // For level-based requirements
            return $this->calculateLevelProgress($current, $requirement);
        }
        
        if ($requirement <= 0) {
            return 0;
        }
        
        return min(100, round(($current / $requirement) * 100));
    }

    /**
     * Calculate level progress
     */
    private function calculateLevelProgress(int $memberId, string $targetLevel): int
    {
        $member = Member::find($memberId);
        $currentLevel = $member->reading_level ?? 'beginner';
        
        $levels = ['beginner', 'elementary', 'intermediate', 'advanced', 'expert', 'master'];
        $currentIndex = array_search($currentLevel, $levels);
        $targetIndex = array_search($targetLevel, $levels);
        
        return $currentIndex >= $targetIndex ? 100 : 0;
    }

    /**
     * Get reading level progress
     */
    private function getReadingLevelProgress(int $memberId, string $requirement): int
    {
        $member = Member::find($memberId);
        $currentLevel = $member->reading_level ?? 'beginner';
        
        $levels = ['beginner', 'elementary', 'intermediate', 'advanced', 'expert', 'master'];
        $currentIndex = array_search($currentLevel, $levels);
        $requirementIndex = array_search($requirement, $levels);
        
        return $currentIndex >= $requirementIndex ? 1 : 0;
    }

    /**
     * Get categories read by member
     */
    private function getCategoriesRead(int $memberId): int
    {
        return DB::table('member_reading_history')
            ->join('stories', 'member_reading_history.story_id', '=', 'stories.id')
            ->where('member_reading_history.member_id', $memberId)
            ->distinct('stories.category_id')
            ->count('stories.category_id');
    }

    /**
     * Get engagement actions count
     */
    private function getEngagementActions(int $memberId): int
    {
        return DB::table('member_story_interactions')
            ->where('member_id', $memberId)
            ->count();
    }

    /**
     * Get morning reading sessions count
     */
    private function getMorningReadingSessions(int $memberId): int
    {
        return DB::table('member_reading_history')
            ->where('member_id', $memberId)
            ->whereRaw('HOUR(last_read_at) BETWEEN 6 AND 11')
            ->count();
    }

    /**
     * Get evening reading sessions count
     */
    private function getEveningReadingSessions(int $memberId): int
    {
        return DB::table('member_reading_history')
            ->where('member_id', $memberId)
            ->whereRaw('HOUR(last_read_at) BETWEEN 18 AND 23')
            ->count();
    }

    /**
     * Get period start date
     */
    private function getPeriodStartDate(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    /**
     * Success response helper
     */
    private function successResponse(array $data, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID', uniqid()),
        ], 200);
    }

    /**
     * Error response helper
     */
    private function errorResponse(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode($code),
                'message' => $message,
                'details' => $errors,
            ],
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID', uniqid()),
        ], $code);
    }

    /**
     * Get error code string
     */
    private function getErrorCode(int $httpCode): string
    {
        return match ($httpCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'INTERNAL_SERVER_ERROR',
            default => 'UNKNOWN_ERROR',
        };
    }
}