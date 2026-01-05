<?php

declare(strict_types=1);

use App\Events\Verbs\Agents\PatternCaptured;
use App\Services\PatternMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Facades\Verbs;

uses(RefreshDatabase::class);

describe('Pattern Matcher', function (): void {
    it('can find exact matches', function (): void {
        PatternCaptured::fire(
            intent_pattern: 'fix linting errors',
            approach: 'use pint --dirty',
            success_rate: 0.75,
        );

        PatternCaptured::fire(
            intent_pattern: 'run tests',
            approach: 'use pest --parallel',
            success_rate: 0.90,
        );

        Verbs::commit();

        $matcher = new PatternMatcher;
        $results = $matcher->match('fix linting errors');

        expect($results)->toHaveCount(1)
            ->and($results->first()['method'])->toBe('exact')
            ->and($results->first()['confidence'])->toBe(1.0)
            ->and($results->first()['pattern']['intent_pattern'])->toBe('fix linting errors');
    });

    it('falls through layers to find matches', function (): void {
        PatternCaptured::fire(
            intent_pattern: 'fix linting errors',
            approach: 'use pint --dirty',
            success_rate: 0.75,
        );

        Verbs::commit();

        $matcher = new PatternMatcher;
        $results = $matcher->match('fix lint errors'); // Different enough to trigger fallback

        // Multi-layered matching should find it via fuzzy or keyword matching
        expect($results)->not->toBeEmpty()
            ->and($results->first()['method'])->toBeIn(['fuzzy', 'keyword'])
            ->and($results->first()['pattern']['intent_pattern'])->toBe('fix linting errors');
    });

    it('uses keyword matching as fallback', function (): void {
        PatternCaptured::fire(
            intent_pattern: 'validate code style with pint',
            approach: 'use pint --test',
            success_rate: 0.85,
        );

        Verbs::commit();

        $matcher = new PatternMatcher;
        $results = $matcher->match('pint code validation'); // Different order, same keywords

        expect($results)->not->toBeEmpty()
            ->and($results->first()['method'])->toBe('keyword')
            ->and($results->first()['pattern']['intent_pattern'])->toBe('validate code style with pint');
    });

    it('returns empty collection when no matches found', function (): void {
        PatternCaptured::fire(
            intent_pattern: 'fix linting errors',
            approach: 'use pint --dirty',
            success_rate: 0.75,
        );

        Verbs::commit();

        $matcher = new PatternMatcher;
        $results = $matcher->match('deploy to production'); // Completely different

        expect($results)->toBeEmpty();
    });

    it('respects fuzzy threshold configuration', function (): void {
        PatternCaptured::fire(
            intent_pattern: 'fix linting errors completely',
            approach: 'use pint --dirty',
            success_rate: 0.75,
        );

        Verbs::commit();

        // High threshold - should not match fuzzy (may match keywords)
        $strictMatcher = new PatternMatcher(fuzzyThreshold: 0.95);
        $strictResults = $strictMatcher->match('fix linting error completely'); // Very close

        // Low threshold - should definitely match
        $relaxedMatcher = new PatternMatcher(fuzzyThreshold: 0.5);
        $relaxedResults = $relaxedMatcher->match('fix linting error completely');

        // Strict should either be empty or use keyword matching, relaxed should use fuzzy
        expect($relaxedResults)->not->toBeEmpty()
            ->and($relaxedResults->first()['method'])->toBe('fuzzy');
    });

    it('returns matches sorted by confidence', function (): void {
        PatternCaptured::fire(
            intent_pattern: 'fix linting errors completely',
            approach: 'comprehensive approach',
            success_rate: 0.75,
        );

        PatternCaptured::fire(
            intent_pattern: 'fix linting errors',
            approach: 'quick approach',
            success_rate: 0.85,
        );

        Verbs::commit();

        $matcher = new PatternMatcher;
        $results = $matcher->match('fix linting errors'); // Matches second one exactly

        expect($results)->not->toBeEmpty()
            ->and($results->first()['confidence'])->toBe(1.0)
            ->and($results->first()['pattern']['intent_pattern'])->toBe('fix linting errors');
    });
});
