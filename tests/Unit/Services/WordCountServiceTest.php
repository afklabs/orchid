<?php

// tests/Unit/Services/WordCountServiceTest.php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WordCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Word Count Service Unit Tests
 * 
 * Comprehensive testing for word count analysis functionality.
 */
class WordCountServiceTest extends TestCase
{
    use RefreshDatabase;

    private WordCountService $wordCountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wordCountService = new WordCountService();
    }

    /** @test */
    public function it_handles_date_scheduling_properly()
    {
        $futureDate = now()->addDays(7);
        
        $storyData = [
            'story' => [
                'title' => 'Scheduled Story',
                'content' => 'Content for scheduled story testing.',
                'category_id' => $this->category->id,
                'active' => true,
                'active_from' => $futureDate->format('Y-m-d H:i:s'),
                'active_until' => $futureDate->addDays(30)->format('Y-m-d H:i:s'),
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->post(route('platform.stories.create'), $storyData);
        
        $response->assertRedirect();
        
        $story = Story::where('title', 'Scheduled Story')->first();
        $this->assertNotNull($story);
        $this->assertNotNull($story->active_from);
        $this->assertNotNull($story->active_until);
        $this->assertTrue($story->active_until->greaterThan($story->active_from));
    }

    /** @test */
    public function it_validates_date_range_properly()
    {
        $invalidData = [
            'story' => [
                'title' => 'Invalid Date Story',
                'content' => 'Content for date validation testing.',
                'category_id' => $this->category->id,
                'active_from' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'active_until' => now()->addDays(5)->format('Y-m-d H:i:s'), // Until before From
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->post(route('platform.stories.create'), $invalidData);
        
        $response->assertSessionHasErrors(['story.active_until']);
    }

    /** @test */
    public function it_handles_tags_association_properly()
    {
        $tag1 = Tag::factory()->create(['name' => 'Tag 1']);
        $tag2 = Tag::factory()->create(['name' => 'Tag 2']);
        
        $storyData = [
            'story' => [
                'title' => 'Tagged Story',
                'content' => 'Content for tag association testing.',
                'category_id' => $this->category->id,
                'tags' => [$tag1->id, $tag2->id],
                'active' => true,
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->post(route('platform.stories.create'), $storyData);
        
        $response->assertRedirect();
        
        $story = Story::where('title', 'Tagged Story')->first();
        $this->assertNotNull($story);
        $this->assertEquals(2, $story->tags->count());
        $this->assertTrue($story->tags->contains($tag1));
        $this->assertTrue($story->tags->contains($tag2));
    }

    /** @test */
    public function it_clears_cache_on_story_update()
    {
        $story = Story::factory()->create([
            'title' => 'Cache Test Story',
            'category_id' => $this->category->id,
        ]);
        
        // Cache some data
        $cacheKey = "story.{$story->id}.analytics_data";
        \Cache::put($cacheKey, ['test' => 'data'], 3600);
        
        $updateData = [
            'story' => [
                'title' => 'Updated Cache Test Story',
                'content' => 'Updated content for cache testing.',
                'category_id' => $this->category->id,
                'active' => true,
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->put(route('platform.stories.edit', $story), $updateData);
        
        $response->assertRedirect();
        
        // Cache should be cleared
        $this->assertFalse(\Cache::has($cacheKey));
    }
}
