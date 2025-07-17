// routes/platform.php - Orchid Platform Routes
use App\Orchid\Screens\Story\{StoryListScreen, StoryEditScreen};
use App\Orchid\Screens\Analytics\{ReadingAnalyticsDashboardScreen, MemberAnalyticsScreen};

Route::screen('stories', StoryListScreen::class)
    ->name('platform.stories')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Stories'), route('platform.stories'))
    );

Route::screen('stories/create', StoryEditScreen::class)
    ->name('platform.stories.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.stories')
        ->push(__('Create Story'), route('platform.stories.create'))
    );

Route::screen('stories/{story}/edit', StoryEditScreen::class)
    ->name('platform.stories.edit')
    ->breadcrumbs(fn (Trail $trail, $story) => $trail
        ->parent('platform.stories')
        ->push(__('Edit Story'), route('platform.stories.edit', $story))
    );

Route::screen('stories/{story}/analytics', StoryAnalyticsScreen::class)
    ->name('platform.stories.analytics')
    ->breadcrumbs(fn (Trail $trail, $story) => $trail
        ->parent('platform.stories')
        ->push(__('Story Analytics'), route('platform.stories.analytics', $story))
    );

// Enhanced Quick Actions for Stories
Route::group([
    'prefix' => 'stories',
    'middleware' => ['web', 'auth:admin'],
    'namespace' => 'App\Orchid\Screens\Story',
], function () {
    
    Route::post('{story}/publish', [StoryEditScreen::class, 'publishStory'])
        ->name('platform.stories.publish');
    
    Route::post('{story}/unpublish', [StoryEditScreen::class, 'unpublishStory'])
        ->name('platform.stories.unpublish');
    
    Route::post('{story}/duplicate', [StoryEditScreen::class, 'duplicateStory'])
        ->name('platform.stories.duplicate');
    
    Route::delete('{story}', [StoryEditScreen::class, 'deleteStory'])
        ->name('platform.stories.delete');
});
