<?php

namespace Tests\Unit\Models;

use App\Models\{Story, Category, User, MemberStoryRating, MemberReadingHistory, MemberStoryInteraction, StoryRatingAggregate};
use Illuminate\Foundation\Testing\{RefreshDatabase, WithFaker};
use Tests\TestCase;
use Carbon\Carbon;

/**
 * Story Performance Tests
 * 
 * Comprehensive test suite for story performance calculations,
 * metrics, and analytics functionality.
 */
class StoryPerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Story instance for testing.
     */
    protected Story $story;

    /**
     * Test user for authoring stories.
     */
    protected User $author;

    /**
     * Test category for stories.
     */
    protected Category $category;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->author = User::factory()->create([
            'name' => 'Test Author',
            'email' => 'author@test.com',
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);

        $this->story = Story::factory()->create([
            'title' => 'Test Story',
            'slug' => 'test-story',
            'content' => str_repeat('This is test content. ', 100),
            'word_count' => 500,
            'reading_level' => 'intermediate',
            'reading_time_minutes' => 3,
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
            'status' => 'active',
            'published_at' => now()->subDays(10),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PERFORMANCE SCORE TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test basic performance score calculation.
     */
    public function test_calculates_basic_performance_score(): void
    {
        $score = $this->story->calculatePerformanceScore();
        
        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test performance score with views.
     */
    public function test_performance_score_includes_views(): void
    {
        // Create some views
        MemberStoryInteraction::factory()->count(50)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        $scoreWithViews = $this->story->calculatePerformanceScore();
        
        // Should have a higher score with views
        $this->assertGreaterThan(0, $scoreWithViews);
    }

    /**
     * Test performance score with ratings.
     */
    public function test_performance_score_includes_ratings(): void
    {
        // Create rating aggregate
        StoryRatingAggregate::factory()->create([
            'story_id' => $this->story->id,
            'average_rating' => 4.5,
            'total_ratings' => 20,
        ]);

        $scoreWithRatings = $this->story->calculatePerformanceScore();
        
        // Should have a higher score with good ratings
        $this->assertGreaterThan(0, $scoreWithRatings);
    }

    /**
     * Test performance score with completion data.
     */
    public function test_performance_score_includes_completion(): void
    {
        // Create reading history with completions
        MemberReadingHistory::factory()->count(10)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 100,
        ]);

        MemberReadingHistory::factory()->count(5)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 50,
        ]);

        $scoreWithCompletion = $this->story->calculatePerformanceScore();
        
        // Should have a higher score with good completion rate
        $this->assertGreaterThan(0, $scoreWithCompletion);
    }

    /**
     * Test performance score with fresh content.
     */
    public function test_performance_score_includes_freshness(): void
    {
        // Create a fresh story
        $freshStory = Story::factory()->create([
            'title' => 'Fresh Story',
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $freshScore = $freshStory->calculatePerformanceScore();
        $oldScore = $this->story->calculatePerformanceScore();
        
        // Fresh story should have higher freshness component
        $this->assertGreaterThanOrEqual($oldScore, $freshScore);
    }

    /**
     * Test performance score maximum values.
     */
    public function test_performance_score_maximum_values(): void
    {
        // Create maximum performance conditions
        MemberStoryInteraction::factory()->count(1000)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        StoryRatingAggregate::factory()->create([
            'story_id' => $this->story->id,
            'average_rating' => 5.0,
            'total_ratings' => 100,
        ]);

        MemberReadingHistory::factory()->count(100)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 100,
        ]);

        // Update story to be very fresh
        $this->story->update(['published_at' => now()]);

        $maxScore = $this->story->calculatePerformanceScore();
        
        // Should be close to maximum (100)
        $this->assertGreaterThan(80, $maxScore);
        $this->assertLessThanOrEqual(100, $maxScore);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETION RATE TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test completion rate calculation.
     */
    public function test_calculates_completion_rate(): void
    {
        // Create reading history
        MemberReadingHistory::factory()->count(10)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 100,
        ]);

        MemberReadingHistory::factory()->count(5)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 50,
        ]);

        $completionRate = $this->story->calculateCompletionRate();
        
        // Should be 10/15 = 66.67%
        $this->assertEquals(66.67, $completionRate);
    }

    /**
     * Test completion rate with no readers.
     */
    public function test_completion_rate_with_no_readers(): void
    {
        $completionRate = $this->story->calculateCompletionRate();
        
        $this->assertEquals(0.0, $completionRate);
    }

    /**
     * Test completion rate with all completed readers.
     */
    public function test_completion_rate_with_all_completed(): void
    {
        MemberReadingHistory::factory()->count(10)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 100,
        ]);

        $completionRate = $this->story->calculateCompletionRate();
        
        $this->assertEquals(100.0, $completionRate);
    }

    /**
     * Test completion rate with no completed readers.
     */
    public function test_completion_rate_with_no_completed(): void
    {
        MemberReadingHistory::factory()->count(10)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 50,
        ]);

        $completionRate = $this->story->calculateCompletionRate();
        
        $this->assertEquals(0.0, $completionRate);
    }

    /*
    |--------------------------------------------------------------------------
    | PERFORMANCE METRICS TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test detailed performance metrics.
     */
    public function test_gets_detailed_performance_metrics(): void
    {
        // Create test data
        MemberStoryInteraction::factory()->count(100)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        MemberStoryInteraction::factory()->count(10)->create([
            'story_id' => $this->story->id,
            'action' => 'bookmark',
        ]);

        MemberStoryInteraction::factory()->count(5)->create([
            'story_id' => $this->story->id,
            'action' => 'share',
        ]);

        StoryRatingAggregate::factory()->create([
            'story_id' => $this->story->id,
            'average_rating' => 4.2,
            'total_ratings' => 25,
        ]);

        MemberReadingHistory::factory()->count(20)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 100,
        ]);

        MemberReadingHistory::factory()->count(10)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 50,
        ]);

        $metrics = $this->story->getPerformanceMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('views', $metrics);
        $this->assertArrayHasKey('completion_rate', $metrics);
        $this->assertArrayHasKey('average_rating', $metrics);
        $this->assertArrayHasKey('performance_score', $metrics);
        $this->assertArrayHasKey('performance_level', $metrics);
        $this->assertArrayHasKey('trending_score', $metrics);
        $this->assertArrayHasKey('engagement_rate', $metrics);
        $this->assertArrayHasKey('social_shares', $metrics);
        $this->assertArrayHasKey('bookmarks', $metrics);

        $this->assertEquals(100, $metrics['views']);
        $this->assertEquals(10, $metrics['bookmarks']);
        $this->assertEquals(5, $metrics['social_shares']);
        $this->assertEquals(4.2, $metrics['average_rating']);
        $this->assertEquals(66.67, $metrics['completion_rate']);
    }

    /**
     * Test performance level determination.
     */
    public function test_determines_performance_level(): void
    {
        $this->assertEquals('excellent', $this->story->getPerformanceLevel(85));
        $this->assertEquals('good', $this->story->getPerformanceLevel(65));
        $this->assertEquals('average', $this->story->getPerformanceLevel(45));
        $this->assertEquals('poor', $this->story->getPerformanceLevel(25));
    }

    /**
     * Test performance badge determination.
     */
    public function test_determines_performance_badge(): void
    {
        $this->assertEquals('🔥', $this->story->getPerformanceBadge(85));
        $this->assertEquals('📈', $this->story->getPerformanceBadge(65));
        $this->assertEquals('📊', $this->story->getPerformanceBadge(45));
        $this->assertEquals('📉', $this->story->getPerformanceBadge(25));
    }

    /*
    |--------------------------------------------------------------------------
    | TRENDING SCORE TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test trending score calculation.
     */
    public function test_calculates_trending_score(): void
    {
        // Create views over time
        MemberStoryInteraction::factory()->count(50)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
            'created_at' => now()->subDays(14),
        ]);

        MemberStoryInteraction::factory()->count(30)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
            'created_at' => now()->subDays(3),
        ]);

        MemberStoryRating::factory()->count(5)->create([
            'story_id' => $this->story->id,
            'created_at' => now()->subDays(2),
        ]);

        $trendingScore = $this->story->calculateTrendingScore();
        
        $this->assertIsFloat($trendingScore);
        $this->assertGreaterThan(0, $trendingScore);
    }

    /**
     * Test trending score with no recent activity.
     */
    public function test_trending_score_with_no_recent_activity(): void
    {
        // Create only old views
        MemberStoryInteraction::factory()->count(100)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
            'created_at' => now()->subDays(30),
        ]);

        $trendingScore = $this->story->calculateTrendingScore();
        
        $this->assertEquals(0.0, $trendingScore);
    }

    /*
    |--------------------------------------------------------------------------
    | ENGAGEMENT RATE TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test engagement rate calculation.
     */
    public function test_calculates_engagement_rate(): void
    {
        // Create views and engagements
        MemberStoryInteraction::factory()->count(100)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        MemberStoryRating::factory()->count(10)->create([
            'story_id' => $this->story->id,
        ]);

        MemberStoryInteraction::factory()->count(5)->create([
            'story_id' => $this->story->id,
            'action' => 'bookmark',
        ]);

        MemberStoryInteraction::factory()->count(3)->create([
            'story_id' => $this->story->id,
            'action' => 'share',
        ]);

        $engagementRate = $this->story->calculateEngagementRate();
        
        // Should be (10 + 5 + 3) / 100 = 18%
        $this->assertEquals(18.0, $engagementRate);
    }

    /**
     * Test engagement rate with no views.
     */
    public function test_engagement_rate_with_no_views(): void
    {
        $engagementRate = $this->story->calculateEngagementRate();
        
        $this->assertEquals(0.0, $engagementRate);
    }

    /*
    |--------------------------------------------------------------------------
    | BOUNCE RATE TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test bounce rate calculation.
     */
    public function test_calculates_bounce_rate(): void
    {
        // Create views
        MemberStoryInteraction::factory()->count(100)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        // Create short sessions (bounces)
        MemberReadingHistory::factory()->count(20)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 5,
        ]);

        $bounceRate = $this->story->calculateBounceRate();
        
        // Should be 20/100 = 20%
        $this->assertEquals(20.0, $bounceRate);
    }

    /**
     * Test bounce rate with no views.
     */
    public function test_bounce_rate_with_no_views(): void
    {
        $bounceRate = $this->story->calculateBounceRate();
        
        $this->assertEquals(0.0, $bounceRate);
    }

    /*
    |--------------------------------------------------------------------------
    | RATING DISTRIBUTION TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test rating distribution.
     */
    public function test_gets_rating_distribution(): void
    {
        StoryRatingAggregate::factory()->create([
            'story_id' => $this->story->id,
            'average_rating' => 4.2,
            'total_ratings' => 100,
            'rating_1_count' => 2,
            'rating_2_count' => 3,
            'rating_3_count' => 15,
            'rating_4_count' => 35,
            'rating_5_count' => 45,
        ]);

        $distribution = $this->story->getRatingDistribution();
        
        $this->assertIsArray($distribution);
        $this->assertEquals(2, $distribution[1]);
        $this->assertEquals(3, $distribution[2]);
        $this->assertEquals(15, $distribution[3]);
        $this->assertEquals(35, $distribution[4]);
        $this->assertEquals(45, $distribution[5]);
    }

    /**
     * Test rating distribution with no ratings.
     */
    public function test_rating_distribution_with_no_ratings(): void
    {
        $distribution = $this->story->getRatingDistribution();
        
        $this->assertIsArray($distribution);
        $this->assertEquals([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $distribution);
    }

    /*
    |--------------------------------------------------------------------------
    | READING PATTERNS TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test reading patterns.
     */
    public function test_gets_reading_patterns(): void
    {
        // Create reading history at different hours
        MemberReadingHistory::factory()->count(5)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 80,
            'created_at' => now()->setHour(9),
        ]);

        MemberReadingHistory::factory()->count(3)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 90,
            'created_at' => now()->setHour(14),
        ]);

        $patterns = $this->story->getReadingPatterns();
        
        $this->assertIsArray($patterns);
        $this->assertArrayHasKey(9, $patterns);
        $this->assertArrayHasKey(14, $patterns);
        $this->assertEquals(5, $patterns[9]['count']);
        $this->assertEquals(3, $patterns[14]['count']);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETION FUNNEL TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test completion funnel.
     */
    public function test_gets_completion_funnel(): void
    {
        // Create reading history with different progress levels
        MemberReadingHistory::factory()->count(100)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 10,
        ]);

        MemberReadingHistory::factory()->count(80)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 30,
        ]);

        MemberReadingHistory::factory()->count(60)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 60,
        ]);

        MemberReadingHistory::factory()->count(40)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 80,
        ]);

        MemberReadingHistory::factory()->count(20)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 100,
        ]);

        $funnel = $this->story->getCompletionFunnel();
        
        $this->assertIsArray($funnel);
        $this->assertArrayHasKey('started', $funnel);
        $this->assertArrayHasKey('quarter', $funnel);
        $this->assertArrayHasKey('half', $funnel);
        $this->assertArrayHasKey('three_quarters', $funnel);
        $this->assertArrayHasKey('completed', $funnel);
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC METHODS TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test getting top performing stories.
     */
    public function test_gets_top_performing_stories(): void
    {
        // Create additional stories with different performance
        $highPerformingStory = Story::factory()->create([
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
            'status' => 'active',
        ]);

        $lowPerformingStory = Story::factory()->create([
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
            'status' => 'active',
        ]);

        // Add performance data to high performing story
        MemberStoryInteraction::factory()->count(1000)->create([
            'story_id' => $highPerformingStory->id,
            'action' => 'view',
        ]);

        StoryRatingAggregate::factory()->create([
            'story_id' => $highPerformingStory->id,
            'average_rating' => 4.8,
            'total_ratings' => 100,
        ]);

        $topStories = Story::getTopPerforming(5);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $topStories);
        $this->assertLessThanOrEqual(5, $topStories->count());
        
        // High performing story should be first
        $this->assertEquals($highPerformingStory->id, $topStories->first()->id);
    }

    /**
     * Test getting trending stories.
     */
    public function test_gets_trending_stories(): void
    {
        $trendingStory = Story::factory()->create([
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
            'status' => 'active',
        ]);

        // Add recent views
        MemberStoryInteraction::factory()->count(100)->create([
            'story_id' => $trendingStory->id,
            'action' => 'view',
            'created_at' => now()->subDays(2),
        ]);

        $trendingStories = Story::getTrending(5);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $trendingStories);
        $this->assertLessThanOrEqual(5, $trendingStories->count());
    }

    /**
     * Test getting stories needing attention.
     */
    public function test_gets_stories_needing_attention(): void
    {
        $needsAttentionStory = Story::factory()->create([
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
            'status' => 'active',
        ]);

        // Add poor performance data
        StoryRatingAggregate::factory()->create([
            'story_id' => $needsAttentionStory->id,
            'average_rating' => 2.0,
            'total_ratings' => 5,
        ]);

        $needsAttentionStories = Story::getNeedingAttention(5);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $needsAttentionStories);
        $this->assertLessThanOrEqual(5, $needsAttentionStories->count());
    }

    /**
     * Test getting platform metrics.
     */
    public function test_gets_platform_metrics(): void
    {
        // Create additional test data
        Story::factory()->count(5)->create([
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
            'status' => 'active',
        ]);

        MemberStoryInteraction::factory()->count(500)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        $metrics = Story::getPlatformMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_stories', $metrics);
        $this->assertArrayHasKey('active_stories', $metrics);
        $this->assertArrayHasKey('total_views', $metrics);
        $this->assertArrayHasKey('total_words', $metrics);
        $this->assertArrayHasKey('avg_rating', $metrics);
        $this->assertArrayHasKey('avg_performance', $metrics);
        $this->assertArrayHasKey('trending_count', $metrics);
    }

    /*
    |--------------------------------------------------------------------------
    | CACHE TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test performance cache clearing.
     */
    public function test_clears_performance_cache(): void
    {
        // Access performance score to cache it
        $originalScore = $this->story->performance_score;
        
        // Clear cache
        $this->story->clearPerformanceCache();
        
        // Add new performance data
        MemberStoryInteraction::factory()->count(100)->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        // Fresh calculation should be different
        $newScore = $this->story->calculatePerformanceScore();
        
        $this->assertNotEquals($originalScore, $newScore);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSOR TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test formatted word count accessor.
     */
    public function test_formatted_word_count_accessor(): void
    {
        $this->story->update(['word_count' => 1500]);
        $this->assertEquals('1.5K', $this->story->formatted_word_count);
        
        $this->story->update(['word_count' => 500]);
        $this->assertEquals('500', $this->story->formatted_word_count);
        
        $this->story->update(['word_count' => null]);
        $this->assertEquals('Not calculated', $this->story->formatted_word_count);
    }

    /**
     * Test formatted reading time accessor.
     */
    public function test_reading_time_formatted_accessor(): void
    {
        $this->story->update(['reading_time_minutes' => 65]);
        $this->assertEquals('1h 5m', $this->story->reading_time_formatted);
        
        $this->story->update(['reading_time_minutes' => 30]);
        $this->assertEquals('30 min', $this->story->reading_time_formatted);
        
        $this->story->update(['reading_time_minutes' => null]);
        $this->assertEquals('Unknown', $this->story->reading_time_formatted);
    }

    /**
     * Test performance score accessor.
     */
    public function test_performance_score_accessor(): void
    {
        $score = $this->story->performance_score;
        
        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test completion rate accessor.
     */
    public function test_completion_rate_accessor(): void
    {
        MemberReadingHistory::factory()->count(10)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 100,
        ]);

        MemberReadingHistory::factory()->count(5)->create([
            'story_id' => $this->story->id,
            'reading_progress' => 50,
        ]);

        $completionRate = $this->story->completion_rate;
        
        $this->assertIsFloat($completionRate);
        $this->assertEquals(66.67, $completionRate);
    }

    /**
     * Test average rating accessor.
     */
    public function test_average_rating_accessor(): void
    {
        StoryRatingAggregate::factory()->create([
            'story_id' => $this->story->id,
            'average_rating' => 4.3,
        ]);

        $this->assertEquals(4.3, $this->story->average_rating);
    }

    /**
     * Test average rating accessor with no ratings.
     */
    public function test_average_rating_accessor_with_no_ratings(): void
    {
        $this->assertEquals(0.0, $this->story->average_rating);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPE TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test active scope.
     */
    public function test_active_scope(): void
    {
        $inactiveStory = Story::factory()->create([
            'status' => 'inactive',
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
        ]);

        $activeStories = Story::active()->get();
        
        $this->assertTrue($activeStories->contains($this->story));
        $this->assertFalse($activeStories->contains($inactiveStory));
    }

    /**
     * Test published scope.
     */
    public function test_published_scope(): void
    {
        $futureStory = Story::factory()->create([
            'status' => 'active',
            'published_at' => now()->addDays(1),
            'category_id' => $this->category->id,
            'author_id' => $this->author->id,
        ]);

        $publishedStories = Story::published()->get();
        
        $this->assertTrue($publishedStories->contains($this->story));
        $this->assertFalse($publishedStories->contains($futureStory));
    }

    /**
     * Test featured scope.
     */
    public function test_featured_scope(): void
    {
        $this->story->update(['is_featured' => true]);
        
        $featuredStories = Story::featured()->get();
        
        $this->assertTrue($featuredStories->contains($this->story));
    }

    /**
     * Test word count scope.
     */
    public function test_word_count_scope(): void
    {
        $this->story->update(['word_count' => 1000]);
        
        $storiesInRange = Story::byWordCount(500, 1500)->get();
        $storiesOutOfRange = Story::byWordCount(1500, 2000)->get();
        
        $this->assertTrue($storiesInRange->contains($this->story));
        $this->assertFalse($storiesOutOfRange->contains($this->story));
    }

    /**
     * Test reading level scope.
     */
    public function test_reading_level_scope(): void
    {
        $this->story->update(['reading_level' => 'advanced']);
        
        $advancedStories = Story::byReadingLevel('advanced')->get();
        $beginnerStories = Story::byReadingLevel('beginner')->get();
        
        $this->assertTrue($advancedStories->contains($this->story));
        $this->assertFalse($beginnerStories->contains($this->story));
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP TESTS
    |--------------------------------------------------------------------------
    */

    /**
     * Test category relationship.
     */
    public function test_category_relationship(): void
    {
        $this->assertInstanceOf(Category::class, $this->story->category);
        $this->assertEquals($this->category->id, $this->story->category->id);
    }

    /**
     * Test author relationship.
     */
    public function test_author_relationship(): void
    {
        $this->assertInstanceOf(User::class, $this->story->author);
        $this->assertEquals($this->author->id, $this->story->author->id);
    }

    /**
     * Test rating aggregate relationship.
     */
    public function test_rating_aggregate_relationship(): void
    {
        $aggregate = StoryRatingAggregate::factory()->create([
            'story_id' => $this->story->id,
        ]);

        $this->assertInstanceOf(StoryRatingAggregate::class, $this->story->ratingAggregate);
        $this->assertEquals($aggregate->id, $this->story->ratingAggregate->id);
    }

    /**
     * Test ratings relationship.
     */
    public function test_ratings_relationship(): void
    {
        $rating = MemberStoryRating::factory()->create([
            'story_id' => $this->story->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->story->ratings);
        $this->assertTrue($this->story->ratings->contains($rating));
    }

    /**
     * Test reading history relationship.
     */
    public function test_reading_history_relationship(): void
    {
        $history = MemberReadingHistory::factory()->create([
            'story_id' => $this->story->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->story->readingHistory);
        $this->assertTrue($this->story->readingHistory->contains($history));
    }

    /**
     * Test interactions relationship.
     */
    public function test_interactions_relationship(): void
    {
        $interaction = MemberStoryInteraction::factory()->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->story->interactions);
        $this->assertTrue($this->story->interactions->contains($interaction));
    }

    /**
     * Test views relationship.
     */
    public function test_views_relationship(): void
    {
        $view = MemberStoryInteraction::factory()->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        $bookmark = MemberStoryInteraction::factory()->create([
            'story_id' => $this->story->id,
            'action' => 'bookmark',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->story->views);
        $this->assertTrue($this->story->views->contains($view));
        $this->assertFalse($this->story->views->contains($bookmark));
    }

    /**
     * Test bookmarks relationship.
     */
    public function test_bookmarks_relationship(): void
    {
        $bookmark = MemberStoryInteraction::factory()->create([
            'story_id' => $this->story->id,
            'action' => 'bookmark',
        ]);

        $view = MemberStoryInteraction::factory()->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->story->bookmarks);
        $this->assertTrue($this->story->bookmarks->contains($bookmark));
        $this->assertFalse($this->story->bookmarks->contains($view));
    }

    /**
     * Test shares relationship.
     */
    public function test_shares_relationship(): void
    {
        $share = MemberStoryInteraction::factory()->create([
            'story_id' => $this->story->id,
            'action' => 'share',
        ]);

        $view = MemberStoryInteraction::factory()->create([
            'story_id' => $this->story->id,
            'action' => 'view',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->story->shares);
        $this->assertTrue($this->story->shares->contains($share));
        $this->assertFalse($this->story->shares->contains($view));
    }
}