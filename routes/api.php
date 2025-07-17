<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Story App API Routes - V1
|--------------------------------------------------------------------------
|
| Enterprise-grade API routes for the Story mobile application
| with comprehensive security, rate limiting, and permission control
|
*/

Route::prefix('v1')->group(function () {
    
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('forgot-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'resetPassword']);
        
        // Protected auth routes
        Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
            Route::post('refresh', [\App\Http\Controllers\Api\V1\AuthController::class, 'refresh']);
            Route::get('profile', [\App\Http\Controllers\Api\V1\AuthController::class, 'profile']);
            Route::put('profile', [\App\Http\Controllers\Api\V1\AuthController::class, 'updateProfile']);
            Route::post('change-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'changePassword']);
        });
    });

    // Public Routes (No Authentication Required)
    Route::middleware(['throttle:100,1'])->group(function () {
        
        // Stories - Public Access
        Route::get('stories', [\App\Http\Controllers\Api\V1\StoryController::class, 'index']);
        Route::get('stories/{story}', [\App\Http\Controllers\Api\V1\StoryController::class, 'show']);
        Route::get('stories/category/{category}', [\App\Http\Controllers\Api\V1\StoryController::class, 'byCategory']);
        Route::get('stories/tag/{tag}', [\App\Http\Controllers\Api\V1\StoryController::class, 'byTag']);
        
        // Categories - Public Access
        Route::get('categories', [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);
        Route::get('categories/{category}', [\App\Http\Controllers\Api\V1\CategoryController::class, 'show']);
        
        // Tags - Public Access
        Route::get('tags', [\App\Http\Controllers\Api\V1\TagController::class, 'index']);
        Route::get('tags/{tag}', [\App\Http\Controllers\Api\V1\TagController::class, 'show']);
        
        // Search Routes
        Route::get('search/stories', [\App\Http\Controllers\Api\V1\SearchController::class, 'stories']);
        Route::get('search/categories', [\App\Http\Controllers\Api\V1\SearchController::class, 'categories']);
        Route::get('search/tags', [\App\Http\Controllers\Api\V1\SearchController::class, 'tags']);
    });

    // Protected Routes (Authentication Required)
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        
        // Member Dashboard
        Route::prefix('member')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\V1\MemberController::class, 'dashboard']);
            Route::get('reading-history', [\App\Http\Controllers\Api\V1\MemberController::class, 'readingHistory']);
            Route::get('favorites', [\App\Http\Controllers\Api\V1\MemberController::class, 'favorites']);
            Route::get('statistics', [\App\Http\Controllers\Api\V1\MemberController::class, 'statistics']);
        });

        // Story Interactions
        Route::prefix('stories/{story}')->group(function () {
            Route::post('view', [\App\Http\Controllers\Api\V1\StoryInteractionController::class, 'recordView']);
            Route::post('like', [\App\Http\Controllers\Api\V1\StoryInteractionController::class, 'like']);
            Route::post('dislike', [\App\Http\Controllers\Api\V1\StoryInteractionController::class, 'dislike']);
            Route::post('favorite', [\App\Http\Controllers\Api\V1\StoryInteractionController::class, 'favorite']);
            Route::delete('favorite', [\App\Http\Controllers\Api\V1\StoryInteractionController::class, 'unfavorite']);
            Route::post('reading-progress', [\App\Http\Controllers\Api\V1\StoryInteractionController::class, 'updateProgress']);
        });

        // File Uploads
        Route::prefix('media')->group(function () {
            Route::post('upload', [\App\Http\Controllers\Api\V1\MediaController::class, 'upload']);
            Route::delete('{media}', [\App\Http\Controllers\Api\V1\MediaController::class, 'delete']);
        });

        // Device Management
        Route::prefix('devices')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\DeviceController::class, 'index']);
            Route::post('register', [\App\Http\Controllers\Api\V1\DeviceController::class, 'register']);
            Route::delete('{device}', [\App\Http\Controllers\Api\V1\DeviceController::class, 'unregister']);
        });
    });

    // Admin API Routes (High Security)
    Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        
        // Admin Authentication Check
        Route::get('check', [\App\Http\Controllers\Api\V1\AdminController::class, 'check']);
        
        // Admin Dashboard Data
        Route::get('dashboard', [\App\Http\Controllers\Api\V1\AdminController::class, 'dashboard']);
        Route::get('statistics', [\App\Http\Controllers\Api\V1\AdminController::class, 'statistics']);
        
        // Admin Story Management
        Route::prefix('stories')->group(function () {
            Route::get('pending', [\App\Http\Controllers\Api\V1\AdminStoryController::class, 'pending']);
            Route::post('{story}/approve', [\App\Http\Controllers\Api\V1\AdminStoryController::class, 'approve']);
            Route::post('{story}/reject', [\App\Http\Controllers\Api\V1\AdminStoryController::class, 'reject']);
            Route::post('{story}/publish', [\App\Http\Controllers\Api\V1\AdminStoryController::class, 'publish']);
            Route::post('{story}/unpublish', [\App\Http\Controllers\Api\V1\AdminStoryController::class, 'unpublish']);
        });

        // Admin Member Management
        Route::prefix('members')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\AdminMemberController::class, 'index']);
            Route::get('{member}', [\App\Http\Controllers\Api\V1\AdminMemberController::class, 'show']);
            Route::post('{member}/suspend', [\App\Http\Controllers\Api\V1\AdminMemberController::class, 'suspend']);
            Route::post('{member}/activate', [\App\Http\Controllers\Api\V1\AdminMemberController::class, 'activate']);
            Route::delete('{member}', [\App\Http\Controllers\Api\V1\AdminMemberController::class, 'delete']);
        });

        // System Health & Monitoring
        Route::prefix('system')->group(function () {
            Route::get('health', [\App\Http\Controllers\Api\V1\SystemController::class, 'health']);
            Route::get('logs', [\App\Http\Controllers\Api\V1\SystemController::class, 'logs']);
            Route::get('cache-status', [\App\Http\Controllers\Api\V1\SystemController::class, 'cacheStatus']);
            Route::post('clear-cache', [\App\Http\Controllers\Api\V1\SystemController::class, 'clearCache']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Legacy API Support (V0)
|--------------------------------------------------------------------------
|
| Maintained for backward compatibility with older app versions
| Will be deprecated in future releases
|
*/

Route::prefix('legacy')->middleware(['throttle:30,1'])->group(function () {
    Route::get('stories', [\App\Http\Controllers\Api\LegacyController::class, 'stories']);
    Route::get('categories', [\App\Http\Controllers\Api\LegacyController::class, 'categories']);
});

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| External service integrations and webhook endpoints
| for third-party services and payment gateways
|
*/

Route::prefix('webhooks')->middleware(['throttle:10,1'])->group(function () {
    Route::post('payment', [\App\Http\Controllers\Api\WebhookController::class, 'payment']);
    Route::post('analytics', [\App\Http\Controllers\Api\WebhookController::class, 'analytics']);
    Route::post('notification', [\App\Http\Controllers\Api\WebhookController::class, 'notification']);
});