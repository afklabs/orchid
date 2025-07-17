<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Orchid\Support\Color;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Boot the application services.
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // Register permissions
        $dashboard->registerPermissions([
            ItemPermission::group(__('Content Management'))
                ->addPermission('platform.stories', __('Stories'))
                ->addPermission('platform.stories.create', __('Create Stories'))
                ->addPermission('platform.stories.edit', __('Edit Stories'))
                ->addPermission('platform.stories.delete', __('Delete Stories'))
                ->addPermission('platform.stories.publish', __('Publish Stories'))
                ->addPermission('platform.categories', __('Categories'))
                ->addPermission('platform.tags', __('Tags')),

            ItemPermission::group(__('Analytics & Reporting'))
                ->addPermission('platform.analytics', __('View Analytics'))
                ->addPermission('platform.analytics.stories', __('Story Analytics'))
                ->addPermission('platform.analytics.members', __('Member Analytics'))
                ->addPermission('platform.leaderboard', __('Leaderboard'))
                ->addPermission('platform.achievements', __('Achievements')),

            ItemPermission::group(__('Member Management'))
                ->addPermission('platform.members', __('Members'))
                ->addPermission('platform.members.create', __('Create Members'))
                ->addPermission('platform.members.edit', __('Edit Members'))
                ->addPermission('platform.members.delete', __('Delete Members'))
                ->addPermission('platform.members.analytics', __('Member Analytics')),

            ItemPermission::group(__('System Management'))
                ->addPermission('platform.settings', __('Settings'))
                ->addPermission('platform.audit', __('Audit Logs'))
                ->addPermission('platform.security', __('Security Events')),
        ]);
    }

    /**
     * Register the application menu.
     */
    public function registerMainMenu(): array
    {
        return [
            // Dashboard
            Menu::make('Dashboard')
                ->icon('home')
                ->route('platform.main')
                ->title(__('Navigation'))
                ->sort(1),

            // Content Management
            Menu::make('Stories')
                ->icon('book-open')
                ->route('platform.stories')
                ->permission('platform.stories')
                ->badge(fn () => \App\Models\Story::where('active', true)->count(), Color::SUCCESS)
                ->sort(10),

            Menu::make('Categories')
                ->icon('folder')
                ->route('platform.categories')
                ->permission('platform.categories')
                ->sort(20),

            Menu::make('Tags')
                ->icon('tag')
                ->route('platform.tags')
                ->permission('platform.tags')
                ->sort(30),

            // Analytics & Reporting
            Menu::make('Analytics')
                ->icon('graph')
                ->permission('platform.analytics')
                ->list([
                    Menu::make('Reading Analytics')
                        ->icon('chart')
                        ->route('platform.analytics.dashboard')
                        ->permission('platform.analytics'),

                    Menu::make('Story Analytics')
                        ->icon('book')
                        ->route('platform.analytics.stories')
                        ->permission('platform.analytics.stories'),

                    Menu::make('Leaderboard')
                        ->icon('trophy')
                        ->route('platform.leaderboard')
                        ->permission('platform.leaderboard'),

                    Menu::make('Achievements')
                        ->icon('award')
                        ->route('platform.achievements')
                        ->permission('platform.achievements'),
                ])
                ->sort(40),

            // Member Management
            Menu::make('Members')
                ->icon('people')
                ->route('platform.members')
                ->permission('platform.members')
                ->badge(fn () => \App\Models\Member::where('status', 'active')->count(), Color::INFO)
                ->sort(50),

            // System Management
            Menu::make('System')
                ->icon('settings')
                ->permission('platform.settings')
                ->list([
                    Menu::make('Settings')
                        ->icon('equalizer')
                        ->route('platform.settings')
                        ->permission('platform.settings'),

                    Menu::make('Audit Logs')
                        ->icon('list')
                        ->route('platform.audit')
                        ->permission('platform.audit'),

                    Menu::make('Security Events')
                        ->icon('shield')
                        ->route('platform.security')
                        ->permission('platform.security'),
                ])
                ->sort(60),
        ];
    }

    /**
     * Register the application's route model bindings.
     */
    public function registerRouteBindings(): void
    {
        // Story route model binding with enhanced loading
        Route::bind('story', function ($value) {
            return \App\Models\Story::with(['category', 'tags', 'publishingHistory'])
                ->findOrFail($value);
        });

        // Member route model binding
        Route::bind('member', function ($value) {
            return \App\Models\Member::with(['readingStatistics', 'achievements'])
                ->findOrFail($value);
        });

        // Category route model binding
        Route::bind('category', function ($value) {
            return \App\Models\Category::with(['stories'])
                ->findOrFail($value);
        });

        // Tag route model binding
        Route::bind('tag', function ($value) {
            return \App\Models\Tag::with(['stories'])
                ->findOrFail($value);
        });
    }
}
