// tests/Feature/Orchid/StoryEditScreenTest.php

namespace Tests\Feature\Orchid;

use Tests\TestCase;
use App\Models\{Story, Category, Tag, User};
use App\Services\WordCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Orchid\Support\Facades\Dashboard;

/**
 * Story Edit Screen Feature Tests
 * 
 * Integration tests for story editing functionality.
 */
class StoryEditScreenTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private Category $category;
    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        
        // Create test data
        $this->category = Category::factory()->create(['name' => 'Test Category']);
        $this->tag = Tag::factory()->create(['name' => 'Test Tag']);
        
        // Authenticate as admin
        $this->actingAs($this->admin);
    }

    /** @test */
    public function it_can_display_story_creation_screen()
    {
        $response = $this->get(route('platform.stories.create'));
        
        $response->assertStatus(200);
        $response->assertSee('Create New Story');
        $response->assertSee('Word Count Analytics');
    }

    /** @test */
    public function it_can_display_story_edit_screen()
    {
        $story = Story::factory()->create([
            'title' => 'Test Story',
            'category_id' => $this->category->id,
            'word_count' => 500,
            'reading_level' => 'intermediate',
        ]);
        
        $response = $this->get(route('platform.stories.edit', $story));
        
        $response->assertStatus(200);
        $response->assertSee('Edit Story: Test Story');
        $response->assertSee('Word Count: 500');
        $response->assertSee('Reading Level: Intermediate');
    }

    /** @test */
    public function it_can_create_new_story_with_word_count_analysis()
    {
        $storyData = [
            'story' => [
                'title' => 'New Test Story',
                'summary' => 'This is a test story summary.',
                'content' => 'This is the story content with multiple words for testing word count functionality.',
                'category_id' => $this->category->id,
                'tags' => [$this->tag->id],
                'active' => true,
                'featured' => false,
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->post(route('platform.stories.create'), $storyData);
        
        $response->assertRedirect();
        
        $story = Story::where('title', 'New Test Story')->first();
        $this->assertNotNull($story);
        $this->assertGreaterThan(0, $story->word_count);
        $this->assertNotNull($story->reading_level);
        $this->assertGreaterThan(0, $story->reading_time_minutes);
    }

    /** @test */
    public function it_can_update_existing_story()
    {
        $story = Story::factory()->create([
            'title' => 'Original Title',
            'content' => 'Original content',
            'category_id' => $this->category->id,
            'word_count' => 100,
        ]);
        
        $updateData = [
            'story' => [
                'title' => 'Updated Title',
                'summary' => 'Updated summary',
                'content' => 'Updated content with more words to test the word count functionality and reading level determination.',
                'category_id' => $this->category->id,
                'tags' => [$this->tag->id],
                'active' => true,
                'featured' => true,
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->put(route('platform.stories.edit', $story), $updateData);
        
        $response->assertRedirect();
        
        $story->refresh();
        $this->assertEquals('Updated Title', $story->title);
        $this->assertGreaterThan(100, $story->word_count);
        $this->assertTrue($story->featured);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $invalidData = [
            'story' => [
                'title' => '', // Empty title
                'content' => '', // Empty content
                'category_id' => 999, // Non-existent category
            ],
        ];
        
        $response = $this->post(route('platform.stories.create'), $invalidData);
        
        $response->assertSessionHasErrors(['story.title', 'story.content', 'story.category_id']);
    }

    /** @test */
    public function it_can_publish_story()
    {
        $story = Story::factory()->create([
            'active' => false,
            'category_id' => $this->category->id,
        ]);
        
        $response = $this->post(route('platform.stories.publish', $story));
        
        $response->assertRedirect();
        
        $story->refresh();
        $this->assertTrue($story->active);
        $this->assertNotNull($story->active_from);
    }

    /** @test */
    public function it_can_unpublish_story()
    {
        $story = Story::factory()->create([
            'active' => true,
            'category_id' => $this->category->id,
        ]);
        
        $response = $this->post(route('platform.stories.unpublish', $story));
        
        $response->assertRedirect();
        
        $story->refresh();
        $this->assertFalse($story->active);
    }

    /** @test */
    public function it_can_duplicate_story()
    {
        $story = Story::factory()->create([
            'title' => 'Original Story',
            'content' => 'Original content',
            'category_id' => $this->category->id,
        ]);
        
        $story->tags()->attach($this->tag);
        
        $response = $this->post(route('platform.stories.duplicate', $story));
        
        $response->assertRedirect();
        
        $duplicatedStory = Story::where('title', 'Original Story (Copy)')->first();
        $this->assertNotNull($duplicatedStory);
        $this->assertFalse($duplicatedStory->active);
        $this->assertEquals($story->content, $duplicatedStory->content);
        $this->assertEquals($story->tags->count(), $duplicatedStory->tags->count());
    }

    /** @test */
    public function it_can_delete_story()
    {
        $story = Story::factory()->create([
            'category_id' => $this->category->id,
        ]);
        
        $story->tags()->attach($this->tag);
        
        $response = $this->delete(route('platform.stories.delete', $story));
        
        $response->assertRedirect();
        
        $this->assertDatabaseMissing('stories', ['id' => $story->id]);
    }

    /** @test */
    public function it_creates_publishing_history_on_status_change()
    {
        $story = Story::factory()->create([
            'active' => false,
            'category_id' => $this->category->id,
        ]);
        
        $updateData = [
            'story' => [
                'title' => $story->title,
                'content' => $story->content,
                'category_id' => $story->category_id,
                'active' => true, // Change from false to true
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->put(route('platform.stories.edit', $story), $updateData);
        
        $response->assertRedirect();
        
        $this->assertDatabaseHas('story_publishing_history', [
            'story_id' => $story->id,
            'action' => 'published',
            'previous_active_status' => false,
            'new_active_status' => true,
        ]);
    }

    /** @test */
    public function it_handles_seo_fields_properly()
    {
        $storyData = [
            'story' => [
                'title' => 'SEO Test Story',
                'content' => 'Content for SEO testing with proper word count.',
                'category_id' => $this->category->id,
                'seo_title' => 'Custom SEO Title',
                'seo_description' => 'Custom SEO description for search engines.',
                'seo_keywords' => 'seo, test, story, keywords',
                'allow_comments' => true,
                'send_notification' => false,
            ],
        ];
        
        $response = $this->post(route('platform.stories.create'), $storyData);
        
        $response->assertRedirect();
        
        $story = Story::where('title', 'SEO Test Story')->first();
        $this->assertNotNull($story);
        $this->assertEquals('Custom SEO Title', $story->seo_title);
        $this->assertEquals('Custom SEO description for search engines.', $story->seo_description);
        $this->assertEquals('seo, test, story, keywords', $story->seo_keywords);
    }

    /** @test */
    public