// app/Repositories/MemberRepository.php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Member Repository with Enhanced Security & Analytics
 */
class MemberRepository extends BaseRepository
{
    protected function makeModel(): Model
    {
        return new Member();
    }

    protected function getSortableColumns(): array
    {
        return ['id', 'name', 'email', 'status', 'created_at', 'last_login_at'];
    }

    protected function getFilterableColumns(): array
    {
        return ['name', 'email', 'status', 'device_id'];
    }

    /**
     * Find member by email with security checks
     */
    public function findByEmail(string $email): ?Member
    {
        return $this->model
            ->where('email', $email)
            ->notLocked()
            ->first();
    }

    /**
     * Get member analytics dashboard data
     */
    public function getMemberAnalytics(int $memberId): array
    {
        $cacheKey = $this->getCacheKey('analytics', ['member_id' => $memberId]);
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $member = $this->findById($memberId, [
            'readingHistory.story',
            'storyRatings.story',
            'storyInteractions.story'
        ]);

        $analytics = [
            'reading_stats' => [
                'total_stories_read' => $member->readingHistory()->count(),
                'completed_stories' => $member->readingHistory()->completed()->count(),
                'in_progress_stories' => $member->readingHistory()->inProgress()->count(),
                'total_reading_time' => $member->total_reading_time,
                'average_reading_time' => $member->readingHistory()->avg('time_spent') ?? 0,
            ],
            'interaction_stats' => [
                'total_ratings' => $member->storyRatings()->count(),
                'average_rating' => $member->average_rating,
                'bookmarks' => $member->storyInteractions()->bookmarks()->count(),
                'shares' => $member->storyInteractions()->shares()->count(),
            ],
            'engagement_stats' => [
                'days_since_last_login' => $member->last_login_at 
                    ? $member->last_login_at->diffInDays(now()) 
                    : null,
                'total_sessions' => $member->storyViews()->distinct('device_id')->count(),
                'favorite_categories' => $this->getFavoriteCategories($memberId),
            ],
            'security_stats' => [
                'account_status' => $member->status,
                'email_verified' => !is_null($member->email_verified_at),
                'two_factor_enabled' => !is_null($member->two_factor_secret),
                'failed_login_attempts' => $member->failed_login_attempts,
                'is_locked' => $member->isLocked(),
            ]
        ];

        Cache::put($cacheKey, $analytics, 1800); // 30 minutes cache
        
        return $analytics;
    }

    /**
     * Get member's favorite categories
     */
    private function getFavoriteCategories(int $memberId): array
    {
        return DB::table('member_reading_history')
            ->join('stories', 'member_reading_history.story_id', '=', 'stories.id')
            ->join('categories', 'stories.category_id', '=', 'categories.id')
            ->where('member_reading_history.member_id', $memberId)
            ->select('categories.name', DB::raw('COUNT(*) as read_count'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('read_count')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Get members with reading statistics
     */
    public function getMembersWithStats(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->model
            ->select([
                'members.*',
                DB::raw('COUNT(DISTINCT member_reading_history.id) as total_reads'),
                DB::raw('SUM(member_reading_history.time_spent) as total_time'),
                DB::raw('AVG(member_story_ratings.rating) as avg_rating'),
                DB::raw('COUNT(DISTINCT member_story_interactions.id) as total_interactions')
            ])
            ->leftJoin('member_reading_history', 'members.id', '=', 'member_reading_history.member_id')
            ->leftJoin('member_story_ratings', 'members.id', '=', 'member_story_ratings.member_id')
            ->leftJoin('member_story_interactions', 'members.id', '=', 'member_story_interactions.member_id')
            ->groupBy('members.id')
            ->orderByDesc('total_reads')
            ->paginate(20);
    }

    /**
     * Search members by multiple criteria
     */
    public function searchMembers(array $criteria): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->model->newQuery();

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (!empty($criteria['date_from'])) {
            $query->whereDate('created_at', '>=', $criteria['date_from']);
        }

        if (!empty($criteria['date_to'])) {
            $query->whereDate('created_at', '<=', $criteria['date_to']);
        }

        if (!empty($criteria['verified_only'])) {
            $query->verified();
        }

        return $query->with(['readingHistory', 'storyRatings'])
                     ->orderByDesc('created_at')
                     ->get();
    }
}

