// routes/api.php - API Routes for Content Analysis
use App\Http\Controllers\Api\Admin\ContentAnalysisController;

Route::group([
    'prefix' => 'admin/api',
    'middleware' => ['web', 'auth:admin'],
    'namespace' => 'App\Http\Controllers\Api\Admin',
], function () {
    
    // Content Analysis Routes
    Route::post('analyze-content', [ContentAnalysisController::class, 'analyzeContent'])
        ->name('api.admin.analyze-content');
    
    Route::post('content-progress', [ContentAnalysisController::class, 'getContentProgress'])
        ->name('api.admin.content-progress');
    
    Route::post('reading-level-suggestions', [ContentAnalysisController::class, 'getReadingLevelSuggestions'])
        ->name('api.admin.reading-level-suggestions');
    
    Route::post('validate-content', [ContentAnalysisController::class, 'validateContent'])
        ->name('api.admin.validate-content');
    
    Route::post('optimization-suggestions', [ContentAnalysisController::class, 'getOptimizationSuggestions'])
        ->name('api.admin.optimization-suggestions');
});
