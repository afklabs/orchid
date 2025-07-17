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

        // Register permissions - Register each permission group separately
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
            ItemPermission::group(__('Member Management'))
                ->addPermission('platform.members', __('View Members'))
                ->addPermission('platform.members.create', __('Create Members'))
                ->addPermission('platform.members.edit', __('Edit Members'))
                ->addPermission('platform.members.delete', __('Delete Members'))
                ->addPermission('platform.members.analytics', __('Member Analytics'))
                ->addPermission('platform.members.import', __('Import Members'))
                ->addPermission('platform.members.export', __('Export Members'))
        );

        $dashboard->registerPermissions(
            ItemPermission::group(__('Analytics & Reporting'))
                ->addPermission('platform.analytics', __('View Analytics'))
                ->addPermission('platform.analytics.stories', __('Story Analytics'))
                ->addPermission('platform.analytics.members', __('Member Analytics'))
                ->addPermission('platform.analytics.reading', __('Reading Analytics'))
                ->addPermission('platform.leaderboard', __('Leaderboard'))
                ->addPermission('platform.achievements', __('Achievements'))
        );

        $dashboard->registerPermissions(
            ItemPermission::group(__('System Management'))
                ->addPermission('platform.settings', __('Settings'))
                ->addPermission('platform.audit', __('Audit Logs'))
                ->addPermission('platform.security', __('Security Events'))
                ->addPermission('platform.system.maintenance', __('System Maintenance'))
        );
    }

    /**
     * Register the application menu.
     */
    public function registerMainMenu(): array
    {
        return [
            // Dashboard
            Menu::make(__('Dashboard'))
                ->icon('monitor')
                ->route('platform.main')
                ->title(__('Navigation')),

            // Content Management
            Menu::make(__('Stories'))
                ->icon('book-open')
                ->route('platform.stories')
                ->permission('platform.stories')
                ->badge(function () {
                    try {
                        return \App\Models\Story::where('active', true)->count();
                    } catch (\Exception $e) {
                        return null;
                    }
                })
                ->sort(100),

            // Member Management
            Menu::make(__('Members'))
                ->icon('users')
                ->permission('platform.members')
                ->list([
                    Menu::make(__('All Members'))
                        ->route('platform.members')
                        ->icon('list')
                        ->permission('platform.members')
                        ->badge(function () {
                            try {
                                return \App\Models\Member::where('status', 'active')->count();
                            } catch (\Exception $e) {
                                return null;
                            }
                        }),

                    Menu::make(__('Add Member'))
                        ->route('platform.members.create')
                        ->icon('user-plus')
                        ->permission('platform.members.create'),

                    Menu::make(__('Import Members'))
                        ->route('platform.members.import.index')
                        ->icon('upload')
                        ->permission('platform.members.import'),

                    Menu::make(__('Member Settings'))
                        ->route('platform.settings.members.index')
                        ->icon('settings')
                        ->permission('platform.settings'),
                ])
                ->sort(200),

            // Analytics
            Menu::make(__('Analytics'))
                ->icon('chart-line')
                ->permission('platform.analytics')
                ->list([
                    Menu::make(__('Reading Dashboard'))
                        ->route('platform.analytics.reading.dashboard')
                        ->icon('chart-bar')
                        ->permission('platform.analytics.reading'),

                    Menu::make(__('Story Analytics'))
                        ->route('platform.analytics.stories')
                        ->icon('book')
                        ->permission('platform.analytics.stories'),

                    Menu::make(__('Member Analytics'))
                        ->route('platform.analytics.members.dashboard')
                        ->icon('users')
                        ->permission('platform.analytics.members'),

                    Menu::make(__('Member Segments'))
                        ->route('platform.analytics.members.segments')
                        ->icon('pie-chart')
                        ->permission('platform.analytics.members'),

                    Menu::make(__('Cohort Analysis'))
                        ->route('platform.analytics.members.cohorts')
                        ->icon('trending-up')
                        ->permission('platform.analytics.members'),

                    Menu::make(__('Leaderboard'))
                        ->route('platform.leaderboard')
                        ->icon('trophy')
                        ->permission('platform.leaderboard'),
                ])
                ->sort(300),

            // Content Categories
            Menu::make(__('Content'))
                ->icon('layers')
                ->permission('platform.categories')
                ->list([
                    Menu::make(__('Categories'))
                        ->route('platform.categories')
                        ->icon('folder')
                        ->permission('platform.categories'),

                    Menu::make(__('Tags'))
                        ->route('platform.tags')
                        ->icon('tag')
                        ->permission('platform.tags'),
                ])
                ->sort(400),

            // System Management
            Menu::make(__('System'))
                ->icon('settings')
                ->permission('platform.settings')
                ->list([
                    Menu::make(__('General Settings'))
                        ->route('platform.settings')
                        ->icon('settings')
                        ->permission('platform.settings'),

                    Menu::make(__('Audit Logs'))
                        ->route('platform.audit')
                        ->icon('shield')
                        ->permission('platform.audit'),

                    Menu::make(__('Security Events'))
                        ->route('platform.security')
                        ->icon('lock')
                        ->permission('platform.security'),

                    Menu::make(__('System Health'))
                        ->route('platform.system.health')
                        ->icon('heart')
                        ->permission('platform.system.maintenance'),
                ])
                ->sort(500),

            // User Management (Admin Users)
            Menu::make(__('User Management'))
                ->icon('user')
                ->permission('platform.systems.users')
                ->list([
                    Menu::make(__('Users'))
                        ->route('platform.systems.users')
                        ->icon('user')
                        ->permission('platform.systems.users'),

                    Menu::make(__('Roles'))
                        ->route('platform.systems.roles')
                        ->icon('shield')
                        ->permission('platform.systems.roles'),
                ])
                ->title(__('Access Controls'))
                ->sort(1000),
        ];
    }

    /**
     * Register profile menu items
     */
    public function registerProfileMenu(): array
    {
        return [
            Menu::make(__('Profile'))
                ->route('platform.profile')
                ->icon('user'),
        ];
    }

    /**
     * Register system menu items (only visible to super admins)
     */
    public function registerSystemMenu(): array
    {
        return [
            Menu::make(__('Systems'))
                ->icon('monitor')
                ->permission('platform.systems')
                ->list([
                    Menu::make(__('Users'))
                        ->route('platform.systems.users')
                        ->permission('platform.systems.users')
                        ->icon('user'),

                    Menu::make(__('Roles'))
                        ->route('platform.systems.roles')
                        ->permission('platform.systems.roles')
                        ->icon('shield'),
                ]),
        ];
    }
}