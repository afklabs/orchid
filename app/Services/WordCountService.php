<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Str;

/**
 * Word Count Service
 * 
 * Advanced content analysis service providing comprehensive word count analytics,
 * reading level determination, and content metrics calculation.
 * 
 * Features:
 * - Accurate word counting with multilingual support
 * - Reading level determination (beginner/intermediate/advanced)
 * - Reading time estimation
 * - Content complexity analysis
 * - Sentence and paragraph counting
 * - Real-time content analysis
 * - Performance optimizations with caching
 * 
 * @package App\Services
 * @author  Development Team
 * @version 1.0.0
 * @since   2025-01-01
 */
class WordCountService
{
    /*
    |--------------------------------------------------------------------------
    | CONSTANTS & CONFIGURATION
    |--------------------------------------------------------------------------
    */

    /**
     * Reading speed constants (words per minute).
     */
    private const READING_SPEEDS = [
        'slow' => 200,
        'average' => 250,
        'fast' => 300,
    ];

    /**
     * Reading level thresholds based on word count.
     */
    private const READING_LEVELS = [
        'beginner' => ['min' => 0, 'max' => 500],
        'intermediate' => ['min' => 501, 'max' => 1500],
        'advanced' => ['min' => 1501, 'max' => PHP_INT_MAX],
    ];

    /**
     * Complexity factors for reading level calculation.
     */
    private const COMPLEXITY_FACTORS = [
        'avg_sentence_length' => 0.3,
        'avg_word_length' => 0.2,
        'paragraph_density' => 0.2,
        'unique_words_ratio' => 0.3,
    ];

    /**
     * Cache TTL in seconds.
     */
    private const CACHE_TTL = 3600; // 1 hour

    /*
    |--------------------------------------------------------------------------
    | MAIN ANALYSIS METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Analyze content and return comprehensive metrics.
     */
    public function analyzeContent(string $content): array
    {
        // Remove HTML tags and normalize content
        $cleanContent = $this->cleanContent($content);
        
        if (empty($cleanContent)) {
            return $this->getEmptyAnalysis();
        }

        // Generate cache key
        $cacheKey = 'word_count_analysis.' . md5($cleanContent);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($cleanContent) {
            return $this->performAnalysis($cleanContent);
        });
    }

    /**
     * Calculate word count only (lightweight operation).
     */
    public function getWordCount(string $content): int
    {
        $cleanContent = $this->cleanContent($content);
        
        if (empty($cleanContent)) {
            return 0;
        }

        // Use PHP's str_word_count for better accuracy
        return str_word_count($cleanContent, 0, 'أبتثجحخدذرزسشصضطظعغفقكلمنهوي');
    }

    /**
     * Determine reading level based on content analysis.
     */
    public function getReadingLevel(string $content): string
    {
        $analysis = $this->analyzeContent($content);
        return $this->calculateReadingLevel($analysis);
    }

    /**
     * Estimate reading time for content.
     */
    public function estimateReadingTime(string $content, string $speed = 'average'): int
    {
        $wordCount = $this->getWordCount($content);
        $wordsPerMinute = self::READING_SPEEDS[$speed] ?? self::READING_SPEEDS['average'];
        
        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    /**
     * Get content readability score.
     */
    public function getReadabilityScore(string $content): float
    {
        $analysis = $this->analyzeContent($content);
        return $this->calculateReadabilityScore($analysis);
    }

    /*
    |--------------------------------------------------------------------------
    | BATCH PROCESSING METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Analyze multiple content pieces at once.
     */
    public function analyzeBatch(array $contentList): array
    {
        $results = [];
        
        foreach ($contentList as $key => $content) {
            try {
                $results[$key] = $this->analyzeContent($content);
            } catch (\Exception $e) {
                Log::warning('Error analyzing content batch item: ' . $e->getMessage(), [
                    'key' => $key,
                    'content_length' => strlen($content),
                ]);
                
                $results[$key] = $this->getEmptyAnalysis();
            }
        }
        
        return $results;
    }

    /**
     * Update word counts for all stories.
     */
    public function updateAllStoryWordCounts(): array
    {
        try {
            $stories = \App\Models\Story::select('id', 'content', 'word_count')->get();
            $updated = 0;
            $errors = 0;

            foreach ($stories as $story) {
                try {
                    $analysis = $this->analyzeContent($story->content);
                    
                    $story->update([
                        'word_count' => $analysis['word_count'],
                        'reading_level' => $analysis['reading_level'],
                        'reading_time_minutes' => $analysis['estimated_reading_time'],
                    ]);
                    
                    $updated++;
                } catch (\Exception $e) {
                    Log::error('Error updating story word count: ' . $e->getMessage(), [
                        'story_id' => $story->id,
                    ]);
                    $errors++;
                }
            }

            return [
                'total_stories' => $stories->count(),
                'updated' => $updated,
                'errors' => $errors,
                'success' => $errors === 0,
            ];

        } catch (\Exception $e) {
            Log::error('Error in batch word count update: ' . $e->getMessage());
            
            return [
                'total_stories' => 0,
                'updated' => 0,
                'errors' => 1,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REAL-TIME ANALYSIS METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Get real-time analysis for content editor.
     */
    public function getRealTimeAnalysis(string $content): array
    {
        // Skip caching for real-time analysis
        $cleanContent = $this->cleanContent($content);
        
        if (empty($cleanContent)) {
            return $this->getEmptyAnalysis();
        }

        return $this->performAnalysis($cleanContent);
    }

    /**
     * Get content statistics for progress tracking.
     */
    public function getContentProgress(string $content, int $targetWords = 1000): array
    {
        $analysis = $this->getRealTimeAnalysis($content);
        $wordCount = $analysis['word_count'];
        
        return [
            'current_words' => $wordCount,
            'target_words' => $targetWords,
            'progress_percentage' => min(100, round(($wordCount / $targetWords) * 100, 1)),
            'words_remaining' => max(0, $targetWords - $wordCount),
            'reading_level' => $analysis['reading_level'],
            'estimated_reading_time' => $analysis['estimated_reading_time'],
            'is_target_met' => $wordCount >= $targetWords,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPER METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Clean and normalize content for analysis.
     */
    private function cleanContent(string $content): string
    {
        // Remove HTML tags
        $content = strip_tags($content);
        
        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove extra punctuation
        $content = preg_replace('/[^\w\s\.\!\?،؛\u0600-\u06FF]/u', '', $content);
        
        // Trim and return
        return trim($content);
    }

    /**
     * Perform comprehensive content analysis.
     */
    private function performAnalysis(string $content): array
    {
        // Basic counts
        $wordCount = $this->calculateWordCount($content);
        $characterCount = mb_strlen($content, 'UTF-8');
        $paragraphCount = $this->calculateParagraphCount($content);
        $sentenceCount = $this->calculateSentenceCount($content);
        
        // Advanced metrics
        $averageWordsPerSentence = $sentenceCount > 0 ? round($wordCount / $sentenceCount, 1) : 0;
        $averageWordsPerParagraph = $paragraphCount > 0 ? round($wordCount / $paragraphCount, 1) : 0;
        $averageCharactersPerWord = $wordCount > 0 ? round($characterCount / $wordCount, 1) : 0;
        
        // Unique words analysis
        $uniqueWordsData = $this->analyzeUniqueWords($content);
        
        // Reading level calculation
        $complexityScore = $this->calculateComplexityScore([
            'avg_sentence_length' => $averageWordsPerSentence,
            'avg_word_length' => $averageCharactersPerWord,
            'paragraph_density' => $averageWordsPerParagraph,
            'unique_words_ratio' => $uniqueWordsData['unique_ratio'],
        ]);
        
        $readingLevel = $this->determineReadingLevel($wordCount, $complexityScore);
        
        // Reading time estimation
        $estimatedReadingTime = $this->calculateReadingTime($wordCount);
        
        return [
            'word_count' => $wordCount,
            'character_count' => $characterCount,
            'paragraph_count' => $paragraphCount,
            'sentence_count' => $sentenceCount,
            'reading_level' => $readingLevel,
            'estimated_reading_time' => $estimatedReadingTime,
            'average_words_per_sentence' => $averageWordsPerSentence,
            'average_words_per_paragraph' => $averageWordsPerParagraph,
            'average_characters_per_word' => $averageCharactersPerWord,
            'unique_words_count' => $uniqueWordsData['unique_count'],
            'unique_words_ratio' => $uniqueWordsData['unique_ratio'],
            'complexity_score' => $complexityScore,
            'readability_score' => $this->calculateReadabilityScore([
                'word_count' => $wordCount,
                'sentence_count' => $sentenceCount,
                'complexity_score' => $complexityScore,
            ]),
            'content_density' => $this->calculateContentDensity($content),
            'analysis_timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Calculate accurate word count.
     */
    private function calculateWordCount(string $content): int
    {
        // Support for Arabic and English
        $arabicPattern = '/[\u0600-\u06FF]+/u';
        $englishPattern = '/[a-zA-Z]+/';
        
        $arabicMatches = preg_match_all($arabicPattern, $content);
        $englishMatches = str_word_count($content, 0);
        
        return $arabicMatches + $englishMatches;
    }

    /**
     * Calculate paragraph count.
     */
    private function calculateParagraphCount(string $content): int
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($content));
        return count(array_filter($paragraphs, 'trim'));
    }

    /**
     * Calculate sentence count.
     */
    private function calculateSentenceCount(string $content): int
    {
        // Support for Arabic and English punctuation
        $sentences = preg_split('/[.!?؟।]/', $content);
        return count(array_filter($sentences, 'trim'));
    }

    /**
     * Analyze unique words in content.
     */
    private function analyzeUniqueWords(string $content): array
    {
        $words = str_word_count(strtolower($content), 1, 'أبتثجحخدذرزسشصضطظعغفقكلمنهوي');
        $totalWords = count($words);
        $uniqueWords = count(array_unique($words));
        
        return [
            'unique_count' => $uniqueWords,
            'unique_ratio' => $totalWords > 0 ? round($uniqueWords / $totalWords, 2) : 0,
        ];
    }

    /**
     * Calculate complexity score.
     */
    private function calculateComplexityScore(array $metrics): float
    {
        $score = 0;
        
        // Sentence length factor
        $sentenceLength = $metrics['avg_sentence_length'];
        if ($sentenceLength > 20) {
            $score += 0.3;
        } elseif ($sentenceLength > 15) {
            $score += 0.2;
        } elseif ($sentenceLength > 10) {
            $score += 0.1;
        }
        
        // Word length factor
        $wordLength = $metrics['avg_word_length'];
        if ($wordLength > 6) {
            $score += 0.2;
        } elseif ($wordLength > 5) {
            $score += 0.1;
        }
        
        // Paragraph density factor
        $paragraphDensity = $metrics['paragraph_density'];
        if ($paragraphDensity > 100) {
            $score += 0.2;
        } elseif ($paragraphDensity > 75) {
            $score += 0.1;
        }
        
        // Unique words ratio factor
        $uniqueRatio = $metrics['unique_words_ratio'];
        if ($uniqueRatio > 0.8) {
            $score += 0.3;
        } elseif ($uniqueRatio > 0.6) {
            $score += 0.2;
        } elseif ($uniqueRatio > 0.4) {
            $score += 0.1;
        }
        
        return min(1.0, $score);
    }

    /**
     * Determine reading level based on word count and complexity.
     */
    private function determineReadingLevel(int $wordCount, float $complexityScore): string
    {
        // Base level on word count
        $baseLevel = 'intermediate';
        
        foreach (self::READING_LEVELS as $level => $range) {
            if ($wordCount >= $range['min'] && $wordCount <= $range['max']) {
                $baseLevel = $level;
                break;
            }
        }
        
        // Adjust based on complexity
        if ($complexityScore > 0.7) {
            if ($baseLevel === 'beginner') {
                $baseLevel = 'intermediate';
            } elseif ($baseLevel === 'intermediate') {
                $baseLevel = 'advanced';
            }
        } elseif ($complexityScore < 0.3) {
            if ($baseLevel === 'advanced') {
                $baseLevel = 'intermediate';
            } elseif ($baseLevel === 'intermediate') {
                $baseLevel = 'beginner';
            }
        }
        
        return $baseLevel;
    }

    /**
     * Calculate reading time in minutes.
     */
    private function calculateReadingTime(int $wordCount): int
    {
        $averageSpeed = self::READING_SPEEDS['average'];
        return max(1, (int) ceil($wordCount / $averageSpeed));
    }

    /**
     * Calculate readability score.
     */
    private function calculateReadabilityScore(array $analysis): float
    {
        $wordCount = $analysis['word_count'] ?? 0;
        $sentenceCount = $analysis['sentence_count'] ?? 1;
        $complexityScore = $analysis['complexity_score'] ?? 0;
        
        if ($wordCount === 0) {
            return 0;
        }
        
        // Simple readability formula
        $averageWordsPerSentence = $wordCount / $sentenceCount;
        $readabilityScore = 100 - (1.015 * $averageWordsPerSentence) - (84.6 * $complexityScore);
        
        return max(0, min(100, round($readabilityScore, 1)));
    }

    /**
     * Calculate content density.
     */
    private function calculateContentDensity(string $content): float
    {
        $contentLength = mb_strlen($content, 'UTF-8');
        $whitespaceCount = substr_count($content, ' ') + substr_count($content, "\n");
        
        if ($contentLength === 0) {
            return 0;
        }
        
        return round((1 - ($whitespaceCount / $contentLength)) * 100, 1);
    }

    /**
     * Calculate reading level from analysis.
     */
    private function calculateReadingLevel(array $analysis): string
    {
        return $analysis['reading_level'] ?? 'intermediate';
    }

    /**
     * Get empty analysis structure.
     */
    private function getEmptyAnalysis(): array
    {
        return [
            'word_count' => 0,
            'character_count' => 0,
            'paragraph_count' => 0,
            'sentence_count' => 0,
            'reading_level' => 'intermediate',
            'estimated_reading_time' => 1,
            'average_words_per_sentence' => 0,
            'average_words_per_paragraph' => 0,
            'average_characters_per_word' => 0,
            'unique_words_count' => 0,
            'unique_words_ratio' => 0,
            'complexity_score' => 0,
            'readability_score' => 0,
            'content_density' => 0,
            'analysis_timestamp' => now()->toISOString(),
        ];
    }
}