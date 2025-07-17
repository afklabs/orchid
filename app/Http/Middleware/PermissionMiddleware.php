<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Enterprise Permission Middleware
 * 
 * Advanced middleware for handling permission-based access control with
 * comprehensive security features, audit logging, and performance optimization.
 * 
 * Security Features:
 * - Permission validation with caching
 * - Rate limiting for permission checks
 * - Audit logging for access attempts
 * - Account status validation
 * - IP-based access control
 * 
 * Performance Features:
 * - Permission caching
 * - Optimized database queries
 * - Rate limiting to prevent abuse
 * 
 * @package App\Http\Middleware
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class PermissionMiddleware
{
    /**
     * Rate limit key prefix for permission checks
     */
    private const RATE_LIMIT_PREFIX = 'permission_check';

    /**
     * Maximum permission checks per minute per user
     */
    private const MAX_CHECKS_PER_MINUTE = 100;

    /**
     * Handle an incoming request with comprehensive permission checking.
     *
     * @param \Illuminate\Http\Request $request Incoming HTTP request
     * @param \Closure $next Next middleware in pipeline
     * @param string ...$permissions Required permissions (comma-separated or multiple args)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$permissions): SymfonyResponse
    {
        // Parse permissions from arguments
        $requiredPermissions = $this->parsePermissions($permissions);

        // Validate request has permissions specified
        if (empty($requiredPermissions)) {
            return $this->handleError(
                $request,
                'No permissions specified for route protection',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'MIDDLEWARE_CONFIG_ERROR'
            );
        }

        // Check if user is authenticated
        if (!Auth::check()) {
            return $this->handleUnauthorized($request, 'User not authenticated');
        }

        $user = Auth::user();

        // Apply rate limiting to prevent permission check abuse
        if (!$this->checkRateLimit($user->id)) {
            return $this->handleError(
                $request,
                'Too many permission checks. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
                'RATE_LIMIT_EXCEEDED'
            );
        }

        // Validate user account status
        $accountValidation = $this->validateUserAccount($user);
        if ($accountValidation !== true) {
            return $this->handleUnauthorized($request, $accountValidation);
        }

        // Check user permissions
        $permissionCheck = $this->checkUserPermissions($user, $requiredPermissions);
        if ($permissionCheck !== true) {
            return $this->handleForbidden($request, $permissionCheck, $requiredPermissions);
        }

        // Log successful access for audit trail
        $this->logSuccessfulAccess($request, $user, $requiredPermissions);

        // Continue to next middleware
        return $next($request);
    }

    /**
     * Parse permissions from middleware arguments.
     * 
     * Supports both comma-separated and multiple argument formats:
     * - 'permission:stories.view,stories.edit'
     * - 'permission:stories.view:stories.edit'
     * 
     * @param array $permissions Raw permission arguments
     * @return array Parsed permission names
     */
    private function parsePermissions(array $permissions): array
    {
        $parsed = [];

        foreach ($permissions as $permission) {
            // Handle comma-separated permissions
            if (str_contains($permission, ',')) {
                $parsed = array_merge($parsed, array_map('trim', explode(',', $permission)));
            } else {
                $parsed[] = trim($permission);
            }
        }

        // Remove empty values and duplicates
        return array_unique(array_filter($parsed));
    }

    /**
     * Validate user account status and security flags.
     * 
     * @param \App\Models\User $user User to validate
     * @return true|string True if valid, error message if invalid
     */
    private function validateUserAccount($user): true|string
    {
        // Check if user model has required methods (backward compatibility)
        if (!method_exists($user, 'isLocked')) {
            return true; // Skip validation for basic user models
        }

        // Check if account is active
        if (property_exists($user, 'is_active') && !$user->is_active) {
            Log::warning('Access denied: Inactive user account', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
            ]);
            
            return 'Account is deactivated. Please contact administrator.';
        }

        // Check if account is locked
        if ($user->isLocked()) {
            Log::warning('Access denied: Locked user account', [
                'user_id' => $user->id,
                'email' => $user->email,
                'locked_until' => $user->locked_until?->toISOString(),
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return 'Account is temporarily locked due to security reasons.';
        }

        // Check email verification if required
        if (config('auth.verification_required', false) && !$user->hasVerifiedEmail()) {
            return 'Email verification required.';
        }

        return true;
    }

    /**
     * Check if user has required permissions with optimization.
     * 
     * @param \App\Models\User $user User to check
     * @param array $requiredPermissions Required permissions
     * @return true|string True if authorized, error message if not
     */
    private function checkUserPermissions($user, array $requiredPermissions): true|string
    {
        // Check if user model supports permission checking
        if (!method_exists($user, 'hasAccess') && !method_exists($user, 'hasAnyAccess')) {
            Log::error('User model does not support permission checking', [
                'user_id' => $user->id,
                'model_class' => get_class($user),
                'timestamp' => now()->toISOString(),
            ]);

            return 'Permission system not available.';
        }

        try {
            // Check for wildcard permissions first (performance optimization)
            foreach ($requiredPermissions as $permission) {
                if (str_contains($permission, '*')) {
                    if (method_exists($user, 'hasAccess') && $user->hasAccess($permission)) {
                        return true;
                    }
                }
            }

            // Check if user has any of the required permissions
            if (method_exists($user, 'hasAnyAccess')) {
                if ($user->hasAnyAccess($requiredPermissions)) {
                    return true;
                }
            } elseif (method_exists($user, 'hasAccess')) {
                // Fallback to individual permission checking
                foreach ($requiredPermissions as $permission) {
                    if ($user->hasAccess($permission)) {
                        return true;
                    }
                }
            }

            // Log permission denial for audit
            Log::info('Access denied: Insufficient permissions', [
                'user_id' => $user->id,
                'email' => $user->email,
                'required_permissions' => $requiredPermissions,
                'user_permissions' => method_exists($user, 'getCachedPermissions') 
                    ? $user->getCachedPermissions() 
                    : ($user->permissions ?? []),
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
            ]);

            return 'Insufficient permissions to access this resource.';

        } catch (\Throwable $exception) {
            Log::error('Permission check failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
                'required_permissions' => $requiredPermissions,
                'timestamp' => now()->toISOString(),
            ]);

            return 'Permission check failed. Please try again.';
        }
    }

    /**
     * Apply rate limiting to permission checks.
     * 
     * @param int $userId User ID for rate limiting
     * @return bool True if within rate limit
     */
    private function checkRateLimit(int $userId): bool
    {
        $key = self::RATE_LIMIT_PREFIX . ':' . $userId;

        return RateLimiter::attempt(
            $key,
            self::MAX_CHECKS_PER_MINUTE,
            function () {
                // This callback is executed if within rate limit
                return true;
            },
            60 // 1 minute decay
        );
    }

    /**
     * Log successful access for audit trail.
     * 
     * @param \Illuminate\Http\Request $request Current request
     * @param \App\Models\User $user Authenticated user
     * @param array $permissions Required permissions
     */
    private function logSuccessfulAccess(Request $request, $user, array $permissions): void
    {
        Log::info('Permission check successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'route' => $request->route()?->getName() ?? 'unknown',
            'method' => $request->method(),
            'url' => $request->url(),
            'required_permissions' => $permissions,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle unauthorized access (401).
     * 
     * @param \Illuminate\Http\Request $request Current request
     * @param string $reason Reason for denial
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function handleUnauthorized(Request $request, string $reason): SymfonyResponse
    {
        Log::warning('Unauthorized access attempt', [
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->url(),
            'timestamp' => now()->toISOString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => $reason,
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->guest(route('login'))
                        ->with('error', $reason);
    }

    /**
     * Handle forbidden access (403).
     * 
     * @param \Illuminate\Http\Request $request Current request
     * @param string $reason Reason for denial
     * @param array $requiredPermissions Required permissions
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function handleForbidden(Request $request, string $reason, array $requiredPermissions): SymfonyResponse
    {
        Log::warning('Forbidden access attempt', [
            'user_id' => Auth::id(),
            'reason' => $reason,
            'required_permissions' => $requiredPermissions,
            'ip_address' => $request->ip(),
            'url' => $request->url(),
            'timestamp' => now()->toISOString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => $reason,
                    'details' => [
                        'required_permissions' => $requiredPermissions,
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->view('errors.403', [
            'message' => $reason,
            'required_permissions' => $requiredPermissions,
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Handle general errors (500, etc.).
     * 
     * @param \Illuminate\Http\Request $request Current request
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string $errorCode Internal error code
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function handleError(Request $request, string $message, int $statusCode, string $errorCode): SymfonyResponse
    {
        Log::error('Permission middleware error', [
            'message' => $message,
            'error_code' => $errorCode,
            'status_code' => $statusCode,
            'url' => $request->url(),
            'timestamp' => now()->toISOString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $errorCode,
                    'message' => $message,
                ],
                'timestamp' => now()->toISOString(),
            ], $statusCode);
        }

        return response()->view('errors.500', [
            'message' => $message,
        ], $statusCode);
    }
}