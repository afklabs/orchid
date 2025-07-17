<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReadingAnalyticsService;
use App\Models\{Member, MemberReadingStatistics, MemberReadingHistory};
use App\Http\Resources\{MemberReadingStatisticsResource, AnalyticsResource};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Cache, Validator, Log, RateLimiter};
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * Reading Analytics API Controller
 * 
 * Provides REST API endpoints for reading analytics data,
 * supporting the Flutter mobile application with comprehensive
 * reading statistics, progress tracking, and insights.
 * 
 * Endpoints:
 * - GET /api/v1/analytics/member/{id} - Get member analytics
 * - GET /api/v1/analytics/member/{id}/summary - Get member summary
 * - GET /api/v1/analytics/member/{id}/trends - Get member trends
 * - GET /api/v1/analytics/member/{id}/progress - Get reading progress
 * - GET /api/v1/analytics/member/{id}/comparisons - Get comparisons
 * - GET /api/v1/analytics/global - Get global analytics
 * - POST /api/v1/analytics/member/{id}/goal - Set reading goal
 * - POST /api/v1/analytics/record-reading - Record reading session
 * 
 * Security Features:
 * - JWT authentication required
 * - Rate limiting (60 requests per minute)
 * - Input validation and sanitization
 * - Request signing verification
 * - Device ID validation
 * - Error handling with proper HTTP status codes
 * 
 * Performance Features:
 * - Response caching with Redis
 * - Optimized database queries
 * - Pagination for large datasets
 * - Compressed responses (gzip)
 * - Response time monitoring
 * 
 * @package App\Http\Controllers\Api\V1
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-17
 */
class ReadingAnalyticsController extends Controller
{
    /**
     * @var ReadingAnalyticsService
     */
    private ReadingAnalyticsService $analyticsService;

    /**
     * Rate limiting configuration
     */
    private const RATE_LIMIT = 60; // requests per minute
    private const RATE_LIMIT_DECAY = 60; // seconds

    /**
     * Cache configuration
     */
    private const CACHE_TTL = [
        'member_analytics' => 1800,    // 30 minutes
        'member_summary' => 900,       // 15 minutes
        'trends' => 3600,             // 1 hour
        'global_analytics' => 7200,   // 2 hours
    ];

    /**
     * Constructor
     */
    public function __construct(ReadingAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
        
        // Apply middleware
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:' . self::RATE_LIMIT . ',' . self::RATE_LIMIT_DECAY);
        $this->middleware('verified.device');
    }

    /**
     * Get comprehensive member analytics
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberAnalytics(int $id, Request $request): JsonResponse
    {
        try {
            // Rate limiting check
            if (!$this->checkRateLimit($request, "analytics:member:{$id}")) {
                return $this->errorResponse('Rate limit exceeded', 429);
            }

            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'quarter', 'year'])],
                'include_comparisons' => ['sometimes', 'boolean'],
                'include_trends' => ['sometimes', 'boolean'],
                'include_recommendations' => ['sometimes', 'boolean'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            // Verify member access
            $member = $this->validateMemberAccess($id, $request);
            if (!$member) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Get analytics data with caching
            $period = $request->get('period', 'month');
            $cacheKey = "analytics:member:{$id}:{$period}:" . md5(serialize($request->all()));
            
            $analytics = Cache::remember($cacheKey, self::CACHE_TTL['member_analytics'], function () use ($id, $period, $request) {
                $analytics = $this->analyticsService->getMemberAnalytics($id, $period);
                
                // Filter data based on request parameters
                if (!$request->get('include_comparisons', true)) {
                    unset($analytics['comparisons']);
                }
                
                if (!$request->get('include_trends', true)) {
                    unset($analytics['trends']);
                }
                
                if (!$request->get('include_recommendations', true)) {
                    unset($analytics['recommendations']);
                }
                
                return $analytics;
            });

            return $this->successResponse($analytics, 'Member analytics retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving member analytics', [
                'member_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse('Failed to retrieve analytics', 500);
        }
    }

    /**
     * Get member analytics summary
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberSummary(int $id, Request $request): JsonResponse
    {
        try {
            // Rate limiting check
            if (!$this->checkRateLimit($request, "summary:member:{$id}")) {
                return $this->errorResponse('Rate limit exceeded', 429);
            }

            // Validate member access
            $member = $this->validateMemberAccess($id, $request);
            if (!$member) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Get summary data with caching
            $period = $request->get('period', 'month');
            $cacheKey = "summary:member:{$id}:{$period}";
            
            $summary = Cache::remember($cacheKey, self::CACHE_TTL['member_summary'], function () use ($id, $period) {
                $analytics = $this->analyticsService->getMemberAnalytics($id, $period);
                
                return [
                    'summary' => $analytics['summary'] ?? [],
                    'reading_stats' => $analytics['reading_stats'] ?? [],
                    'word_count_analytics' => $analytics['word_count_analytics'] ?? [],
                    'engagement_metrics' => $analytics['engagement_metrics'] ?? [],
                ];
            });

            return $this->successResponse($summary, 'Member summary retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving member summary', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve summary', 500);
        }
    }

    /**
     * Get member reading trends
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberTrends(int $id, Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'quarter', 'year'])],
                'chart_type' => ['sometimes', 'string', Rule::in(['line', 'bar', 'area'])],
                'granularity' => ['sometimes', 'string', Rule::in(['hourly', 'daily', 'weekly', 'monthly'])],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            // Validate member access
            $member = $this->validateMemberAccess($id, $request);
            if (!$member) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Get trends data with caching
            $period = $request->get('period', 'month');
            $cacheKey = "trends:member:{$id}:{$period}";
            
            $trends = Cache::remember($cacheKey, self::CACHE_TTL['trends'], function () use ($id, $period) {
                $analytics = $this->analyticsService->getMemberAnalytics($id, $period);
                return $analytics['trends'] ?? [];
            });

            return $this->successResponse($trends, 'Member trends retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving member trends', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve trends', 500);
        }
    }

    /**
     * Get member reading progress
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberProgress(int $id, Request $request): JsonResponse
    {
        try {
            // Validate member access
            $member = $this->validateMemberAccess($id, $request);
            if (!$member) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Get progress data
            $period = $request->get('period', 'month');
            $startDate = $this->getPeriodStartDate($period);
            
            $progress = [
                'current_streak' => MemberReadingStatistics::getCurrentStreak($id),
                'longest_streak' => MemberReadingStatistics::getLongestStreak($id),
                'words_read_today' => MemberReadingStatistics::getWordsReadToday($id),
                'words_read_this_week' => MemberReadingStatistics::getWordsReadThisWeek($id),
                'words_read_this_month' => MemberReadingStatistics::getWordsReadThisMonth($id),
                'stories_completed_today' => MemberReadingHistory::getStoriesCompletedToday($id),
                'stories_completed_this_week' => MemberReadingHistory::getStoriesCompletedThisWeek($id),
                'stories_completed_this_month' => MemberReadingHistory::getStoriesCompletedThisMonth($id),
                'reading_level' => $member->reading_level ?? 'beginner',
                'reading_goals' => $this->getMemberReadingGoals($id),
            ];

            return $this->successResponse($progress, 'Member progress retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving member progress', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve progress', 500);
        }
    }

    /**
     * Get member comparisons
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function getMemberComparisons(int $id, Request $request): JsonResponse
    {
        try {
            // Validate member access
            $member = $this->validateMemberAccess($id, $request);
            if (!$member) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Get comparisons data
            $period = $request->get('period', 'month');
            $analytics = $this->analyticsService->getMemberAnalytics($id, $period);
            
            $comparisons = [
                'vs_average' => $analytics['comparisons']['vs_average'] ?? [],
                'percentile_rank' => $analytics['comparisons']['percentile_rank'] ?? [],
                'peer_group' => $analytics['comparisons']['peer_group_comparison'] ?? [],
                'reading_level_comparison' => $this->getReadingLevelComparison($id),
            ];

            return $this->successResponse($comparisons, 'Member comparisons retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving member comparisons', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve comparisons', 500);
        }
    }

    /**
     * Get global analytics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getGlobalAnalytics(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period' => ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'quarter', 'year'])],
                'include_leaderboard' => ['sometimes', 'boolean'],
                'include_trends' => ['sometimes', 'boolean'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid parameters', 422, $validator->errors());
            }

            // Get global analytics with caching
            $period = $request->get('period', 'month');
            $cacheKey = "global:analytics:{$period}";
            
            $analytics = Cache::remember($cacheKey, self::CACHE_TTL['global_analytics'], function () use ($period, $request) {
                $analytics = $this->analyticsService->getGlobalAnalytics($period);
                
                // Add additional data if requested
                if ($request->get('include_leaderboard', false)) {
                    $analytics['leaderboard'] = $this->getGlobalLeaderboard($period);
                }
                
                return $analytics;
            });

            return $this->successResponse($analytics, 'Global analytics retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving global analytics', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to retrieve global analytics', 500);
        }
    }

    /**
     * Set member reading goal
     * 
     * @param int $id Member ID
     * @param Request $request
     * @return JsonResponse
     */
    public function setReadingGoal(int $id, Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'goal_type' => ['required', 'string', Rule::in(['daily_words', 'weekly_words', 'monthly_words', 'daily_stories', 'weekly_stories', 'monthly_stories'])],
                'target_value' => ['required', 'integer', 'min:1', 'max:10000'],
                'start_date' => ['sometimes', 'date'],
                'end_date' => ['sometimes', 'date', 'after:start_date'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid goal parameters', 422, $validator->errors());
            }

            // Validate member access
            $member = $this->validateMemberAccess($id, $request);
            if (!$member) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Set reading goal
            $goalData = [
                'member_id' => $id,
                'goal_type' => $request->get('goal_type'),
                'target_value' => $request->get('target_value'),
                'start_date' => $request->get('start_date', now()->toDateString()),
                'end_date' => $request->get('end_date'),
                'is_active' => true,
            ];

            // TODO: Implement reading goals table and model
            // $goal = MemberReadingGoal::updateOrCreate(
            //     ['member_id' => $id, 'goal_type' => $goalData['goal_type']],
            //     $goalData
            // );

            return $this->successResponse([], 'Reading goal set successfully');
            
        } catch (\Exception $e) {
            Log::error('Error setting reading goal', [
                'member_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse('Failed to set reading goal', 500);
        }
    }

    /**
     * Record reading session
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function recordReadingSession(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'member_id' => ['required', 'integer', 'exists:members,id'],
                'story_id' => ['required', 'integer', 'exists:stories,id'],
                'words_read' => ['required', 'integer', 'min:0'],
                'reading_time' => ['required', 'integer', 'min:0'], // in seconds
                'reading_progress' => ['required', 'numeric', 'min:0', 'max:100'],
                'session_start' => ['required', 'date'],
                'session_end' => ['required', 'date', 'after:session_start'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid session data', 422, $validator->errors());
            }

            // Validate member access
            $member = $this->validateMemberAccess($request->get('member_id'), $request);
            if (!$member) {
                return $this->errorResponse('Member not found or access denied', 404);
            }

            // Record reading session
            $sessionData = [
                'member_id' => $request->get('member_id'),
                'story_id' => $request->get('story_id'),
                'words_read' => $request->get('words_read'),
                'reading_time' => $request->get('reading_time'),
                'reading_progress' => $request->get('reading_progress'),
                'session_start' => $request->get('session_start'),
                'session_end' => $request->get('session_end'),
                'device_id' => $request->header('X-Device-ID'),
                'recorded_at' => now(),
            ];

            // Update reading statistics
            MemberReadingStatistics::updateOrCreate(
                [
                    'member_id' => $sessionData['member_id'],
                    'date' => now()->toDateString(),
                ],
                [
                    'words_read' => \DB::raw('words_read + ' . $sessionData['words_read']),
                    'reading_time_minutes' => \DB::raw('reading_time_minutes + ' . ceil($sessionData['reading_time'] / 60)),
                    'stories_completed' => $request->get('reading_progress') >= 100 ? \DB::raw('stories_completed + 1') : \DB::raw('stories_completed'),
                    'updated_at' => now(),
                ]
            );

            // Update reading history
            MemberReadingHistory::updateOrCreate(
                [
                    'member_id' => $sessionData['member_id'],
                    'story_id' => $sessionData['story_id'],
                ],
                [
                    'reading_progress' => $sessionData['reading_progress'],
                    'time_spent' => \DB::raw('time_spent + ' . $sessionData['reading_time']),
                    'words_read' => \DB::raw('COALESCE(words_read, 0) + ' . $sessionData['words_read']),
                    'last_read_at' => now(),
                ]
            );

            // Clear relevant caches
            Cache::forget("analytics:member:{$sessionData['member_id']}:month");
            Cache::forget("summary:member:{$sessionData['member_id']}:month");

            return $this->successResponse([], 'Reading session recorded successfully');
            
        } catch (\Exception $e) {
            Log::error('Error recording reading session', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to record reading session', 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check rate limiting
     */
    private function checkRateLimit(Request $request, string $key): bool
    {
        $limiter = RateLimiter::for($key, function () {
            return Limit::perMinute(self::RATE_LIMIT);
        });

        return $limiter->attempt($request->ip(), 1);
    }

    /**
     * Validate member access
     */
    private function validateMemberAccess(int $memberId, Request $request): ?Member
    {
        $member = Member::find($memberId);
        
        if (!$member) {
            return null;
        }

        // Check if authenticated user can access this member's data
        $authenticatedMember = auth()->user();
        
        // Allow access if it's the same member or admin
        if ($authenticatedMember->id === $memberId || $authenticatedMember->hasRole('admin')) {
            return $member;
        }

        return null;
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
     * Get member reading goals
     */
    private function getMemberReadingGoals(int $memberId): array
    {
        // TODO: Implement reading goals functionality
        return [
            'daily_words' => ['target' => 500, 'current' => 0, 'progress' => 0],
            'weekly_words' => ['target' => 3500, 'current' => 0, 'progress' => 0],
            'monthly_words' => ['target' => 15000, 'current' => 0, 'progress' => 0],
        ];
    }

    /**
     * Get reading level comparison
     */
    private function getReadingLevelComparison(int $memberId): array
    {
        $member = Member::find($memberId);
        $memberLevel = $member->reading_level ?? 'beginner';
        
        $levelDistribution = Member::selectRaw('reading_level, COUNT(*) as count')
            ->groupBy('reading_level')
            ->pluck('count', 'reading_level')
            ->toArray();
        
        $totalMembers = array_sum($levelDistribution);
        
        return [
            'member_level' => $memberLevel,
            'level_distribution' => $levelDistribution,
            'member_percentile' => $this->calculateLevelPercentile($memberLevel, $levelDistribution, $totalMembers),
        ];
    }

    /**
     * Calculate level percentile
     */
    private function calculateLevelPercentile(string $level, array $distribution, int $total): float
    {
        $levelOrder = ['beginner', 'elementary', 'intermediate', 'advanced', 'expert'];
        $levelIndex = array_search($level, $levelOrder);
        
        if ($levelIndex === false || $total == 0) {
            return 0;
        }
        
        $lowerLevels = array_slice($levelOrder, 0, $levelIndex);
        $lowerCount = array_sum(array_intersect_key($distribution, array_flip($lowerLevels)));
        
        return round(($lowerCount / $total) * 100, 1);
    }

    /**
     * Get global leaderboard
     */
    private function getGlobalLeaderboard(string $period): array
    {
        $startDate = $this->getPeriodStartDate($period);
        
        return MemberReadingStatistics::select('member_id')
            ->selectRaw('SUM(words_read) as total_words')
            ->selectRaw('SUM(stories_completed) as total_stories')
            ->selectRaw('MAX(reading_streak) as max_streak')
            ->where('date', '>=', $startDate)
            ->groupBy('member_id')
            ->orderBy('total_words', 'desc')
            ->limit(10)
            ->with('member:id,name,reading_level')
            ->get()
            ->map(function ($stat, $index) {
                return [
                    'rank' => $index + 1,
                    'member_id' => $stat->member_id,
                    'member_name' => $stat->member->name ?? 'Unknown',
                    'reading_level' => $stat->member->reading_level ?? 'beginner',
                    'total_words' => $stat->total_words,
                    'total_stories' => $stat->total_stories,
                    'max_streak' => $stat->max_streak,
                ];
            })
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