<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Member, MemberReadingStatistics, MemberReadingAchievements};
use App\Http\Resources\LeaderboardResource;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Validator, Log, DB};
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * Leaderboard API Controller
 * 
 * Provides leaderboard functionality for the Flutter mobile application,
 * including various ranking systems, filters, and social features.
 * 
 * Leaderboard Types:
 * - words_read: Total words read ranking
 * - stories_completed: Stories completion ranking
 * - reading_streak: Current reading streak ranking
 * - achievements: Achievement points ranking
 * - consistency: Reading consistency ranking
 * - speed: Reading speed ranking
 * - level: Reading level progression ranking
 * 
 * Endpoints:
 * - GET /api/v1/leaderboard/words - Words read leaderboard
 * - GET /api/v1/leaderboard/stories - Stories completed leaderboard
 * - GET /api/v1/leaderboard/streaks - Reading streaks leaderboard
 * - GET /api/v1/leaderboard/achievements - Achievement points leaderboard
 * - GET /api/v1/leaderboard/member/{id}/rank - Get member's rank
 * - GET /api/v1/leaderboard/global - Global leaderboard overview
 * - GET /api/v1/leaderboard/friends/{id} - Friends leaderboard
 * 
 * Features:
 * - Period-based rankings (daily, weekly, monthly, yearly, all-time)
 * - Reading level filtering
 * - Pagination support
 * - Social features (friends comparison)
 * - Member rank tracking
 * - Performance analytics
 * 
 * @package App\Http\Controllers\Api\V1
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class LeaderboardController extends Controller
{
    /**
     * Cache TTL configuration
     */
    private const CACHE_TTL = [
        'leaderboard' => 1800,        // 30 minutes
        'member_rank' => 900,         // 15 minutes
        'global_overview' => 3600,    // 1 hour
        'friends_leaderboard' => 600, // 10 minutes
    ];

    /**
     * Leaderboard configuration
     */
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;
    private const VALID_PERIODS = ['day', 'week', 'month', 'quarter', 'year', 'all'];
    private const VALID_LEVELS = ['beginner', 'elementary', 'intermediate', 'advanced', 'expert', 'master'];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:120,1'); // Higher limit for leaderboards
        $this->middleware('verified.device');
    }

    /**
     * Get words read leaderboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getWordsLeaderboard(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(self::VALID_PERIODS)],
                'reading_level' => ['sometimes', 'string', Rule::in(self::VALID_LEVELS)],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
                'offset' => ['sometimes', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $period = $request->get('period', 'month');
            $readingLevel = $request->get('reading_level');
            $limit = $request->get('limit', self::DEFAULT_LIMIT);
            $offset = $request->get('offset', 0);

            // Get words leaderboard with caching
            $cacheKey = "leaderboard:words:{$period}:{$readingLevel}:{$limit}:{$offset}";
            
            $leaderboard = Cache::remember($cacheKey, self::CACHE_TTL['leaderboard'], function () use ($period, $readingLevel, $limit, $offset) {
                $query = MemberReadingStatistics::select('member_id')
                    ->selectRaw('SUM(words_read) as total_words')
                    ->selectRaw('COUNT(DISTINCT date) as reading_days')
                    ->selectRaw('ROUND(AVG(words_read), 0) as avg_daily_words')
                    ->selectRaw('MAX(reading_streak) as max_streak')
                    ->groupBy('member_id');

                // Apply period filter
                if ($period !== 'all') {
                    $startDate = $this->getPeriodStartDate($period);
                    $query->where('date', '>=', $startDate);
                }

                // Apply reading level filter
                if ($readingLevel) {
                    $query->whereHas('member', function ($q) use ($readingLevel) {
                        $q->where('reading_level', $readingLevel);
                    });
                }

                return $query->orderBy('total_words', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->with(['member:id,name,reading_level,created_at'])
                    ->get()
                    ->map(function ($stat, $index) use ($offset) {
                        return [
                            'rank' => $offset + $index + 1,
                            'member_id' => $stat->member_id,
                            'member_name' => $stat->member->name ?? 'Unknown',
                            'reading_level' => $stat->member->reading_level ?? 'beginner',
                            'member_since' => $stat->member->created_at?->format('Y-m-d'),
                            'total_words' => (int) $stat->total_words,
                            'reading_days' => (int) $stat->reading_days,
                            'avg_daily_words' => (int) $stat->avg_daily_words,
                            'max_streak' => (int) $stat->max_streak,
                            'score' => (int) $stat->total_words, // Primary ranking score
                        ];
                    });
            });

            return $this->successResponse([
                'leaderboard' => $leaderboard,
                'metadata' => [
                    'type' => 'words_read',
                    'period' => $period,
                    'reading_level' => $readingLevel,
                    'limit' => $limit,
                    'offset' => $offset,
                    'total_entries' => $leaderboard->count(),
                ],
            ], 'Words leaderboard retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving words leaderboard', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to retrieve words leaderboard', 500);
        }
    }

    /**
     * Get stories completed leaderboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStoriesLeaderboard(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(self::VALID_PERIODS)],
                'reading_level' => ['sometimes', 'string', Rule::in(self::VALID_LEVELS)],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
                'offset' => ['sometimes', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $period = $request->get('period', 'month');
            $readingLevel = $request->get('reading_level');
            $limit = $request->get('limit', self::DEFAULT_LIMIT);
            $offset = $request->get('offset', 0);

            // Get stories leaderboard with caching
            $cacheKey = "leaderboard:stories:{$period}:{$readingLevel}:{$limit}:{$offset}";
            
            $leaderboard = Cache::remember($cacheKey, self::CACHE_TTL['leaderboard'], function () use ($period, $readingLevel, $limit, $offset) {
                $query = MemberReadingStatistics::select('member_id')
                    ->selectRaw('SUM(stories_completed) as total_stories')
                    ->selectRaw('SUM(words_read) as total_words')
                    ->selectRaw('COUNT(DISTINCT date) as reading_days')
                    ->selectRaw('ROUND(AVG(stories_completed), 1) as avg_daily_stories')
                    ->groupBy('member_id');

                // Apply period filter
                if ($period !== 'all') {
                    $startDate = $this->getPeriodStartDate($period);
                    $query->where('date', '>=', $startDate);
                }

                // Apply reading level filter
                if ($readingLevel) {
                    $query->whereHas('member', function ($q) use ($readingLevel) {
                        $q->where('reading_level', $readingLevel);
                    });
                }

                return $query->orderBy('total_stories', 'desc')
                    ->orderBy('total_words', 'desc') // Secondary sort
                    ->offset($offset)
                    ->limit($limit)
                    ->with(['member:id,name,reading_level,created_at'])
                    ->get()
                    ->map(function ($stat, $index) use ($offset) {
                        return [
                            'rank' => $offset + $index + 1,
                            'member_id' => $stat->member_id,
                            'member_name' => $stat->member->name ?? 'Unknown',
                            'reading_level' => $stat->member->reading_level ?? 'beginner',
                            'member_since' => $stat->member->created_at?->format('Y-m-d'),
                            'total_stories' => (int) $stat->total_stories,
                            'total_words' => (int) $stat->total_words,
                            'reading_days' => (int) $stat->reading_days,
                            'avg_daily_stories' => (float) $stat->avg_daily_stories,
                            'score' => (int) $stat->total_stories, // Primary ranking score
                        ];
                    });
            });

            return $this->successResponse([
                'leaderboard' => $leaderboard,
                'metadata' => [
                    'type' => 'stories_completed',
                    'period' => $period,
                    'reading_level' => $readingLevel,
                    'limit' => $limit,
                    'offset' => $offset,
                    'total_entries' => $leaderboard->count(),
                ],
            ], 'Stories leaderboard retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving stories leaderboard', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to retrieve stories leaderboard', 500);
        }
    }

    /**
     * Get reading streaks leaderboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStreaksLeaderboard(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'type' => ['sometimes', 'string', Rule::in(['current', 'longest'])],
                'reading_level' => ['sometimes', 'string', Rule::in(self::VALID_LEVELS)],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
                'offset' => ['sometimes', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $type = $request->get('type', 'current');
            $readingLevel = $request->get('reading_level');
            $limit = $request->get('limit', self::DEFAULT_LIMIT);
            $offset = $request->get('offset', 0);

            // Get streaks leaderboard with caching
            $cacheKey = "leaderboard:streaks:{$type}:{$readingLevel}:{$limit}:{$offset}";
            
            $leaderboard = Cache::remember($cacheKey, self::CACHE_TTL['leaderboard'], function () use ($type, $readingLevel, $limit, $offset) {
                $streakColumn = $type === 'current' ? 'reading_streak' : 'longest_streak';
                
                $query = MemberReadingStatistics::select('member_id')
                    ->selectRaw("MAX({$streakColumn}) as max_streak")
                    ->selectRaw('SUM(words_read) as total_words')
                    ->selectRaw('SUM(stories_completed) as total_stories')
                    ->selectRaw('COUNT(DISTINCT date) as reading_days')
                    ->groupBy('member_id');

                // Apply reading level filter
                if ($readingLevel) {
                    $query->whereHas('member', function ($q) use ($readingLevel) {
                        $q->where('reading_level', $readingLevel);
                    });
                }

                return $query->orderBy('max_streak', 'desc')
                    ->orderBy('total_words', 'desc') // Secondary sort
                    ->offset($offset)
                    ->limit($limit)
                    ->with(['member:id,name,reading_level,created_at'])
                    ->get()
                    ->map(function ($stat, $index) use ($offset, $type) {
                        return [
                            'rank' => $offset + $index + 1,
                            'member_id' => $stat->member_id,
                            'member_name' => $stat->member->name ?? 'Unknown',
                            'reading_level' => $stat->member->reading_level ?? 'beginner',
                            'member_since' => $stat->member->created_at?->format('Y-m-d'),
                            'streak' => (int) $stat->max_streak,
                            'total_words' => (int) $stat->total_words,
                            'total_stories' => (int) $stat->total_stories,
                            'reading_days' => (int) $stat->reading_days,
                            'score' => (int) $stat->max_streak, // Primary ranking score
                            'streak_type' => $type,
                        ];
                    });
            });

            return $this->successResponse([
                'leaderboard' => $leaderboard,
                'metadata' => [
                    'type' => 'reading_streaks',
                    'streak_type' => $type,
                    'reading_level' => $readingLevel,
                    'limit' => $limit,
                    'offset' => $offset,
                    'total_entries' => $leaderboard->count(),
                ],
            ], 'Streaks leaderboard retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving streaks leaderboard', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to retrieve streaks leaderboard', 500);
        }
    }

    /**
     * Get achievements leaderboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAchievementsLeaderboard(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(self::VALID_PERIODS)],
                'reading_level' => ['sometimes', 'string', Rule::in(self::VALID_LEVELS)],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
                'offset' => ['sometimes', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $period = $request->get('period', 'month');
            $readingLevel = $request->get('reading_level');
            $limit = $request->get('limit', self::DEFAULT_LIMIT);
            $offset = $request->get('offset', 0);

            // Get achievements leaderboard with caching
            $cacheKey = "leaderboard:achievements:{$period}:{$readingLevel}:{$limit}:{$offset}";
            
            $leaderboard = Cache::remember($cacheKey, self::CACHE_TTL['leaderboard'], function () use ($period, $readingLevel, $limit, $offset) {
                $query = MemberReadingAchievements::select('member_id')
                    ->selectRaw('COUNT(*) as total_achievements')
                    ->selectRaw('SUM(points_awarded) as total_points')
                    ->selectRaw('MAX(level) as highest_level')
                    ->selectRaw('COUNT(DISTINCT achievement_type) as unique_types')
                    ->groupBy('member_id');

                // Apply period filter
                if ($period !== 'all') {
                    $startDate = $this->getPeriodStartDate($period);
                    $query->where('achieved_at', '>=', $startDate);
                }

                // Apply reading level filter
                if ($readingLevel) {
                    $query->whereHas('member', function ($q) use ($readingLevel) {
                        $q->where('reading_level', $readingLevel);
                    });
                }

                return $query->orderBy('total_points', 'desc')
                    ->orderBy('total_achievements', 'desc') // Secondary sort
                    ->offset($offset)
                    ->limit($limit)
                    ->with(['member:id,name,reading_level,created_at'])
                    ->get()
                    ->map(function ($stat, $index) use ($offset) {
                        return [
                            'rank' => $offset + $index + 1,
                            'member_id' => $stat->member_id,
                            'member_name' => $stat->member->name ?? 'Unknown',
                            'reading_level' => $stat->member->reading_level ?? 'beginner',
                            'member_since' => $stat->member->created_at?->format('Y-m-d'),
                            'total_achievements' => (int) $stat->total_achievements,
                            'total_points' => (int) $stat->total_points,
                            'highest_level' => (int) $stat->highest_level,
                            'unique_types' => (int) $stat->unique_types,
                            'score' => (int) $stat->total_points, // Primary ranking score
                        ];
                    });
            });

            return $this->successResponse([
                'leaderboard' => $leaderboard,
                'metadata' => [
                    'type' => 'achievements',
                    'period' => $period,
                    'reading_level' => $readingLevel,
                    'limit' => $limit,
                    'offset' => $offset,
                    'total_entries' => $leaderboard->count(),
                ],
            ], 'Achievements leaderboard retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving achievements leaderboard', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to retrieve achievements leaderboard', 500);
        }
    }

    /**
     * Get member's rank in various leaderboards
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberRank(int $id, Request $request): JsonResponse
    {
        try {
            // Validate member access
            if (!$this->validateMemberAccess($id, $request)) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(self::VALID_PERIODS)],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $period = $request->get('period', 'month');

            // Get member rank with caching
            $cacheKey = "member_rank:{$id}:{$period}";
            
            $rankings = Cache::remember($cacheKey, self::CACHE_TTL['member_rank'], function () use ($id, $period) {
                return [
                    'words_read' => $this->getMemberWordsRank($id, $period),
                    'stories_completed' => $this->getMemberStoriesRank($id, $period),
                    'current_streak' => $this->getMemberStreakRank($id, 'current'),
                    'longest_streak' => $this->getMemberStreakRank($id, 'longest'),
                    'achievements' => $this->getMemberAchievementsRank($id, $period),
                ];
            });

            return $this->successResponse([
                'member_id' => $id,
                'period' => $period,
                'rankings' => $rankings,
            ], 'Member rank retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving member rank', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve member rank', 500);
        }
    }

    /**
     * Get global leaderboard overview
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getGlobalOverview(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(self::VALID_PERIODS)],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $period = $request->get('period', 'month');

            // Get global overview with caching
            $cacheKey = "global_overview:{$period}";
            
            $overview = Cache::remember($cacheKey, self::CACHE_TTL['global_overview'], function () use ($period) {
                $startDate = $period !== 'all' ? $this->getPeriodStartDate($period) : null;
                
                return [
                    'total_participants' => $this->getTotalParticipants($startDate),
                    'top_performers' => [
                        'words_leader' => $this->getTopPerformer('words', $startDate),
                        'stories_leader' => $this->getTopPerformer('stories', $startDate),
                        'streak_leader' => $this->getTopPerformer('streak', null), // Current streak
                        'achievements_leader' => $this->getTopPerformer('achievements', $startDate),
                    ],
                    'platform_stats' => [
                        'total_words_read' => $this->getTotalWordsRead($startDate),
                        'total_stories_completed' => $this->getTotalStoriesCompleted($startDate),
                        'total_achievements_earned' => $this->getTotalAchievements($startDate),
                        'average_reading_streak' => $this->getAverageReadingStreak($startDate),
                    ],
                    'level_distribution' => $this->getLevelDistribution($startDate),
                    'recent_milestones' => $this->getRecentMilestones(),
                ];
            });

            return $this->successResponse([
                'period' => $period,
                'overview' => $overview,
            ], 'Global overview retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving global overview', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve global overview', 500);
        }
    }

    /**
     * Get friends leaderboard
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getFriendsLeaderboard(int $id, Request $request): JsonResponse
    {
        try {
            // Validate member access
            if (!$this->validateMemberAccess($id, $request)) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(self::VALID_PERIODS)],
                'type' => ['sometimes', 'string', Rule::in(['words', 'stories', 'achievements'])],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            $period = $request->get('period', 'month');
            $type = $request->get('type', 'words');

            // Get friends leaderboard with caching
            $cacheKey = "friends_leaderboard:{$id}:{$period}:{$type}";
            
            $leaderboard = Cache::remember($cacheKey, self::CACHE_TTL['friends_leaderboard'], function () use ($id, $period, $type) {
                // TODO: Implement friends system
                // For now, return empty array as friends feature is not implemented
                return [];
                
                // Future implementation:
                // $friendIds = $this->getFriendIds($id);
                // return $this->getLeaderboardForMembers($friendIds, $period, $type);
            });

            return $this->successResponse([
                'member_id' => $id,
                'period' => $period,
                'type' => $type,
                'leaderboard' => $leaderboard,
                'message' => 'Friends system not implemented yet',
            ], 'Friends leaderboard retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving friends leaderboard', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve friends leaderboard', 500);
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
     * Get period start date
     */
    private function getPeriodStartDate(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    /**
     * Get member words rank
     */
    private function getMemberWordsRank(int $memberId, string $period): array
    {
        $query = MemberReadingStatistics::selectRaw('SUM(words_read) as total_words')
            ->groupBy('member_id');

        if ($period !== 'all') {
            $startDate = $this->getPeriodStartDate($period);
            $query->where('date', '>=', $startDate);
        }

        $memberWords = $query->where('member_id', $memberId)->first()?->total_words ?? 0;
        
        $rank = $query->havingRaw('SUM(words_read) > ?', [$memberWords])->count() + 1;
        $totalParticipants = $query->count();

        return [
            'rank' => $rank,
            'total_participants' => $totalParticipants,
            'score' => (int) $memberWords,
            'percentile' => $totalParticipants > 0 ? round((($totalParticipants - $rank + 1) / $totalParticipants) * 100, 1) : 0,
        ];
    }

    /**
     * Get member stories rank
     */
    private function getMemberStoriesRank(int $memberId, string $period): array
    {
        $query = MemberReadingStatistics::selectRaw('SUM(stories_completed) as total_stories')
            ->groupBy('member_id');

        if ($period !== 'all') {
            $startDate = $this->getPeriodStartDate($period);
            $query->where('date', '>=', $startDate);
        }

        $memberStories = $query->where('member_id', $memberId)->first()?->total_stories ?? 0;
        
        $rank = $query->havingRaw('SUM(stories_completed) > ?', [$memberStories])->count() + 1;
        $totalParticipants = $query->count();

        return [
            'rank' => $rank,
            'total_participants' => $totalParticipants,
            'score' => (int) $memberStories,
            'percentile' => $totalParticipants > 0 ? round((($totalParticipants - $rank + 1) / $totalParticipants) * 100, 1) : 0,
        ];
    }

    /**
     * Get member streak rank
     */
    private function getMemberStreakRank(int $memberId, string $type): array
    {
        $streakColumn = $type === 'current' ? 'reading_streak' : 'longest_streak';
        
        $query = MemberReadingStatistics::selectRaw("MAX({$streakColumn}) as max_streak")
            ->groupBy('member_id');

        $memberStreak = $query->where('member_id', $memberId)->first()?->max_streak ?? 0;
        
        $rank = $query->havingRaw("MAX({$streakColumn}) > ?", [$memberStreak])->count() + 1;
        $totalParticipants = $query->count();

        return [
            'rank' => $rank,
            'total_participants' => $totalParticipants,
            'score' => (int) $memberStreak,
            'percentile' => $totalParticipants > 0 ? round((($totalParticipants - $rank + 1) / $totalParticipants) * 100, 1) : 0,
            'streak_type' => $type,
        ];
    }

    /**
     * Get member achievements rank
     */
    private function getMemberAchievementsRank(int $memberId, string $period): array
    {
        $query = MemberReadingAchievements::selectRaw('SUM(points_awarded) as total_points')
            ->groupBy('member_id');

        if ($period !== 'all') {
            $startDate = $this->getPeriodStartDate($period);
            $query->where('achieved_at', '>=', $startDate);
        }

        $memberPoints = $query->where('member_id', $memberId)->first()?->total_points ?? 0;
        
        $rank = $query->havingRaw('SUM(points_awarded) > ?', [$memberPoints])->count() + 1;
        $totalParticipants = $query->count();

        return [
            'rank' => $rank,
            'total_participants' => $totalParticipants,
            'score' => (int) $memberPoints,
            'percentile' => $totalParticipants > 0 ? round((($totalParticipants - $rank + 1) / $totalParticipants) * 100, 1) : 0,
        ];
    }

    /**
     * Get total participants
     */
    private function getTotalParticipants(?Carbon $startDate): int
    {
        $query = MemberReadingStatistics::distinct('member_id');
        
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        return $query->count('member_id');
    }

    /**
     * Get top performer
     */
    private function getTopPerformer(string $type, ?Carbon $startDate): ?array
    {
        switch ($type) {
            case 'words':
                $query = MemberReadingStatistics::selectRaw('member_id, SUM(words_read) as score')
                    ->groupBy('member_id')
                    ->orderBy('score', 'desc');
                break;
                
            case 'stories':
                $query = MemberReadingStatistics::selectRaw('member_id, SUM(stories_completed) as score')
                    ->groupBy('member_id')
                    ->orderBy('score', 'desc');
                break;
                
            case 'streak':
                $query = MemberReadingStatistics::selectRaw('member_id, MAX(reading_streak) as score')
                    ->groupBy('member_id')
                    ->orderBy('score', 'desc');
                break;
                
            case 'achievements':
                $query = MemberReadingAchievements::selectRaw('member_id, SUM(points_awarded) as score')
                    ->groupBy('member_id')
                    ->orderBy('score', 'desc');
                break;
                
            default:
                return null;
        }

        if ($startDate && $type !== 'streak') {
            $query->where($type === 'achievements' ? 'achieved_at' : 'date', '>=', $startDate);
        }

        $topPerformer = $query->with('member:id,name,reading_level')->first();

        return $topPerformer ? [
            'member_id' => $topPerformer->member_id,
            'member_name' => $topPerformer->member->name ?? 'Unknown',
            'reading_level' => $topPerformer->member->reading_level ?? 'beginner',
            'score' => (int) $topPerformer->score,
        ] : null;
    }

    /**
     * Get total words read
     */
    private function getTotalWordsRead(?Carbon $startDate): int
    {
        $query = MemberReadingStatistics::query();
        
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        return $query->sum('words_read');
    }

    /**
     * Get total stories completed
     */
    private function getTotalStoriesCompleted(?Carbon $startDate): int
    {
        $query = MemberReadingStatistics::query();
        
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        return $query->sum('stories_completed');
    }

    /**
     * Get total achievements
     */
    private function getTotalAchievements(?Carbon $startDate): int
    {
        $query = MemberReadingAchievements::query();
        
        if ($startDate) {
            $query->where('achieved_at', '>=', $startDate);
        }

        return $query->count();
    }

    /**
     * Get average reading streak
     */
    private function getAverageReadingStreak(?Carbon $startDate): float
    {
        $query = MemberReadingStatistics::query();
        
        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        return round($query->avg('reading_streak') ?? 0, 1);
    }

    /**
     * Get level distribution
     */
    private function getLevelDistribution(?Carbon $startDate): array
    {
        $query = Member::selectRaw('reading_level, COUNT(*) as count')
            ->groupBy('reading_level');

        if ($startDate) {
            $query->whereHas('readingStatistics', function ($q) use ($startDate) {
                $q->where('date', '>=', $startDate);
            });
        }

        return $query->get()->mapWithKeys(function ($item) {
            return [$item->reading_level => $item->count];
        })->toArray();
    }

    /**
     * Get recent milestones
     */
    private function getRecentMilestones(): array
    {
        // Get recent significant achievements
        $recentAchievements = MemberReadingAchievements::with('member:id,name')
            ->where('achieved_at', '>=', now()->subDays(7))
            ->where('level', '>=', 3) // Only significant achievements
            ->orderBy('achieved_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($achievement) {
                return [
                    'type' => 'achievement',
                    'member_name' => $achievement->member->name ?? 'Unknown',
                    'description' => "Achieved {$achievement->achievement_type} Level {$achievement->level}",
                    'timestamp' => $achievement->achieved_at,
                ];
            });

        // Get recent reading milestones
        $recentMilestones = MemberReadingStatistics::with('member:id,name')
            ->where('date', '>=', now()->subDays(7))
            ->where(function ($query) {
                $query->where('words_read', '>=', 1000)
                    ->orWhere('reading_streak', '>=', 30);
            })
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($stat) {
                $descriptions = [];
                if ($stat->words_read >= 1000) {
                    $descriptions[] = "Read {$stat->words_read} words";
                }
                if ($stat->reading_streak >= 30) {
                    $descriptions[] = "Reached {$stat->reading_streak} day streak";
                }
                
                return [
                    'type' => 'milestone',
                    'member_name' => $stat->member->name ?? 'Unknown',
                    'description' => implode(' and ', $descriptions),
                    'timestamp' => $stat->date,
                ];
            });

        return $recentAchievements->merge($recentMilestones)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
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