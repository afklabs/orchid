// tests/Unit/Models/StoryTest.php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\{Story, Category, Tag};
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Story Model Unit Tests
 * 
 * Tests for Story model functionality and relationships.
 */
class StoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_required_fillable_fields()
    {
        $story = new Story();
        
        $expectedFillable = [
            'title', 'summary', 'content', 'image', 'category_id',
            'active', 'active_from', 'active_until', 'featured',
            'sort_order', 'views', 'likes', 'reading_time_minutes',
            'word_count', 'reading_level', 'seo_title', 'seo_description',
            'seo_keywords', 'allow_comments', 'send_notification',
        ];
        
        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $story->getFillable());
        }
    }

    /** @test */
    public function it_belongs_to_category()
    {
        $category = Category::factory()->create();
        $story = Story::factory()->create(['category_id' => $category->id]);
        
        $this->assertInstanceOf(Category::class, $story->category);
        $this->assertEquals($category->id, $story->category->id);
    }

    /** @test */
    public function it_belongs_to_many_tags()
    {
        $story = Story::factory()->create();
        $tags = Tag::factory(3)->create();
        
        $story->tags()->attach($tags);
        
        $this->assertCount(3, $story->tags);
        $this->assertInstanceOf(Tag::class, $story->tags->first());
    }

    /** @test */
    public function it_has_publishing_history()
    {
        $story = Story::factory()->create();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $story->publishingHistory());
    }

    /** @test */
    public function it_can_determine_if_currently_active()
    {
        // Active story without date restrictions
        $activeStory = Story::factory()->create([
            'active' => true,
            'active_from' => null,
            'active_until' => null,
        ]);
        
        // Active story with future start date
        $futureStory = Story::factory()->create([
            'active' => true,
            'active_from' => now()->addDays(1),
            'active_until' => null,
        ]);
        
        // Active story with past end date
        $expiredStory = Story::factory()->create([
            'active' => true,
            'active_from' => now()->subDays(10),
            'active_until' => now()->subDays(1),
        ]);
        
        $this->assertTrue($activeStory->isCurrentlyActive());
        $this->assertFalse($futureStory->isCurrentlyActive());
        $this->assertFalse($expiredStory->isCurrentlyActive());
    }

    /** @test */
    public function it_can_calculate_reading_level_from_word_count()
    {
        $beginnerStory = Story::factory()->create(['word_count' => 300]);
        $intermediateStory = Story::factory()->create(['word_count' => 800]);
        $advancedStory = Story::factory()->create(['word_count' => 2000]);
        
        $this->assertEquals('beginner', $beginnerStory->calculateReadingLevel());
        $this->assertEquals('intermediate', $intermediateStory->calculateReadingLevel());
        $this->assertEquals('advanced', $advancedStory->calculateReadingLevel());
    }

    /** @test */
    public function it_can_generate_seo_friendly_slug()
    {
        $story = Story::factory()->create([
            'title' => 'This is a Test Story Title!'
        ]);
        
        $slug = $story->generateSlug();
        
        $this->assertEquals('this-is-a-test-story-title', $slug);
    }

    /** @test */
    public function it_can_get_word_count_statistics()
    {
        $story = Story::factory()->create([
            'word_count' => 1000,
            'reading_time_minutes' => 4,
        ]);
        
        $stats = $story->getWordCountStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('word_count', $stats);
        $this->assertArrayHasKey('reading_time_minutes', $stats);
        $this->assertArrayHasKey('words_per_minute', $stats);
        $this->assertEquals(1000, $stats['word_count']);
        $this->assertEquals(4, $stats['reading_time_minutes']);
    }

    /** @test */
    public function it_can_check_if_story_is_featured()
    {
        $featuredStory = Story::factory()->create(['featured' => true]);
        $regularStory = Story::factory()->create(['featured' => false]);
        
        $this->assertTrue($featuredStory->isFeatured());
        $this->assertFalse($regularStory->isFeatured());
    }

    /** @test */
    public function it_can_get_estimated_reading_time()
    {
        $story = Story::factory()->create(['word_count' => 500]);
        
        $estimatedTime = $story->getEstimatedReadingTime();
        
        $this->assertIsInt($estimatedTime);
        $this->assertGreaterThan(0, $estimatedTime);
    }

    /** @test */
    public function it_can_get_performance_metrics()
    {
        $story = Story::factory()->create([
            'views' => 1000,
            'likes' => 50,
            'word_count' => 800,
        ]);
        
        $metrics = $story->getPerformanceMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('views', $metrics);
        $this->assertArrayHasKey('likes', $metrics);
        $this->assertArrayHasKey('engagement_rate', $metrics);
    }

    /** @test */
    public function it_can_check_publishing_eligibility()
    {
        $eligibleStory = Story::factory()->create([
            'title' => 'Complete Story',
            'content' => 'This is a complete story with enough content for publishing.',
            'category_id' => Category::factory()->create()->id,
            'word_count' => 500,
        ]);
        
        $incompleteStory = Story::factory()->create([
            'title' => '',
            'content' => 'Short',
            'word_count' => 50,
        ]);
        
        $this->assertTrue($eligibleStory->isEligibleForPublishing());
        $this->assertFalse($incompleteStory->isEligibleForPublishing());
    }
} function it_counts_words_correctly_for_english_content()
    {
        $content = "This is a simple test content with exactly ten words.";
        $wordCount = $this->wordCountService->getWordCount($content);
        
        $this->assertEquals(10, $wordCount);
    }

    /** @test */
    public function it_counts_words_correctly_for_arabic_content()
    {
        $content = "هذا نص تجريبي باللغة العربية يحتوي على عشر كلمات";
        $wordCount = $this->wordCountService->getWordCount($content);
        
        $this->assertEquals(10, $wordCount);
    }

    /** @test */
    public function it_counts_words_correctly_for_mixed_content()
    {
        $content = "This is mixed content هذا نص مختلط with different languages";
        $wordCount = $this->wordCountService->getWordCount($content);
        
        $this->assertGreaterThan(0, $wordCount);
    }

    /** @test */
    public function it_handles_empty_content()
    {
        $content = "";
        $wordCount = $this->wordCountService->getWordCount($content);
        
        $this->assertEquals(0, $wordCount);
    }

    /** @test */
    public function it_handles_html_content()
    {
        $content = "<p>This is <strong>HTML</strong> content with <em>markup</em></p>";
        $wordCount = $this->wordCountService->getWordCount($content);
        
        $this->assertEquals(6, $wordCount);
    }

    /** @test */
    public function it_determines_reading_level_correctly()
    {
        // Beginner level (≤500 words)
        $beginnerContent = str_repeat("word ", 100);
        $beginnerLevel = $this->wordCountService->getReadingLevel($beginnerContent);
        $this->assertEquals('beginner', $beginnerLevel);

        // Intermediate level (501-1500 words)
        $intermediateContent = str_repeat("word ", 800);
        $intermediateLevel = $this->wordCountService->getReadingLevel($intermediateContent);
        $this->assertEquals('intermediate', $intermediateLevel);

        // Advanced level (>1500 words)
        $advancedContent = str_repeat("word ", 2000);
        $advancedLevel = $this->wordCountService->getReadingLevel($advancedContent);
        $this->assertEquals('advanced', $advancedLevel);
    }

    /** @test */
    public function it_estimates_reading_time_correctly()
    {
        $content = str_repeat("word ", 250); // 250 words
        $readingTime = $this->wordCountService->estimateReadingTime($content);
        
        $this->assertEquals(1, $readingTime); // Should be 1 minute at 250 WPM
    }

    /** @test */
    public function it_performs_comprehensive_analysis()
    {
        $content = "This is a test story. It has multiple sentences and paragraphs.\n\nThis is the second paragraph with more content.";
        $analysis = $this->wordCountService->analyzeContent($content);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('word_count', $analysis);
        $this->assertArrayHasKey('reading_level', $analysis);
        $this->assertArrayHasKey('estimated_reading_time', $analysis);
        $this->assertArrayHasKey('paragraph_count', $analysis);
        $this->assertArrayHasKey('sentence_count', $analysis);
        $this->assertArrayHasKey('readability_score', $analysis);
        
        $this->assertGreaterThan(0, $analysis['word_count']);
        $this->assertGreaterThan(0, $analysis['paragraph_count']);
        $this->assertGreaterThan(0, $analysis['sentence_count']);
    }

    /** @test */
    public function it_calculates_readability_score()
    {
        $content = "This is easy to read. Short sentences. Simple words.";
        $score = $this->wordCountService->getReadabilityScore($content);
        
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /** @test */
    public function it_handles_content_progress_tracking()
    {
        $content = str_repeat("word ", 500); // 500 words
        $progress = $this->wordCountService->getContentProgress($content, 1000);
        
        $this->assertIsArray($progress);
        $this->assertEquals(500, $progress['current_words']);
        $this->assertEquals(1000, $progress['target_words']);
        $this->assertEquals(50.0, $progress['progress_percentage']);
        $this->assertEquals(500, $progress['words_remaining']);
        $this->assertFalse($progress['is_target_met']);
    }

    /** @test */
    public function it_processes_batch_content_analysis()
    {
        $contentList = [
            'content1' => 'This is the first content piece.',
            'content2' => 'This is the second content piece with more words.',
            'content3' => 'Third content piece.',
        ];
        
        $results = $this->wordCountService->analyzeBatch($contentList);
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        
        foreach ($results as $key => $result) {
            $this->assertArrayHasKey('word_count', $result);
            $this->assertArrayHasKey('reading_level', $result);
        }
    }

    /** @test */
    public function it_caches_analysis_results()
    {
        $content = "This is test content for caching verification.";
        
        // First call should compute and cache
        $startTime = microtime(true);
        $result1 = $this->wordCountService->analyzeContent($content);
        $firstCallTime = microtime(true) - $startTime;
        
        // Second call should be faster due to caching
        $startTime = microtime(true);
        $result2 = $this->wordCountService->analyzeContent($content);
        $secondCallTime = microtime(true) - $startTime;
        
        $this->assertEquals($result1, $result2);
        $this->assertLessThan($firstCallTime, $secondCallTime);
    }
}
