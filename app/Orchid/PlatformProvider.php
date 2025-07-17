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

        // Register permissions - FIX: Register each permission group separately
        $dashboard->registerPermissions(
            ItemPermission::group(__('Content Management'))
                ->addPermission('platform.stories', __('Stories'))
                ->addPermission('platform.stories.create', __('Create Stories'))
                ->addPermission('platform.stories.edit', __('Edit Stories'))
                ->addPermission('platform.stories.delete', __('Delete Stories'))
                ->addPermission('platform.stories.publish', __('Publish Stories'))
                ->addPermission('platform.categories', __('Categories'))
                ->addPermission('platform.tags', __('Tags'))
        );

        $dashboard->registerPermissions(
            ItemPermission::group(__('Analytics & Reporting'))
                ->addPermission('platform.analytics', __('View Analytics'))
                ->addPermission('platform.analytics.stories', __('Story Analytics'))
                ->addPermission('platform.analytics.members', __('Member Analytics'))
                ->addPermission('platform.leaderboard', __('Leaderboard'))
                ->addPermission('platform.achievements', __('Achievements'))
        );

        $dashboard->registerPermissions(
            ItemPermission::group(__('Member Management'))
                ->addPermission('platform.members', __('Members'))
                ->addPermission('platform.members.create', __('Create Members'))
                ->addPermission('platform.members.edit', __('Edit Members'))
                ->addPermission('platform.members.delete', __('Delete Members'))
                ->addPermission('platform.members.analytics', __('Member Analytics'))
        );

        $dashboard->registerPermissions(
            ItemPermission::group(__('System Management'))
                ->addPermission('platform.settings', __('Settings'))
                ->addPermission('platform.audit', __('Audit Logs'))
                ->addPermission('platform.security', __('Security Events'))
        );
    }

    /**
     * Register the application menu.
     */
    public function registerMainMenu(): array
    {
        return [
            Menu::make('Stories')
                ->icon('book-open')
                ->route('platform.stories')
                ->permission('platform.stories')
                ->badge(function () {
                    return \App\Models\Story::where('active', true)->count();
                }),

            Menu::make('Analytics')
                ->icon('chart-line')
                ->list([
                    Menu::make('Story Analytics')
                        ->route('platform.analytics.stories')
                        ->permission('platform.analytics.stories'),
                    Menu::make('Member Analytics')
                        ->route('platform.analytics.members')
                        ->permission('platform.analytics.members'),
                    Menu::make('Leaderboard')
                        ->route('platform.leaderboard')
                        ->permission('platform.leaderboard'),
                ]),

            Menu::make('Content')
                ->icon('layers')
                ->list([
                    Menu::make('Categories')
                        ->route('platform.categories')
                        ->permission('platform.categories'),
                    Menu::make('Tags')
                        ->route('platform.tags')
                        ->permission('platform.tags'),
                ]),

            Menu::make('Members')
                ->icon('users')
                ->route('platform.members')
                ->permission('platform.members'),

            Menu::make('Settings')
                ->icon('settings')
                ->route('platform.settings')
                ->permission('platform.settings'),
        ];
    }
}