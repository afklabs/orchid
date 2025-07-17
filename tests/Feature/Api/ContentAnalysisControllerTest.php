// tests/Feature/Api/ContentAnalysisControllerTest.php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Services\WordCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Content Analysis API Tests
 * 
 * Tests for real-time content analysis API endpoints.
 */
class ContentAnalysisControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        
        // Authenticate as admin
        $this->actingAs($this->admin);
    }

    /** @test */
    public function it_can_analyze_content_in_real_time()
    {
        $content = 'This is a test content for real-time analysis. It contains multiple sentences and should provide comprehensive analysis results.';
        
        $response = $this->postJson('/admin/api/analyze-content', [
            'content' => $content,
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'analysis' => [
                    'word_count',
                    'reading_level',
                    'estimated_reading_time',
                    'character_count',
                    'paragraph_count',
                    'sentence_count',
                    'readability_score',
                    'complexity_score',
                ],
                'message',
            ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, $response->json('analysis.word_count'));
    }

    /** @test */
    public function it_validates_content_analysis_request()
    {
        $response = $this->postJson('/admin/api/analyze-content', [
            'content' => '', // Empty content
        ]);
        
        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'content',
                ],
            ]);
        
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_calculate_content_progress()
    {
        $content = str_repeat('word ', 500); // 500 words
        
        $response = $this->postJson('/admin/api/content-progress', [
            'content' => $content,
            'target_words' => 1000,
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'progress' => [
                    'current_words',
                    'target_words',
                    'progress_percentage',
                    'words_remaining',
                    'is_target_met',
                ],
                'message',
            ]);
        
        $progress = $response->json('progress');
        $this->assertEquals(500, $progress['current_words']);
        $this->assertEquals(1000, $progress['target_words']);
        $this->assertEquals(50.0, $progress['progress_percentage']);
        $this->assertFalse($progress['is_target_met']);
    }

    /** @test */
    public function it_can_provide_reading_level_suggestions()
    {
        $content = 'This is a simple story with basic vocabulary and short sentences.';
        
        $response = $this->postJson('/admin/api/reading-level-suggestions', [
            'content' => $content,
            'target_level' => 'advanced',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'current_level',
                'target_level',
                'suggestions',
                'analysis',
            ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertEquals('advanced', $response->json('target_level'));
        $this->assertIsArray($response->json('suggestions'));
    }

    /** @test */
    public function it_can_validate_content_quality()
    {
        $content = 'This is a comprehensive story with multiple paragraphs and detailed content. It should pass most quality checks and provide good validation results.';
        
        $response = $this->postJson('/admin/api/validate-content', [
            'content' => $content,
            'title' => 'Test Story Title',
            'category_id' => 1,
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'validation' => [
                    'checks',
                    'overall_score',
                    'recommendations',
                ],
                'is_valid',
                'message',
            ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('validation.checks'));
        $this->assertIsNumeric($response->json('validation.overall_score'));
    }

    /** @test */
    public function it_can_provide_optimization_suggestions()
    {
        $content = 'This is content for optimization testing. It should provide relevant suggestions based on target audience and content type.';
        
        $response = $this->postJson('/admin/api/optimization-suggestions', [
            'content' => $content,
            'target_audience' => 'adults',
            'content_type' => 'story',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'suggestions',
                'analysis',
                'message',
            ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('suggestions'));
    }

    /** @test */
    public function it_handles_too_long_content()
    {
        $content = str_repeat('word ', 50000); // Very long content
        
        $response = $this->postJson('/admin/api/analyze-content', [
            'content' => $content,
        ]);
        
        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
        
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_requires_authentication()
    {
        // Test without authentication
        $this->actingAs(null);
        
        $response = $this->postJson('/admin/api/analyze-content', [
            'content' => 'Test content',
        ]);
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_invalid_target_level()
    {
        $content = 'Test content for invalid target level testing.';
        
        $response = $this->postJson('/admin/api/reading-level-suggestions', [
            'content' => $content,
            'target_level' => 'invalid_level',
        ]);
        
        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'target_level',
                ],
            ]);
        
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_handles_invalid_target_audience()
    {
        $content = 'Test content for invalid target audience testing.';
        
        $response = $this->postJson('/admin/api/optimization-suggestions', [
            'content' => $content,
            'target_audience' => 'invalid_audience',
            'content_type' => 'story',
        ]);
        
        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'target_audience',
                ],
            ]);
        
        $this->assertFalse($response->json('success'));
    }
}
