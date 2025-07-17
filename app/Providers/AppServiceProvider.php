<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\{WordCountService, ReadingAnalyticsService};

/**
 * Application Service Provider - Service Registration
 * 
 * Registers word count and reading analytics services
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Word Count Service
        $this->app->singleton(WordCountService::class, function ($app) {
            return new WordCountService();
        });

        // Register Reading Analytics Service
        $this->app->singleton(ReadingAnalyticsService::class, function ($app) {
            return new ReadingAnalyticsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom validation rules
        \Illuminate\Support\Facades\Validator::extend('word_count_min', function ($attribute, $value, $parameters, $validator) {
            $wordCount = app(WordCountService::class)->getWordCount($value);
            return $wordCount >= (int) $parameters[0];
        });

        \Illuminate\Support\Facades\Validator::extend('reading_level', function ($attribute, $value, $parameters, $validator) {
            $readingLevel = app(WordCountService::class)->getReadingLevel($value);
            return in_array($readingLevel, $parameters);
        });

        // Register custom validation messages
        \Illuminate\Support\Facades\Validator::replacer('word_count_min', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':min', $parameters[0], 'The :attribute must contain at least :min words.');
        });

        \Illuminate\Support\Facades\Validator::replacer('reading_level', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':levels', implode(', ', $parameters), 'The :attribute must be at one of these reading levels: :levels.');
        });
    }
}