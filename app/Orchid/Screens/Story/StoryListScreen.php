<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Story;

use App\Models\{Story, Category, Tag};
use App\Services\{WordCountService, ReadingAnalyticsService};
use Orchid\Screen\{Screen, Actions\Button, Actions\Link, Actions\DropDown};
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\Fields\{Select, Input, Group, CheckBox};
use Orchid\Screen\TD;
use Orchid\Support\Facades\{Layout, Toast};
use Orchid\Support\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Enhanced Story List Screen with Word Count Analytics
 * 
 * Features:
 * - Word count display in table
 * - Reading level indicators
 * - Performance metrics
 * - Enhanced table layout
 * - Quick actions
 * - Advanced filters
 */
class StoryListScreen extends Screen
{
    /**
     * Query data for the screen.
     */
    public function query(): array
    {
        $stories = Story::with(['category', 'tags'])
            ->filters()
            ->when(request('filter.reading_level'), function ($query, $level) {
                $query->where('reading_level', $level);
            })
            ->when(request('filter.word_count_min'), function ($query, $min) {
                $query->where('word_count', '>=', $min);
            })
            ->when(request('filter.word_count_max'), function ($query, $max) {
                $query->where('word_count', '<=', $max);
            })
            ->when(request('filter.category_id'), function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when(request('filter.status'), function ($query, $status) {
                if ($status === 'active') {
                    $query->where('active', true);
                } elseif ($status === 'inactive') {
                    $query->where('active', false);
                }
            })
            ->when(request('filter.views_min'), function ($query, $min) {
                $query->where('views', '>=', $min);
            })
            ->when(request('filter.created_from'), function ($query, $from) {
                $query->where('created_at', '>=', $from);
            })
            ->when(request('filter.created_to'), function ($query, $to) {
                $query->where('created_at', '<=', $to);
            })
            ->defaultSort('created_at', 'desc')
            ->paginate(25);

        return [
            'stories' => $stories,
            'categories' => Category::pluck('name', 'id'),
            'totalStories' => Story::count(),
            'activeStories' => Story::where('active', true)->count(),
            'totalWords' => Story::sum('word_count'),
            'avgWordCount' => round(Story::avg('word_count'), 0),
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Stories Management';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage all stories with word count analytics and reading level tracking';
    }

    /**
     * Button commands.
     */
    public function commandBar(): array
    {
        return [
            Link::make('Create Story')
                ->route('platform.stories.create')
                ->icon('plus')
                ->class('btn btn-primary'),

            // Bulk Actions
            Button::make('Bulk Publish')
                ->method('bulkPublish')
                ->icon('check')
                ->class('btn btn-success')
                ->confirm('Are you sure you want to publish selected stories?'),

            Button::make('Bulk Unpublish')
                ->method('bulkUnpublish')
                ->icon('pause')
                ->class('btn btn-warning')
                ->confirm('Are you sure you want to unpublish selected stories?'),

            Button::make('Bulk Delete')
                ->method('bulkDelete')
                ->icon('trash')
                ->class('btn btn-danger')
                ->confirm('Are you sure you want to delete selected stories? This action cannot be undone.'),

            Button::make('Analytics Dashboard')
                ->method('goToAnalytics')
                ->icon('graph')
                ->class('btn btn-outline-info'),

            Button::make('Export Stories')
                ->method('exportStories')
                ->icon('cloud-download')
                ->class('btn btn-outline-secondary'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            // Statistics Cards
            Layout::metrics([
                'Total Stories' => 'totalStories',
                'Active Stories' => 'activeStories', 
                'Total Words' => 'totalWords',
                'Avg Words/Story' => 'avgWordCount',
            ]),

            // Advanced Filters
            Layout::rows([
                Group::make([
                    Select::make('filter.reading_level')
                        ->title('Reading Level')
                        ->options([
                            'beginner' => 'Beginner (≤500 words)',
                            'intermediate' => 'Intermediate (501-1500 words)',
                            'advanced' => 'Advanced (>1500 words)',
                        ])
                        ->empty('All Levels')
                        ->value(request('filter.reading_level')),

                    Select::make('filter.category_id')
                        ->title('Category')
                        ->fromQuery(Category::class, 'name')
                        ->empty('All Categories')
                        ->value(request('filter.category_id')),

                    Select::make('filter.status')
                        ->title('Status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                        ])
                        ->empty('All Status')
                        ->value(request('filter.status')),
                ])->autoWidth(),

                Group::make([
                    Input::make('filter.word_count_min')
                        ->title('Min Word Count')
                        ->type('number')
                        ->placeholder('e.g., 100')
                        ->value(request('filter.word_count_min')),

                    Input::make('filter.word_count_max')
                        ->title('Max Word Count')
                        ->type('number')
                        ->placeholder('e.g., 2000')
                        ->value(request('filter.word_count_max')),

                    Input::make('filter.views_min')
                        ->title('Min Views')
                        ->type('number')
                        ->placeholder('e.g., 50')
                        ->value(request('filter.views_min')),
                ])->autoWidth(),

                Group::make([
                    Input::make('filter.created_from')
                        ->title('Created From')
                        ->type('date')
                        ->value(request('filter.created_from')),

                    Input::make('filter.created_to')
                        ->title('Created To')
                        ->type('date')
                        ->value(request('filter.created_to')),

                    Button::make('Apply Filters')
                        ->method('applyFilters')
                        ->icon('filter')
                        ->class('btn btn-primary'),

                    Button::make('Clear Filters')
                        ->method('clearFilters')
                        ->icon('refresh')
                        ->class('btn btn-outline-secondary'),
                ])->autoWidth(),

                // Bulk Actions Row
                Group::make([
                    Select::make('bulk_action')
                        ->title('Bulk Actions')
                        ->options([
                            'publish' => 'Publish Selected',
                            'unpublish' => 'Unpublish Selected',
                            'delete' => 'Delete Selected',
                            'change_category' => 'Change Category',
                            'change_level' => 'Change Reading Level',
                        ])
                        ->empty('Select Action'),

                    Select::make('bulk_category_id')
                        ->title('New Category')
                        ->fromQuery(Category::class, 'name')
                        ->empty('Select Category')
                        ->help('For category change action'),

                    Select::make('bulk_reading_level')
                        ->title('New Reading Level')
                        ->options([
                            'beginner' => 'Beginner',
                            'intermediate' => 'Intermediate',
                            'advanced' => 'Advanced',
                        ])
                        ->empty('Select Level')
                        ->help('For reading level change action'),

                    Button::make('Execute Bulk Action')
                        ->method('executeBulkAction')
                        ->icon('play')
                        ->class('btn btn-primary')
                        ->confirm('Are you sure you want to execute this bulk action?'),
                ])->autoWidth(),
            ])->title('Advanced Filters'),

            // Enhanced Stories Table
            Layout::table('stories', [
                TD::make('bulk_select', '')
                    ->width('50px')
                    ->render(function (Story $story) {
                        return CheckBox::make('bulk_stories[]')
                            ->value($story->id)
                            ->placeholder('')
                            ->class('bulk-checkbox');
                    }),

                TD::make('image', 'Image')
                    ->width('60px')
                    ->render(function (Story $story) {
                        return $story->image 
                            ? "<img src='{$story->image}' style='width: 40px; height: 40px; border-radius: 4px; object-fit: cover;'>"
                            : "<div style='width: 40px; height: 40px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;'><i class='icon-book'></i></div>";
                    }),

                TD::make('title', 'Title')
                    ->sort()
                    ->filter()
                    ->render(function (Story $story) {
                        $title = "<strong>{$story->title}</strong>";
                        
                        if ($story->excerpt) {
                            $excerpt = Str::limit($story->excerpt, 60);
                            $title .= "<br><small class='text-muted'>{$excerpt}</small>";
                        }
                        
                        return $title;
                    }),

                TD::make('category.name', 'Category')
                    ->sort()
                    ->filter()
                    ->render(function (Story $story) {
                        return $story->category 
                            ? "<span class='badge badge-info'>{$story->category->name}</span>"
                            : "<span class='badge badge-secondary'>No Category</span>";
                    }),

                TD::make('word_count', 'Word Count')
                    ->sort()
                    ->align(TD::ALIGN_CENTER)
                    ->render(function (Story $story) {
                        $wordCount = number_format($story->word_count);
                        $color = $this->getWordCountColor($story->word_count);
                        
                        return "<span class='badge badge-{$color}'>{$wordCount}</span>";
                    }),

                TD::make('reading_level', 'Reading Level')
                    ->sort()
                    ->filter()
                    ->align(TD::ALIGN_CENTER)
                    ->render(function (Story $story) {
                        $level = ucfirst($story->reading_level);
                        $color = $this->getReadingLevelColor($story->reading_level);
                        
                        return "<span class='badge badge-{$color}'>{$level}</span>";
                    }),

                TD::make('reading_time_minutes', 'Reading Time')
                    ->sort()
                    ->align(TD::ALIGN_CENTER)
                    ->render(function (Story $story) {
                        return $story->reading_time_minutes . ' min';
                    }),

                TD::make('views', 'Views')
                    ->sort()
                    ->align(TD::ALIGN_CENTER)
                    ->render(function (Story $story) {
                        return number_format($story->views);
                    }),

                TD::make('active', 'Status')
                    ->sort()
                    ->filter()
                    ->align(TD::ALIGN_CENTER)
                    ->render(function (Story $story) {
                        if ($story->active) {
                            $status = "<span class='badge badge-success'>Active</span>";
                            
                            if ($story->active_from && $story->active_from->isFuture()) {
                                $status .= "<br><small class='text-info'>Scheduled: {$story->active_from->format('M j, Y')}</small>";
                            }
                            
                            if ($story->active_until && $story->active_until->isPast()) {
                                $status = "<span class='badge badge-warning'>Expired</span>";
                            }
                        } else {
                            $status = "<span class='badge badge-secondary'>Inactive</span>";
                        }
                        
                        return $status;
                    }),

                TD::make('tags', 'Tags')
                    ->width('120px')
                    ->render(function (Story $story) {
                        if ($story->tags->isEmpty()) {
                            return '<span class="text-muted">No tags</span>';
                        }
                        
                        $tags = $story->tags->take(2)->map(function ($tag) {
                            return "<span class='badge badge-light'>{$tag->name}</span>";
                        })->implode(' ');
                        
                        if ($story->tags->count() > 2) {
                            $remaining = $story->tags->count() - 2;
                            $tags .= " <span class='badge badge-light'>+{$remaining}</span>";
                        }
                        
                        return $tags;
                    }),

                TD::make('created_at', 'Created')
                    ->sort()
                    ->render(function (Story $story) {
                        return $story->created_at->format('M j, Y') . '<br>' . 
                               '<small class="text-muted">' . $story->created_at->diffForHumans() . '</small>';
                    }),

                TD::make('actions', 'Actions')
                    ->align(TD::ALIGN_CENTER)
                    ->width('120px')
                    ->render(function (Story $story) {
                        return DropDown::make()
                            ->icon('options-vertical')
                            ->list([
                                Link::make('Edit')
                                    ->route('platform.stories.edit', $story->id)
                                    ->icon('pencil'),
                                
                                Link::make('View')
                                    ->href(route('stories.show', $story->id))
                                    ->icon('eye')
                                    ->target('_blank'),
                                
                                Link::make('Analytics')
                                    ->route('platform.stories.analytics', $story->id)
                                    ->icon('graph'),
                                
                                Button::make('Duplicate')
                                    ->method('duplicateStory')
                                    ->parameters(['story' => $story->id])
                                    ->icon('docs'),
                                
                                $story->active
                                    ? Button::make('Unpublish')
                                        ->method('unpublishStory')
                                        ->parameters(['story' => $story->id])
                                        ->icon('pause')
                                        ->confirm('Are you sure you want to unpublish this story?')
                                    : Button::make('Publish')
                                        ->method('publishStory')
                                        ->parameters(['story' => $story->id])
                                        ->icon('check')
                                        ->confirm('Are you sure you want to publish this story?'),
                                
                                Button::make('Delete')
                                    ->method('deleteStory')
                                    ->parameters(['story' => $story->id])
                                    ->icon('trash')
                                    ->confirm('Are you sure you want to delete this story? This action cannot be undone.')
                                    ->class('text-danger'),
                            ]);
                    }),
            ])
            ->title('Stories')
            ->description('All stories with word count analytics and performance metrics')
            ->striped()
            ->bordered(),

            // Bulk Actions JavaScript
            Layout::view('orchid.story.bulk-actions-script'),
        ];
    }

    /**
     * Get word count color based on count.
     */
    private function getWordCountColor(int $wordCount): string
    {
        if ($wordCount <= 500) {
            return 'success';
        } elseif ($wordCount <= 1500) {
            return 'info';
        } else {
            return 'warning';
        }
    }

    /**
     * Get reading level color.
     */
    private function getReadingLevelColor(string $level): string
    {
        return match ($level) {
            'beginner' => 'success',
            'intermediate' => 'info',
            'advanced' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Publish story.
     */
    public function publishStory(Request $request): void
    {
        $story = Story::findOrFail($request->get('story'));
        $story->update(['active' => true, 'active_from' => now()]);
        
        Toast::success('Story published successfully!');
    }

    /**
     * Unpublish story.
     */
    public function unpublishStory(Request $request): void
    {
        $story = Story::findOrFail($request->get('story'));
        $story->update(['active' => false, 'active_until' => now()]);
        
        Toast::success('Story unpublished successfully!');
    }

    /**
     * Duplicate story.
     */
    public function duplicateStory(Request $request): void
    {
        $story = Story::findOrFail($request->get('story'));
        $duplicatedStory = $story->replicate();
        $duplicatedStory->title = $story->title . ' (Copy)';
        $duplicatedStory->active = false;
        $duplicatedStory->views = 0;
        $duplicatedStory->save();
        
        // Copy tags
        $duplicatedStory->tags()->sync($story->tags->pluck('id'));
        
        Toast::success('Story duplicated successfully!');
    }

    /**
     * Delete story.
     */
    public function deleteStory(Request $request): void
    {
        $story = Story::findOrFail($request->get('story'));
        
        // Delete related data
        $story->tags()->detach();
        $story->delete();
        
        Toast::success('Story deleted successfully!');
    }

    /**
     * Apply filters method.
     */
    public function applyFilters(Request $request): void
    {
        Toast::info('Filters applied successfully!');
    }

    /**
     * Clear all filters.
     */
    public function clearFilters()
    {
        return redirect()->route('platform.stories');
    }

    /**
     * Go to analytics dashboard.
     */
    public function goToAnalytics()
    {
        return redirect()->route('platform.analytics.dashboard');
    }

    /**
     * Export stories.
     */
    public function exportStories(): void
    {
        Toast::info('Export functionality will be implemented in the next phase.');
    }

    /*
    |--------------------------------------------------------------------------
    | BULK ACTIONS METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Execute bulk action on selected stories.
     */
    public function executeBulkAction(Request $request): void
    {
        $selectedStories = $request->get('bulk_stories', []);
        $action = $request->get('bulk_action');

        if (empty($selectedStories)) {
            Toast::error('Please select at least one story.');
            return;
        }

        if (empty($action)) {
            Toast::error('Please select an action.');
            return;
        }

        try {
            DB::beginTransaction();

            $count = 0;
            switch ($action) {
                case 'publish':
                    $count = $this->bulkPublishStories($selectedStories);
                    break;
                case 'unpublish':
                    $count = $this->bulkUnpublishStories($selectedStories);
                    break;
                case 'delete':
                    $count = $this->bulkDeleteStories($selectedStories);
                    break;
                case 'change_category':
                    $count = $this->bulkChangeCategoryStories($selectedStories, $request->get('bulk_category_id'));
                    break;
                case 'change_level':
                    $count = $this->bulkChangeReadingLevelStories($selectedStories, $request->get('bulk_reading_level'));
                    break;
            }

            DB::commit();
            Toast::success("Bulk action completed successfully! {$count} stories processed.");

        } catch (\Exception $e) {
            DB::rollBack();
            Toast::error('Error executing bulk action: ' . $e->getMessage());
        }
    }

    /**
     * Bulk publish stories.
     */
    public function bulkPublish(Request $request): void
    {
        $selectedStories = $request->get('bulk_stories', []);
        
        if (empty($selectedStories)) {
            Toast::error('Please select stories to publish.');
            return;
        }

        try {
            $count = Story::whereIn('id', $selectedStories)
                ->update([
                    'active' => true,
                    'active_from' => now(),
                ]);

            Toast::success("Successfully published {$count} stories!");
        } catch (\Exception $e) {
            Toast::error('Error publishing stories: ' . $e->getMessage());
        }
    }

    /**
     * Bulk unpublish stories.
     */
    public function bulkUnpublish(Request $request): void
    {
        $selectedStories = $request->get('bulk_stories', []);
        
        if (empty($selectedStories)) {
            Toast::error('Please select stories to unpublish.');
            return;
        }

        try {
            $count = Story::whereIn('id', $selectedStories)
                ->update([
                    'active' => false,
                    'active_until' => now(),
                ]);

            Toast::success("Successfully unpublished {$count} stories!");
        } catch (\Exception $e) {
            Toast::error('Error unpublishing stories: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete stories.
     */
    public function bulkDelete(Request $request): void
    {
        $selectedStories = $request->get('bulk_stories', []);
        
        if (empty($selectedStories)) {
            Toast::error('Please select stories to delete.');
            return;
        }

        try {
            DB::beginTransaction();

            // Delete related data first
            $stories = Story::whereIn('id', $selectedStories)->get();
            
            foreach ($stories as $story) {
                $story->tags()->detach();
            }

            $count = Story::whereIn('id', $selectedStories)->delete();

            DB::commit();
            Toast::success("Successfully deleted {$count} stories!");
        } catch (\Exception $e) {
            DB::rollBack();
            Toast::error('Error deleting stories: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE BULK ACTION METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Bulk publish stories.
     */
    private function bulkPublishStories(array $storyIds): int
    {
        return Story::whereIn('id', $storyIds)
            ->update([
                'active' => true,
                'active_from' => now(),
            ]);
    }

    /**
     * Bulk unpublish stories.
     */
    private function bulkUnpublishStories(array $storyIds): int
    {
        return Story::whereIn('id', $storyIds)
            ->update([
                'active' => false,
                'active_until' => now(),
            ]);
    }

    /**
     * Bulk delete stories.
     */
    private function bulkDeleteStories(array $storyIds): int
    {
        // Delete related data first
        $stories = Story::whereIn('id', $storyIds)->get();
        
        foreach ($stories as $story) {
            $story->tags()->detach();
        }

        return Story::whereIn('id', $storyIds)->delete();
    }

    /**
     * Bulk change category for stories.
     */
    private function bulkChangeCategoryStories(array $storyIds, ?int $categoryId): int
    {
        if (!$categoryId) {
            throw new \InvalidArgumentException('Category ID is required');
        }

        return Story::whereIn('id', $storyIds)
            ->update(['category_id' => $categoryId]);
    }

    /**
     * Bulk change reading level for stories.
     */
    private function bulkChangeReadingLevelStories(array $storyIds, ?string $readingLevel): int
    {
        if (!$readingLevel) {
            throw new \InvalidArgumentException('Reading level is required');
        }

        return Story::whereIn('id', $storyIds)
            ->update(['reading_level' => $readingLevel]);
    }
}