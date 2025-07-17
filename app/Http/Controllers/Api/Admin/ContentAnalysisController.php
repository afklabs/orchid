<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\WordCountService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, Validator};

/**
 * Content Analysis API Controller
 * 
 * Provides real-time content analysis for the story editor,
 * including word count, reading level, and content metrics.
 * 
 * @package App\Http\Controllers\Api\Admin
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class ContentAnalysisController extends Controller
{
    /**
     * Word count service instance.
     */
    private WordCountService $wordCountService;

    /**
     * Constructor.
     */
    public function __construct(WordCountService $wordCountService)
    {
        $this->wordCountService = $wordCountService;
        
        // Apply admin middleware
        $this->middleware('auth:admin');
    }

    /**
     * Analyze content in real-time.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function analyzeContent(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:100000', // 100KB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid content provided',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $content = $request->input('content');

            // Perform analysis
            $analysis = $this->wordCountService->getRealTimeAnalysis($content);

            return response()->json([
                'success' => true,
                'analysis' => $analysis,
                'message' => 'Content analyzed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in content analysis API: ' . $e->getMessage(), [
                'content_length' => strlen($request->input('content', '')),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while analyzing content',
                'error' => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get content progress towards target.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getContentProgress(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:100000',
                'target_words' => 'nullable|integer|min:100|max:10000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $content = $request->input('content');
            $targetWords = $request->input('target_words', 1000);

            // Get progress data
            $progress = $this->wordCountService->getContentProgress($content, $targetWords);

            return response()->json([
                'success' => true,
                'progress' => $progress,
                'message' => 'Progress calculated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in content progress API: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while calculating progress',
            ], 500);
        }
    }

    /**
     * Get reading level suggestions.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getReadingLevelSuggestions(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:100000',
                'target_level' => 'nullable|in:beginner,intermediate,advanced',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $content = $request->input('content');
            $targetLevel = $request->input('target_level', 'intermediate');

            // Analyze current content
            $analysis = $this->wordCountService->getRealTimeAnalysis($content);
            $currentLevel = $analysis['reading_level'];

            // Generate suggestions
            $suggestions = $this->generateReadingLevelSuggestions($analysis, $currentLevel, $targetLevel);

            return response()->json([
                'success' => true,
                'current_level' => $currentLevel,
                'target_level' => $targetLevel,
                'suggestions' => $suggestions,
                'analysis' => $analysis,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in reading level suggestions API: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating suggestions',
            ], 500);
        }
    }

    /**
     * Validate content for publication.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateContent(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:100000',
                'title' => 'required|string|max:255',
                'category_id' => 'required|integer|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid content data',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $content = $request->input('content');
            $title = $request->input('title');

            // Perform content validation
            $validation = $this->validateContentQuality($content, $title);

            return response()->json([
                'success' => true,
                'validation' => $validation,
                'is_valid' => $validation['overall_score'] >= 70,
                'message' => 'Content validation completed',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in content validation API: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while validating content',
            ], 500);
        }
    }

    /**
     * Get content optimization suggestions.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getOptimizationSuggestions(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:100000',
                'target_audience' => 'nullable|in:children,teens,adults,seniors',
                'content_type' => 'nullable|in:story,article,tutorial,review',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $content = $request->input('content');
            $targetAudience = $request->input('target_audience', 'adults');
            $contentType = $request->input('content_type', 'story');

            // Analyze content
            $analysis = $this->wordCountService->getRealTimeAnalysis($content);

            // Generate optimization suggestions
            $suggestions = $this->generateOptimizationSuggestions($analysis, $targetAudience, $contentType);

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
                'analysis' => $analysis,
                'message' => 'Optimization suggestions generated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in optimization suggestions API: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating suggestions',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Generate reading level suggestions.
     */
    private function generateReadingLevelSuggestions(array $analysis, string $currentLevel, string $targetLevel): array
    {
        $suggestions = [];

        if ($currentLevel === $targetLevel) {
            $suggestions[] = [
                'type' => 'success',
                'message' => 'Content is at the target reading level',
                'icon' => 'check-circle',
            ];
            return $suggestions;
        }

        $wordCount = $analysis['word_count'];
        $avgSentenceLength = $analysis['average_words_per_sentence'];
        $complexityScore = $analysis['complexity_score'];

        // Suggestions based on target level
        switch ($targetLevel) {
            case 'beginner':
                if ($wordCount > 500) {
                    $suggestions[] = [
                        'type' => 'warning',
                        'message' => 'Consider reducing word count to 500 or fewer for beginner level',
                        'icon' => 'edit',
                        'action' => 'reduce_words',
                    ];
                }
                
                if ($avgSentenceLength > 15) {
                    $suggestions[] = [
                        'type' => 'info',
                        'message' => 'Use shorter sentences (10-15 words) for better readability',
                        'icon' => 'type',
                        'action' => 'shorten_sentences',
                    ];
                }
                break;

            case 'intermediate':
                if ($wordCount < 501 || $wordCount > 1500) {
                    $suggestions[] = [
                        'type' => 'info',
                        'message' => 'Intermediate level works best with 501-1500 words',
                        'icon' => 'target',
                        'action' => 'adjust_length',
                    ];
                }
                break;

            case 'advanced':
                if ($wordCount < 1501) {
                    $suggestions[] = [
                        'type' => 'info',
                        'message' => 'Advanced level typically requires 1500+ words',
                        'icon' => 'trending-up',
                        'action' => 'expand_content',
                    ];
                }
                
                if ($complexityScore < 0.5) {
                    $suggestions[] = [
                        'type' => 'suggestion',
                        'message' => 'Consider adding more complex vocabulary and sentence structures',
                        'icon' => 'book',
                        'action' => 'increase_complexity',
                    ];
                }
                break;
        }

        return $suggestions;
    }

    /**
     * Validate content quality.
     */
    private function validateContentQuality(string $content, string $title): array
    {
        $analysis = $this->wordCountService->getRealTimeAnalysis($content);
        $validation = [
            'checks' => [],
            'overall_score' => 0,
            'recommendations' => [],
        ];

        // Check minimum word count
        $wordCount = $analysis['word_count'];
        if ($wordCount >= 100) {
            $validation['checks']['word_count'] = [
                'status' => 'pass',
                'message' => 'Word count is adequate',
                'score' => 20,
            ];
        } else {
            $validation['checks']['word_count'] = [
                'status' => 'fail',
                'message' => 'Content is too short (minimum 100 words)',
                'score' => 0,
            ];
            $validation['recommendations'][] = 'Add more content to reach minimum word count';
        }

        // Check readability
        $readabilityScore = $analysis['readability_score'];
        if ($readabilityScore >= 50) {
            $validation['checks']['readability'] = [
                'status' => 'pass',
                'message' => 'Content is readable',
                'score' => 25,
            ];
        } else {
            $validation['checks']['readability'] = [
                'status' => 'warning',
                'message' => 'Content might be difficult to read',
                'score' => 15,
            ];
            $validation['recommendations'][] = 'Simplify sentences and vocabulary';
        }

        // Check paragraph structure
        $paragraphCount = $analysis['paragraph_count'];
        if ($paragraphCount >= 3) {
            $validation['checks']['structure'] = [
                'status' => 'pass',
                'message' => 'Content has good paragraph structure',
                'score' => 20,
            ];
        } else {
            $validation['checks']['structure'] = [
                'status' => 'warning',
                'message' => 'Content needs better paragraph structure',
                'score' => 10,
            ];
            $validation['recommendations'][] = 'Break content into more paragraphs';
        }

        // Check title relevance
        $titleLength = strlen($title);
        if ($titleLength >= 10 && $titleLength <= 100) {
            $validation['checks']['title'] = [
                'status' => 'pass',
                'message' => 'Title length is appropriate',
                'score' => 15,
            ];
        } else {
            $validation['checks']['title'] = [
                'status' => 'fail',
                'message' => 'Title should be 10-100 characters',
                'score' => 0,
            ];
            $validation['recommendations'][] = 'Adjust title length';
        }

        // Check sentence variety
        $avgSentenceLength = $analysis['average_words_per_sentence'];
        if ($avgSentenceLength >= 10 && $avgSentenceLength <= 20) {
            $validation['checks']['sentence_variety'] = [
                'status' => 'pass',
                'message' => 'Good sentence length variety',
                'score' => 20,
            ];
        } else {
            $validation['checks']['sentence_variety'] = [
                'status' => 'warning',
                'message' => 'Sentence length could be improved',
                'score' => 10,
            ];
            $validation['recommendations'][] = 'Vary sentence lengths for better flow';
        }

        // Calculate overall score
        $totalScore = array_sum(array_column($validation['checks'], 'score'));
        $validation['overall_score'] = $totalScore;

        return $validation;
    }

    /**
     * Generate optimization suggestions.
     */
    private function generateOptimizationSuggestions(array $analysis, string $targetAudience, string $contentType): array
    {
        $suggestions = [];

        // Audience-specific suggestions
        switch ($targetAudience) {
            case 'children':
                if ($analysis['word_count'] > 300) {
                    $suggestions[] = [
                        'category' => 'Length',
                        'type' => 'warning',
                        'message' => 'Consider shorter content for children (200-300 words)',
                        'priority' => 'high',
                    ];
                }
                
                if ($analysis['average_words_per_sentence'] > 10) {
                    $suggestions[] = [
                        'category' => 'Readability',
                        'type' => 'info',
                        'message' => 'Use shorter sentences (5-10 words) for children',
                        'priority' => 'medium',
                    ];
                }
                break;

            case 'teens':
                if ($analysis['word_count'] > 800) {
                    $suggestions[] = [
                        'category' => 'Length',
                        'type' => 'info',
                        'message' => 'Teen content works best under 800 words',
                        'priority' => 'medium',
                    ];
                }
                break;

            case 'adults':
                if ($analysis['word_count'] < 400) {
                    $suggestions[] = [
                        'category' => 'Length',
                        'type' => 'suggestion',
                        'message' => 'Adult content can be longer for more depth',
                        'priority' => 'low',
                    ];
                }
                break;
        }

        // Content type specific suggestions
        switch ($contentType) {
            case 'story':
                if ($analysis['paragraph_count'] < 5) {
                    $suggestions[] = [
                        'category' => 'Structure',
                        'type' => 'info',
                        'message' => 'Stories benefit from more paragraphs for pacing',
                        'priority' => 'medium',
                    ];
                }
                break;

            case 'tutorial':
                if ($analysis['sentence_count'] < 10) {
                    $suggestions[] = [
                        'category' => 'Detail',
                        'type' => 'warning',
                        'message' => 'Tutorials need more detailed explanations',
                        'priority' => 'high',
                    ];
                }
                break;
        }

        // General optimization suggestions
        if ($analysis['readability_score'] < 60) {
            $suggestions[] = [
                'category' => 'Readability',
                'type' => 'warning',
                'message' => 'Improve readability by simplifying complex sentences',
                'priority' => 'high',
            ];
        }

        if ($analysis['unique_words_ratio'] < 0.4) {
            $suggestions[] = [
                'category' => 'Vocabulary',
                'type' => 'suggestion',
                'message' => 'Add more variety to vocabulary',
                'priority' => 'low',
            ];
        }

        return $suggestions;
    }
}