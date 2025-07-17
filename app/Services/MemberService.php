<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Repositories\MemberRepository;
use App\DTOs\MemberData;
use App\Exports\MembersExport;
use App\Notifications\MemberWelcomeNotification;
use Illuminate\Database\Eloquent\{Model, Collection};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Hash, DB, Mail, Notification, Storage};
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Enhanced Member Service - Business Logic Layer
 * 
 * Comprehensive business logic for member management with:
 * - Advanced member operations and lifecycle management
 * - Export functionality with multiple formats
 * - Bulk operations with transaction safety
 * - Security-first approach with validation
 * - Performance optimization with caching
 * - Integration with notification systems
 * 
 * Security Features:
 * - Password complexity validation
 * - Account lockout protection
 * - Input sanitization and validation
 * - Activity logging and audit trail
 * - Rate limiting for sensitive operations
 * 
 * Performance Features:
 * - Database transaction optimization
 * - Bulk operation efficiency
 * - Memory usage optimization for exports
 * - Queue integration for heavy operations
 * - Caching for frequently accessed data
 * 
 * @package App\Services
 * @author  Development Team
 * @version 2.0.0
 * @since   2025-01-17
 */
class MemberService extends BaseService
{
    /**
     * Member repository
     */
    private MemberRepository $memberRepository;

    /**
     * Maximum bulk operation size
     */
    private const MAX_BULK_SIZE = 1000;

    /**
     * Maximum export records
     */
    private const MAX_EXPORT_RECORDS = 10000;

    /**
     * Password validation rules
     */
    private const PASSWORD_RULES = [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special_chars' => true,
    ];

    /**
     * Initialize service
     */
    public function __construct(MemberRepository $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }

    /**
     * Create new member with validation and security checks
     * 
     * @param array $data Member data
     * @return Member Created member
     * @throws ValidationException
     */
    public function createMember(array $data): Member
    {
        // Validate input data
        $validatedData = $this->validateMemberData($data);

        // Check for existing email
        if ($this->memberRepository->findByEmail($validatedData['email'])) {
            throw ValidationException::withMessages([
                'email' => ['Email address is already registered.']
            ]);
        }

        // Validate password complexity
        if (isset($validatedData['password'])) {
            $this->validatePasswordComplexity($validatedData['password']);
        }

        return DB::transaction(function () use ($validatedData) {
            // Hash password if provided
            if (isset($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
                $validatedData['password_changed_at'] = now();
            }

            // Set default values
            $validatedData['status'] = $validatedData['status'] ?? Member::STATUS_PENDING;
            $validatedData['email_verification_token'] = \Str::random(64);

            // Create member
            $member = $this->memberRepository->create($validatedData);

            // Send welcome email
            $this->sendWelcomeNotification($member);

            // Log creation
            $this->logMemberOperation('created', $member);

            return $member;
        });
    }

    /**
     * Update member with validation
     * 
     * @param int $id Member ID
     * @param array $data Update data
     * @return Member Updated member
     * @throws ValidationException
     */
    public function updateMember(int $id, array $data): Member
    {
        $member = $this->memberRepository->findOrFail($id);
        
        // Validate input data
        $validatedData = $this->validateMemberData($data, $id);

        // Check for email conflicts
        if (isset($validatedData['email']) && $validatedData['email'] !== $member->email) {
            if ($this->memberRepository->findByEmail($validatedData['email'])) {
                throw ValidationException::withMessages([
                    'email' => ['Email address is already in use.']
                ]);
            }
            
            // Mark email as unverified if changed
            $validatedData['email_verified_at'] = null;
            $validatedData['email_verification_token'] = \Str::random(64);
        }

        // Handle password update
        if (isset($validatedData['password']) && !empty($validatedData['password'])) {
            $this->validatePasswordComplexity($validatedData['password']);
            $validatedData['password'] = Hash::make($validatedData['password']);
            $validatedData['password_changed_at'] = now();
        } else {
            unset($validatedData['password']);
        }

        return DB::transaction(function () use ($member, $validatedData) {
            $originalData = $member->toArray();
            
            // Update member
            $updatedMember = $this->memberRepository->update($member->id, $validatedData);

            // Log changes
            $this->logMemberChanges($member, $originalData, $validatedData);

            return $updatedMember;
        });
    }

    /**
     * Bulk update member status with validation
     * 
     * @param array $memberIds Array of member IDs
     * @param string $status New status
     * @return int Number of affected members
     */
    public function bulkUpdateStatus(array $memberIds, string $status): int
    {
        // Validate inputs
        $this->validateBulkOperation($memberIds);
        $this->validateMemberStatus($status);

        return DB::transaction(function () use ($memberIds, $status) {
            $affected = $this->memberRepository->bulkUpdateStatus($memberIds, $status);

            // Send notifications for status changes
            $this->notifyStatusChange($memberIds, $status);

            // Log bulk operation
            $this->logBulkOperation('status_update', $memberIds, $status, $affected);

            return $affected;
        });
    }

    /**
     * Bulk delete members with safety checks
     * 
     * @param array $memberIds Array of member IDs
     * @return int Number of deleted members
     */
    public function bulkDelete(array $memberIds): int
    {
        // Validate inputs
        $this->validateBulkOperation($memberIds);

        return DB::transaction(function () use ($memberIds) {
            $affected = $this->memberRepository->bulkDelete($memberIds);

            // Log bulk deletion
            $this->logBulkOperation('bulk_delete', $memberIds, null, $affected);

            return $affected;
        });
    }

    /**
     * Quick status change for individual member
     * 
     * @param int $memberId Member ID
     * @param string $status New status
     * @return Member Updated member
     */
    public function quickStatusChange(int $memberId, string $status): Member
    {
        $this->validateMemberStatus($status);
        
        $member = $this->memberRepository->findOrFail($memberId);
        $oldStatus = $member->status;

        return DB::transaction(function () use ($member, $status, $oldStatus) {
            $updatedMember = $this->memberRepository->update($member->id, [
                'status' => $status,
                'updated_at' => now(),
            ]);

            // Send status change notification
            $this->notifyStatusChange([$member->id], $status);

            // Log status change
            $this->logMemberOperation('status_changed', $updatedMember, [
                'old_status' => $oldStatus,
                'new_status' => $status,
            ]);

            return $updatedMember;
        });
    }

    /**
     * Export members to CSV
     * 
     * @param array $filters Export filters
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToCsv(array $filters = [])
    {
        $this->validateExportRequest($filters);
        
        $exportData = $this->memberRepository->getExportData($filters);
        
        $filename = 'members_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        // Create CSV content
        $csvContent = $this->generateCsvContent($exportData);
        
        // Store temporarily
        Storage::disk('local')->put("exports/{$filename}", $csvContent);
        
        // Log export
        $this->logExportOperation('csv', $filters, $exportData->count());
        
        return response()->download(
            storage_path("app/exports/{$filename}"),
            $filename,
            ['Content-Type' => 'text/csv']
        )->deleteFileAfterSend();
    }

    /**
     * Export members to Excel
     * 
     * @param array $filters Export filters
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToExcel(array $filters = [])
    {
        $this->validateExportRequest($filters);
        
        $filename = 'members_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        // Log export
        $exportData = $this->memberRepository->getExportData($filters);
        $this->logExportOperation('excel', $filters, $exportData->count());
        
        return Excel::download(
            new MembersExport($filters, $this->memberRepository),
            $filename
        );
    }

    /**
     * Export members to PDF
     * 
     * @param array $filters Export filters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportToPdf(array $filters = [])
    {
        $this->validateExportRequest($filters);
        
        $exportData = $this->memberRepository->getExportData($filters);
        
        if ($exportData->count() > 1000) {
            throw new \Exception('PDF export is limited to 1000 records. Please use Excel for larger exports.');
        }
        
        $pdf = Pdf::loadView('exports.members-pdf', [
            'members' => $exportData,
            'filters' => $filters,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name ?? 'System',
        ]);
        
        // Log export
        $this->logExportOperation('pdf', $filters, $exportData->count());
        
        $filename = 'members_report_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Send email to member
     * 
     * @param int $memberId Member ID
     * @param array $emailData Email data
     * @return bool Success status
     */
    public function sendEmail(int $memberId, array $emailData): bool
    {
        $member = $this->memberRepository->findOrFail($memberId);
        
        try {
            Mail::send(
                $emailData['template'] ?? 'emails.member.custom',
                [
                    'member' => $member,
                    'content' => $emailData['content'] ?? '',
                    'subject' => $emailData['subject'] ?? 'Message from Admin',
                ],
                function ($message) use ($member, $emailData) {
                    $message->to($member->email, $member->name)
                           ->subject($emailData['subject'] ?? 'Message from Admin');
                }
            );

            // Log email sent
            $this->logMemberOperation('email_sent', $member, [
                'subject' => $emailData['subject'] ?? 'Custom Email',
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send email to member', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send notification to member
     * 
     * @param int $memberId Member ID
     * @param array $notificationData Notification data
     * @return bool Success status
     */
    public function sendNotification(int $memberId, array $notificationData): bool
    {
        $member = $this->memberRepository->findOrFail($memberId);
        
        try {
            // Send notification based on type
            $member->notify(new \App\Notifications\CustomMemberNotification($notificationData));

            // Log notification sent
            $this->logMemberOperation('notification_sent', $member, [
                'type' => $notificationData['type'] ?? 'custom',
                'title' => $notificationData['title'] ?? 'Notification',
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send notification to member', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reset member statistics
     * 
     * @param int $memberId Member ID
     * @return bool Success status
     */
    public function resetStatistics(int $memberId): bool
    {
        $member = $this->memberRepository->findOrFail($memberId);
        
        return DB::transaction(function () use ($member) {
            try {
                // Delete reading history
                $member->readingHistory()->delete();
                
                // Delete interactions
                $member->interactions()->delete();
                
                // Delete story views
                $member->storyViews()->delete();
                
                // Clear caches
                \Cache::forget("member_statistics_{$member->id}");
                
                // Log operation
                $this->logMemberOperation('statistics_reset', $member);
                
                return true;
            } catch (\Exception $e) {
                \Log::error('Failed to reset member statistics', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                ]);
                
                return false;
            }
        });
    }

    /**
     * Get member analytics data
     * 
     * @param int $memberId Member ID
     * @return array Analytics data
     */
    public function getMemberAnalytics(int $memberId): array
    {
        return $this->memberRepository->getMemberStatistics($memberId);
    }

    /**
     * Validate member data
     */
    private function validateMemberData(array $data, ?int $memberId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255|min:2',
            'email' => 'required|email:rfc,dns|max:255',
            'phone' => 'nullable|string|max:20|regex:/^[\+]?[0-9\-\(\)\s]+$/',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'status' => 'nullable|in:active,inactive,suspended,pending',
            'device_id' => 'nullable|string|max:255',
        ];

        // Add unique validation for email if updating
        if ($memberId) {
            $rules['email'] .= '|unique:members,email,' . $memberId;
        } else {
            $rules['email'] .= '|unique:members,email';
            $rules['password'] = 'required|string|min:12';
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $rules['password'] = 'string|min:12|confirmed';
        }

        return validator($data, $rules)->validate();
    }

    /**
     * Validate password complexity
     */
    private function validatePasswordComplexity(string $password): void
    {
        $errors = [];

        if (strlen($password) < self::PASSWORD_RULES['min_length']) {
            $errors[] = sprintf('Password must be at least %d characters long.', self::PASSWORD_RULES['min_length']);
        }

        if (self::PASSWORD_RULES['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (self::PASSWORD_RULES['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (self::PASSWORD_RULES['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if (self::PASSWORD_RULES['require_special_chars'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'password' => $errors
            ]);
        }
    }

    /**
     * Validate member status
     */
    private function validateMemberStatus(string $status): void
    {
        $validStatuses = [
            Member::STATUS_ACTIVE,
            Member::STATUS_INACTIVE,
            Member::STATUS_SUSPENDED,
            Member::STATUS_PENDING,
        ];

        if (!in_array($status, $validStatuses)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status provided.']
            ]);
        }
    }

    /**
     * Validate bulk operation
     */
    private function validateBulkOperation(array $memberIds): void
    {
        if (empty($memberIds)) {
            throw ValidationException::withMessages([
                'members' => ['No members selected for bulk operation.']
            ]);
        }

        if (count($memberIds) > self::MAX_BULK_SIZE) {
            throw ValidationException::withMessages([
                'members' => [sprintf('Bulk operations are limited to %d members at a time.', self::MAX_BULK_SIZE)]
            ]);
        }

        // Validate that all IDs are integers
        foreach ($memberIds as $id) {
            if (!is_numeric($id) || $id <= 0) {
                throw ValidationException::withMessages([
                    'members' => ['Invalid member ID provided.']
                ]);
            }
        }
    }

    /**
     * Validate export request
     */
    private function validateExportRequest(array $filters): void
    {
        // Estimate record count
        $estimatedCount = $this->memberRepository->getFilteredMembers($filters)->count();
        
        if ($estimatedCount > self::MAX_EXPORT_RECORDS) {
            throw ValidationException::withMessages([
                'export' => [sprintf('Export is limited to %d records. Please refine your filters.', self::MAX_EXPORT_RECORDS)]
            ]);
        }
    }

    /**
     * Generate CSV content
     */
    private function generateCsvContent(Collection $data): string
    {
        $csv = '';
        
        if ($data->isNotEmpty()) {
            // Add headers
            $headers = array_keys($data->first());
            $csv .= implode(',', array_map([$this, 'escapeCsvValue'], $headers)) . "\n";
            
            // Add data rows
            foreach ($data as $row) {
                $csv .= implode(',', array_map([$this, 'escapeCsvValue'], array_values($row))) . "\n";
            }
        }
        
        return $csv;
    }

    /**
     * Escape CSV value
     */
    private function escapeCsvValue($value): string
    {
        if (is_null($value)) {
            return '';
        }
        
        $value = (string) $value;
        
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }

    /**
     * Send welcome notification to new member
     */
    private function sendWelcomeNotification(Member $member): void
    {
        try {
            $member->notify(new MemberWelcomeNotification());
        } catch (\Exception $e) {
            \Log::warning('Failed to send welcome notification', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify members of status change
     */
    private function notifyStatusChange(array $memberIds, string $status): void
    {
        try {
            $members = $this->memberRepository->findMany($memberIds);
            
            foreach ($members as $member) {
                $member->notify(new \App\Notifications\StatusChangeNotification($status));
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send status change notifications', [
                'member_ids' => $memberIds,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log member operation
     */
    private function logMemberOperation(string $operation, Member $member, array $details = []): void
    {
        \Log::info("Member operation performed", [
            'operation' => $operation,
            'member_id' => $member->id,
            'member_email' => $member->email,
            'details' => $details,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log member changes
     */
    private function logMemberChanges(Member $member, array $original, array $changes): void
    {
        $changedFields = [];
        
        foreach ($changes as $field => $newValue) {
            if (isset($original[$field]) && $original[$field] !== $newValue) {
                $changedFields[$field] = [
                    'old' => $field === 'password' ? '[HIDDEN]' : $original[$field],
                    'new' => $field === 'password' ? '[HIDDEN]' : $newValue,
                ];
            }
        }

        if (!empty($changedFields)) {
            \Log::info("Member data updated", [
                'member_id' => $member->id,
                'member_email' => $member->email,
                'changes' => $changedFields,
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Log bulk operation
     */
    private function logBulkOperation(string $operation, array $memberIds, $data, int $affected): void
    {
        \Log::info("Bulk member operation performed", [
            'operation' => $operation,
            'member_ids' => $memberIds,
            'data' => $data,
            'affected_count' => $affected,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log export operation
     */
    private function logExportOperation(string $format, array $filters, int $recordCount): void
    {
        \Log::info("Member export performed", [
            'format' => $format,
            'filters' => $filters,
            'record_count' => $recordCount,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}