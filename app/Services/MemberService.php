// app/Services/MemberService.php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MemberRepository;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Member Service with Enhanced Security & Business Logic
 */
class MemberService extends BaseService
{
    private MemberRepository $memberRepository;

    public function __construct(MemberRepository $memberRepository)
    {
        parent::__construct($memberRepository);
        $this->memberRepository = $memberRepository;
    }

    /**
     * Create member with enhanced security
     */
    public function createMember(array $data): Member
    {
        // Generate device ID if not provided
        if (empty($data['device_id'])) {
            $data['device_id'] = Str::uuid();
        }

        // Set default status
        $data['status'] = $data['status'] ?? Member::STATUS_PENDING;

        return DB::transaction(function () use ($data) {
            $member = $this->create($data);
            
            // Send verification email
            $this->sendEmailVerification($member);
            
            // Log member creation
            activity()
                ->causedBy(auth()->user())
                ->performedOn($member)
                ->log('Member created');

            return $member;
        });
    }

    /**
     * Authenticate member with security checks
     */
    public function authenticateMember(string $email, string $password, string $deviceId): array
    {
        $member = $this->memberRepository->findByEmail($email);

        if (!$member) {
            throw new \Exception('Invalid credentials');
        }

        // Check if account is locked
        if ($member->isLocked()) {
            throw new \Exception('Account is temporarily locked due to multiple failed login attempts');
        }

        // Verify password
        if (!Hash::check($password, $member->password)) {
            $member->recordFailedLogin();
            throw new \Exception('Invalid credentials');
        }

        // Verify device ID
        if ($member->device_id !== $deviceId) {
            throw new \Exception('Device not recognized. Please verify your device.');
        }

        // Record successful login
        $member->recordSuccessfulLogin();

        // Generate JWT token
        $token = $this->generateJwtToken($member);

        return [
            'member' => $member,
            'token' => $token,
            'expires_at' => now()->addHours(24)
        ];
    }

    /**
     * Generate secure JWT token
     */
    private function generateJwtToken(Member $member): string
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $member->id,
            'aud' => 'stories-app',
            'exp' => time() + (24 * 60 * 60), // 24 hours
            'iat' => time(),
            'device_id' => $member->device_id,
            'user_type' => 'member'
        ];

        return \Firebase\JWT\JWT::encode($payload, config('app.jwt_secret'), 'HS256');
    }

    /**
     * Get member analytics
     */
    public function getMemberAnalytics(int $memberId): array
    {
        return $this->memberRepository->getMemberAnalytics($memberId);
    }

    /**
     * Update member with security validation
     */
    public function updateMember(int $id, array $data): Member
    {
        // Remove sensitive fields that shouldn't be updated directly
        unset($data['password'], $data['failed_login_attempts'], $data['locked_until']);

        return DB::transaction(function () use ($id, $data) {
            $member = $this->update($id, $data);
            
            // Log member update
            activity()
                ->causedBy(auth()->user())
                ->performedOn($member)
                ->log('Member updated');

            return $member;
        });
    }

    /**
     * Reset member password with security
     */
    public function resetPassword(int $memberId, string $newPassword): bool
    {
        $member = $this->findById($memberId);
        
        return DB::transaction(function () use ($member, $newPassword) {
            // This will automatically validate password strength and history
            $member->password = $newPassword;
            $member->save();

            // Force re-authentication on all devices
            $member->tokens()->delete();

            // Log password reset
            activity()
                ->causedBy(auth()->user())
                ->performedOn($member)
                ->log('Password reset');

            return true;
        });
    }

    /**
     * Suspend member account
     */
    public function suspendMember(int $memberId, string $reason = null): bool
    {
        return DB::transaction(function () use ($memberId, $reason) {
            $member = $this->findById($memberId);
            
            $member->update([
                'status' => Member::STATUS_SUSPENDED,
                'locked_until' => now()->addDays(7) // 7-day suspension for stories app
            ]);

            // Revoke all tokens
            $member->tokens()->delete();

            // Log suspension
            activity()
                ->causedBy(auth()->user())
                ->performedOn($member)
                ->withProperties(['reason' => $reason])
                ->log('Member suspended');

            return true;
        });
    }

    /**
     * Validation rules for member creation
     */
    protected function getCreateValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email|max:255',
            'phone' => 'nullable|string|max:20|unique:members,phone',
            'password' => 'required|string|min:8|confirmed', // Simplified: 8+ chars
            'device_id' => 'nullable|string|max:255',
            'status' => 'in:active,inactive,pending,suspended',
        ];
    }

    /**
     * Validation rules for member update
     */
    protected function getUpdateValidationRules(int $id): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:members,email,{$id}|max:255",
            'phone' => "nullable|string|max:20|unique:members,phone,{$id}",
            'status' => 'in:active,inactive,pending,suspended',
        ];
    }

    /**
     * Get allowed filter fields
     */
    protected function getAllowedFilters(): array
    {
        return ['name', 'email', 'status', 'created_at'];
    }

    /**
     * Send email verification
     */
    private function sendEmailVerification(Member $member): void
    {
        // Implementation for email verification
        // This would integrate with your email service
        \Log::info("Email verification sent to member: {$member->email}");
    }

    /**
     * Execute before deletion - check dependencies
     */
    protected function beforeDelete(Model $model): void
    {
        /** @var Member $model */
        
        // Check if member has reading history
        if ($model->readingHistory()->count() > 0) {
            throw new \Exception("Cannot delete member '{$model->name}' because they have reading history.");
        }

        // Check if member has ratings
        if ($model->storyRatings()->count() > 0) {
            throw new \Exception("Cannot delete member '{$model->name}' because they have story ratings.");
        }
    }
}   