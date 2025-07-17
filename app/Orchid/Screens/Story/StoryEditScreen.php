<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Story;

use App\Models\{Story, Category, Tag, StoryPublishingHistory};
use App\Services\{ReadingAnalyticsService, WordCountService};
use Orchid\Screen\{Screen, Actions\Button, Actions\Link, Actions\DropDown};
use Orchid\Screen\Fields\{
    Input, 
    TextArea, 
    Select, 
    Upload, 
    CheckBox, 
    DateTimer, 
    Quill, 
    Relation,
    Group,
    Matrix
};
use Orchid\Support\Facades\{Layout, Toast, Alert};
use Orchid\Support\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Log, Storage};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Enhanced Story Edit Screen with Word Count Calculator
 * 
 * Advanced story editing interface with real-time word count analysis,
 * reading level detection, analytics integration, and performance optimization.
 * 
 * Features:
 * - Real-time word count calculator
 * - Automatic reading level detection
 * - Publishing workflow management
 * - Analytics tracking integration
 * - Performance metrics display
 * - Publishing history management
 * - SEO optimization tools
 * - Content validation
 * 
 * @package App\Orchid\Screens\Story
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class StoryEditScreen extends Screen
{
    /**
     * Story instance being edited.
     */
    public ?Story $story = null;

    /**
     * Word count service instance.
     */
    private WordCountService $wordCountService;

    /**
     * Analytics service instance.
     */
    private ReadingAnalyticsService $analyticsService;

    /**
     * Constructor.
     */
    public function __construct(
        WordCountService $wordCountService,
        ReadingAnalyticsService $analyticsService
    ) {
        $this->wordCountService = $wordCountService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Query data for the screen.
     */
    public function query(Story $story): array
    {
        $this->story = $story;

        return [
            'story' => $story->load(['category', 'tags', 'publishingHistory']),
            'categories' => Category::active()->pluck('name', 'id'),
            'tags' => Tag::pluck('name', 'id'),
            'wordCountData' => $this->getWordCountData($story),
            'analyticsData' => $this->getAnalyticsData($story),
            'publishingData' => $this->getPublishingData($story),
            'performanceMetrics' => $this->getPerformanceMetrics($story),
            'seoData' => $this->getSeoData($story),
            'validationRules' => $this->getValidationRules(),
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return $this->story->exists 
            ? 'Edit Story: ' . $this->story->title
            : 'Create New Story';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        if (!$this->story->exists) {
            return 'Create a new story with advanced word count analytics and publishing workflow';
        }

        $wordCount = number_format($this->story->word_count);
        $readingLevel = ucfirst($this->story->reading_level);
        $readingTime = $this->story->reading_time_minutes;

        return "Word Count: {$wordCount} | Reading Level: {$readingLevel} | Reading Time: {$readingTime} min";
    }

    /**
     * Button commands.
     */
    public function commandBar(): array
    {
        $buttons = [];

        if ($this->story->exists) {
            // Preview button
            $buttons[] = Link::make('Preview')
                ->href(route('stories.show', $this->story->id))
                ->icon('eye')
                ->target('_blank')
                ->class('btn btn-outline-primary');

            // Publishing actions
            if (!$this->story->active) {
                $buttons[] = Button::make('Publish Now')
                    ->method('publishStory')
                    ->icon('check')
                    ->class('btn btn-success')
                    ->confirm('Are you sure you want to publish this story?');
            } else {
                $buttons[] = Button::make('Unpublish')
                    ->method('unpublishStory')
                    ->icon('pause')
                    ->class('btn btn-warning')
                    ->confirm('Are you sure you want to unpublish this story?');
            }

            // Analytics button
            $buttons[] = Link::make('Analytics')
                ->route('platform.stories.analytics', $this->story->id)
                ->icon('graph')
                ->class('btn btn-outline-info');

            // Duplicate button
            $buttons[] = Button::make('Duplicate')
                ->method('duplicateStory')
                ->icon('docs')
                ->class('btn btn-outline-secondary');

            // Delete button
            $buttons[] = Button::make('Delete')
                ->method('deleteStory')
                ->icon('trash')
                ->class('btn btn-outline-danger')
                ->confirm('Are you sure you want to delete this story? This action cannot be undone.');
        }

        // Save button
        $buttons[] = Button::make('Save Story')
            ->method('saveStory')
            ->icon('check')
            ->class('btn btn-primary');

        return $buttons;
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            // Main story editing form
            Layout::block([
                Layout::rows([
                    // Basic Information Section
                    Layout::columns([
                        Layout::rows([
                            Input::make('story.title')
                                ->title('Story Title')
                                ->placeholder('Enter story title')
                                ->required()
                                ->help('Clear, engaging title that describes your story')
                                ->maxlength(255),

                            TextArea::make('story.summary')
                                ->title('Story Summary')
                                ->placeholder('Brief description of the story')
                                ->rows(3)
                                ->maxlength(500)
                                ->help('Short description for preview and SEO'),
                        ])->width('8'),

                        Layout::rows([
                            Upload::make('story.image')
                                ->title('Featured Image')
                                ->acceptedFiles('image/*')
                                ->storage('public')
                                ->path('stories')
                                ->help('Recommended: 1200x630px for best display'),

                            Select::make('story.category_id')
                                ->title('Category')
                                ->options('categories')
                                ->required()
                                ->help('Choose the most relevant category'),

                            Relation::make('story.tags')
                                ->title('Tags')
                                ->fromModel(Tag::class, 'name')
                                ->multiple()
                                ->help('Add relevant tags to improve discoverability'),
                        ])->width('4'),
                    ]),

                    // Word Count Analytics Section
                    Layout::view('orchid.story.word-count-analytics', [
                        'wordCountData' => 'wordCountData',
                        'story' => 'story',
                    ]),

                    // Content Editor with Real-time Analysis
                    Layout::rows([
                        Quill::make('story.content')
                            ->title('Story Content')
                            ->toolbar([
                                'bold', 'italic', 'underline', 'strike',
                                'blockquote', 'code-block',
                                'header', 'list', 'indent',
                                'link', 'image', 'video',
                                'clean'
                            ])
                            ->help('Write your story content here. Word count and reading level will be calculated automatically.')
                            ->required(),
                    ]),

                    // Publishing Settings
                    Layout::accordion([
                        'Publishing Settings' => Layout::rows([
                            Layout::columns([
                                Layout::rows([
                                    CheckBox::make('story.active')
                                        ->title('Active')
                                        ->placeholder('Story is published and visible to users')
                                        ->sendTrueOrFalse(),

                                    DateTimer::make('story.active_from')
                                        ->title('Active From')
                                        ->format('Y-m-d H:i:s')
                                        ->help('When should this story become active?'),

                                    DateTimer::make('story.active_until')
                                        ->title('Active Until')
                                        ->format('Y-m-d H:i:s')
                                        ->help('When should this story become inactive? (Optional)'),
                                ])->width('6'),

                                Layout::rows([
                                    Input::make('story.reading_time_minutes')
                                        ->title('Reading Time (minutes)')
                                        ->type('number')
                                        ->min(1)
                                        ->max(120)
                                        ->help('Estimated reading time for average reader'),

                                    Select::make('story.reading_level')
                                        ->title('Reading Level')
                                        ->options([
                                            'beginner' => 'Beginner (≤500 words)',
                                            'intermediate' => 'Intermediate (501-1500 words)',
                                            'advanced' => 'Advanced (>1500 words)',
                                        ])
                                        ->help('Will be auto-calculated based on word count'),

                                    CheckBox::make('story.featured')
                                        ->title('Featured Story')
                                        ->placeholder('Highlight this story in featured sections')
                                        ->sendTrueOrFalse(),
                                ])->width('6'),
                            ]),
                        ]),

                        'SEO Settings' => Layout::rows([
                            Input::make('story.seo_title')
                                ->title('SEO Title')
                                ->placeholder('Custom title for search engines')
                                ->maxlength(60)
                                ->help('Leave blank to use story title'),

                            TextArea::make('story.seo_description')
                                ->title('SEO Description')
                                ->placeholder('Description for search engines')
                                ->rows(3)
                                ->maxlength(160)
                                ->help('Leave blank to use story summary'),

                            Input::make('story.seo_keywords')
                                ->title('SEO Keywords')
                                ->placeholder('keyword1, keyword2, keyword3')
                                ->help('Comma-separated keywords for SEO'),
                        ]),

                        'Advanced Settings' => Layout::rows([
                            CheckBox::make('story.allow_comments')
                                ->title('Allow Comments')
                                ->placeholder('Enable comments for this story')
                                ->sendTrueOrFalse(),

                            CheckBox::make('story.send_notification')
                                ->title('Send Notification')
                                ->placeholder('Send push notification when published')
                                ->sendTrueOrFalse(),

                            Input::make('story.sort_order')
                                ->title('Sort Order')
                                ->type('number')
                                ->help('Lower numbers appear first in listings'),
                        ]),
                    ]),
                ])
            ])
            ->title('Story Details')
            ->description('Edit story information and content'),

            // Analytics & Performance Section (only for existing stories)
            $this->story->exists ? Layout::block([
                Layout::tabs([
                    'Analytics' => Layout::view('orchid.story.analytics', [
                        'analyticsData' => 'analyticsData',
                        'story' => 'story',
                    ]),

                    'Performance' => Layout::view('orchid.story.performance', [
                        'performanceMetrics' => 'performanceMetrics',
                        'story' => 'story',
                    ]),

                    'Publishing History' => Layout::view('orchid.story.publishing-history', [
                        'publishingData' => 'publishingData',
                        'story' => 'story',
                    ]),
                ])
            ])
            ->title('Analytics & Performance')
            ->description('Story performance metrics and publishing history') : null,
        ];
    }

    /**
     * Save story with enhanced validation and analytics.
     */
    public function saveStory(Request $request): void
    {
        try {
            DB::beginTransaction();

            // Validate request
            $validated = $request->validate([
                'story.title' => 'required|string|max:255',
                'story.summary' => 'nullable|string|max:500',
                'story.content' => 'required|string',
                'story.category_id' => 'required|exists:categories,id',
                'story.tags' => 'nullable|array',
                'story.tags.*' => 'exists:tags,id',
                'story.image' => 'nullable|string',
                'story.active' => 'boolean',
                'story.active_from' => 'nullable|date',
                'story.active_until' => 'nullable|date|after:active_from',
                'story.reading_time_minutes' => 'nullable|integer|min:1|max:120',
                'story.reading_level' => 'nullable|in:beginner,intermediate,advanced',
                'story.featured' => 'boolean',
                'story.seo_title' => 'nullable|string|max:60',
                'story.seo_description' => 'nullable|string|max:160',
                'story.seo_keywords' => 'nullable|string|max:255',
                'story.allow_comments' => 'boolean',
                'story.send_notification' => 'boolean',
                'story.sort_order' => 'nullable|integer',
            ]);

            $storyData = $validated['story'];
            $originalActive = $this->story->active ?? false;

            // Calculate word count and reading level
            $wordCountData = $this->wordCountService->analyzeContent($storyData['content']);
            $storyData['word_count'] = $wordCountData['word_count'];
            $storyData['reading_level'] = $wordCountData['reading_level'];

            // Auto-calculate reading time if not provided
            if (empty($storyData['reading_time_minutes'])) {
                $storyData['reading_time_minutes'] = $wordCountData['estimated_reading_time'];
            }

            // Handle story creation/update
            if ($this->story->exists) {
                $this->story->update($storyData);
            } else {
                $this->story = Story::create($storyData);
            }

            // Handle tags
            if (isset($validated['story']['tags'])) {
                $this->story->tags()->sync($validated['story']['tags']);
            }

            // Handle publishing history
            if ($originalActive !== $this->story->active) {
                $this->createPublishingHistory($originalActive, $this->story->active);
            }

            // Send notification if requested
            if ($storyData['send_notification'] ?? false && $this->story->active) {
                $this->sendPublishingNotification();
            }

            // Clear related caches
            $this->clearStoryRelatedCaches();

            DB::commit();

            Toast::success('Story saved successfully!');
            
            // Update analytics in background
            $this->updateStoryAnalytics();

        } catch (ValidationException $e) {
            DB::rollBack();
            Toast::error('Please fix the validation errors and try again.');
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving story: ' . $e->getMessage(), [
                'story_id' => $this->story->id ?? null,
                'user_id' => auth()->id(),
                'error' => $e->getTraceAsString(),
            ]);
            Toast::error('An error occurred while saving the story. Please try again.');
            throw $e;
        }
    }

    /**
     * Publish story immediately.
     */
    public function publishStory(): void
    {
        try {
            DB::beginTransaction();

            $originalActive = $this->story->active;
            
            $this->story->update([
                'active' => true,
                'active_from' => now(),
            ]);

            // Create publishing history
            $this->createPublishingHistory($originalActive, true);

            // Send notification
            $this->sendPublishingNotification();

            // Clear caches
            $this->clearStoryRelatedCaches();

            DB::commit();

            Toast::success('Story published successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error publishing story: ' . $e->getMessage());
            Toast::error('Failed to publish story. Please try again.');
        }
    }

    /**
     * Unpublish story.
     */
    public function unpublishStory(): void
    {
        try {
            DB::beginTransaction();

            $originalActive = $this->story->active;
            
            $this->story->update([
                'active' => false,
                'active_until' => now(),
            ]);

            // Create publishing history
            $this->createPublishingHistory($originalActive, false);

            // Clear caches
            $this->clearStoryRelatedCaches();

            DB::commit();

            Toast::success('Story unpublished successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error unpublishing story: ' . $e->getMessage());
            Toast::error('Failed to unpublish story. Please try again.');
        }
    }

    /**
     * Duplicate story.
     */
    public function duplicateStory(): void
    {
        try {
            DB::beginTransaction();

            $duplicatedStory = $this->story->replicate();
            $duplicatedStory->title = $this->story->title . ' (Copy)';
            $duplicatedStory->active = false;
            $duplicatedStory->active_from = null;
            $duplicatedStory->active_until = null;
            $duplicatedStory->views = 0;
            $duplicatedStory->likes = 0;
            $duplicatedStory->save();

            // Duplicate tags
            $duplicatedStory->tags()->sync($this->story->tags->pluck('id'));

            DB::commit();

            Toast::success('Story duplicated successfully!');
            
            return redirect()->route('platform.stories.edit', $duplicatedStory->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error duplicating story: ' . $e->getMessage());
            Toast::error('Failed to duplicate story. Please try again.');
        }
    }

    /**
     * Delete story.
     */
    public function deleteStory(): void
    {
        try {
            DB::beginTransaction();

            // Delete related data
            $this->story->tags()->detach();
            $this->story->publishingHistory()->delete();
            $this->story->memberReadingHistory()->delete();

            // Delete story image if exists
            if ($this->story->image) {
                Storage::disk('public')->delete($this->story->image);
            }

            $this->story->delete();

            // Clear caches
            $this->clearStoryRelatedCaches();

            DB::commit();

            Toast::success('Story deleted successfully!');
            
            return redirect()->route('platform.stories');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting story: ' . $e->getMessage());
            Toast::error('Failed to delete story. Please try again.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get word count data for the story.
     */
    private function getWordCountData(Story $story): array
    {
        if (!$story->exists || !$story->content) {
            return [
                'word_count' => 0,
                'reading_level' => 'intermediate',
                'estimated_reading_time' => 1,
                'character_count' => 0,
                'paragraph_count' => 0,
                'sentence_count' => 0,
            ];
        }

        return $this->wordCountService->analyzeContent($story->content);
    }

    /**
     * Get analytics data for the story.
     */
    private function getAnalyticsData(Story $story): array
    {
        if (!$story->exists) {
            return [];
        }

        return Cache::remember(
            "story.{$story->id}.analytics_data",
            now()->addHour(),
            fn() => $this->analyticsService->getStoryAnalytics($story->id, 'month')
        );
    }

    /**
     * Get publishing data for the story.
     */
    private function getPublishingData(Story $story): array
    {
        if (!$story->exists) {
            return [];
        }

        return Cache::remember(
            "story.{$story->id}.publishing_data",
            now()->addMinutes(30),
            fn() => $story->publishingHistory()
                ->with('user')
                ->latest()
                ->limit(10)
                ->get()
                ->toArray()
        );
    }

    /**
     * Get performance metrics for the story.
     */
    private function getPerformanceMetrics(Story $story): array
    {
        if (!$story->exists) {
            return [];
        }

        return Cache::remember(
            "story.{$story->id}.performance_metrics",
            now()->addHour(),
            fn() => [
                'views' => $story->views,
                'likes' => $story->likes,
                'completion_rate' => $story->completion_rate ?? 0,
                'avg_rating' => $story->avg_rating ?? 0,
                'total_ratings' => $story->total_ratings ?? 0,
                'engagement_score' => $story->engagement_score ?? 0,
                'social_shares' => $story->social_shares ?? 0,
                'comments_count' => $story->comments()->count(),
                'reading_time_actual' => $story->reading_time_actual ?? 0,
                'bounce_rate' => $story->bounce_rate ?? 0,
            ]
        );
    }

    /**
     * Get SEO data for the story.
     */
    private function getSeoData(Story $story): array
    {
        if (!$story->exists) {
            return [];
        }

        return [
            'seo_title' => $story->seo_title ?: $story->title,
            'seo_description' => $story->seo_description ?: $story->summary,
            'seo_keywords' => $story->seo_keywords,
            'slug' => $story->slug,
            'canonical_url' => route('stories.show', $story->id),
        ];
    }

    /**
     * Get validation rules for the form.
     */
    private function getValidationRules(): array
    {
        return [
            'title' => ['required', 'max:255'],
            'content' => ['required', 'min:100'],
            'category_id' => ['required', 'exists:categories,id'],
            'summary' => ['max:500'],
            'reading_time_minutes' => ['integer', 'min:1', 'max:120'],
        ];
    }

    /**
     * Create publishing history record.
     */
    private function createPublishingHistory(bool $originalActive, bool $newActive): void
    {
        StoryPublishingHistory::create([
            'story_id' => $this->story->id,
            'user_id' => auth()->id(),
            'action' => $newActive ? 'published' : 'unpublished',
            'previous_active_status' => $originalActive,
            'new_active_status' => $newActive,
            'scheduled_at' => null,
            'published_at' => $newActive ? now() : null,
            'reason' => 'Manual action via admin panel',
            'meta_data' => [
                'word_count' => $this->story->word_count,
                'reading_level' => $this->story->reading_level,
                'category' => $this->story->category->name ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }

    /**
     * Send publishing notification.
     */
    private function sendPublishingNotification(): void
    {
        // TODO: Implement push notification service
        // This would integrate with Firebase/FCM or similar service
        Log::info('Publishing notification sent', [
            'story_id' => $this->story->id,
            'title' => $this->story->title,
        ]);
    }

    /**
     * Clear story-related caches.
     */
    private function clearStoryRelatedCaches(): void
    {
        $cacheKeys = [
            "story.{$this->story->id}.analytics_data",
            "story.{$this->story->id}.publishing_data",
            "story.{$this->story->id}.performance_metrics",
            'stories.active',
            'stories.featured',
            'stories.recent',
            'stories.popular',
            "category.{$this->story->category_id}.stories",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear tag-based caches
        Cache::tags(['stories', 'analytics'])->flush();
    }

    /**
     * Update story analytics in background.
     */
    private function updateStoryAnalytics(): void
    {
        try {
            dispatch(function () {
                $this->analyticsService->updateStoryMetrics($this->story->id);
            })->afterResponse();
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch analytics update: ' . $e->getMessage());
        }
    }
}