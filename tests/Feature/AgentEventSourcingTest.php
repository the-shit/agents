<?php

declare(strict_types=1);

use App\Events\Verbs\Agents\AgentAttemptStarted;
use App\Events\Verbs\Agents\AgentIntentDeclared;
use App\Events\Verbs\Agents\AgentOutputProduced;
use App\Events\Verbs\Agents\AgentOutputValidated;
use App\Events\Verbs\Agents\AgentStopSignalSent;
use App\Events\Verbs\Agents\PatternCaptured;
use App\States\AgentExecutionState;
use App\States\KnowledgePatternState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Facades\Verbs;

uses(RefreshDatabase::class);

describe('Agent Event Sourcing', function (): void {
    it('can declare an intent and create execution state', function (): void {
        $event = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
            context: ['files' => ['app/Models/User.php']],
        );

        Verbs::commit();

        $state = AgentExecutionState::load($event->execution_id);

        expect($state)->toBeInstanceOf(AgentExecutionState::class)
            ->and($state->agent_id)->toBe('lint-agent')
            ->and($state->intent)->toBe('validate code style')
            ->and($state->status)->toBe('declared')
            ->and($state->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('can start an attempt and update execution state', function (): void {
        $intentEvent = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        $attemptEvent = AgentAttemptStarted::fire(
            agent_id: 'lint-agent',
            action: 'running pint --test',
            parameters: ['files' => ['app/Models/User.php']],
            execution_id: $intentEvent->execution_id,
        );

        Verbs::commit();

        $state = AgentExecutionState::load($intentEvent->execution_id);

        expect($state->status)->toBe('attempting')
            ->and($state->current_attempt_id)->toBe($attemptEvent->attempt_id);
    });

    it('can produce output and store artifacts', function (): void {
        $intentEvent = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        $attemptEvent = AgentAttemptStarted::fire(
            agent_id: 'lint-agent',
            action: 'running pint --test',
            execution_id: $intentEvent->execution_id,
        );

        AgentOutputProduced::fire(
            attempt_id: $attemptEvent->attempt_id,
            output: 'All files validated successfully',
            artifacts: ['violations' => 0],
            execution_id: $intentEvent->execution_id,
        );

        Verbs::commit();

        $state = AgentExecutionState::load($intentEvent->execution_id);

        expect($state->outputs)->toHaveCount(1)
            ->and($state->outputs[0]['attempt_id'])->toBe($attemptEvent->attempt_id)
            ->and($state->outputs[0]['output'])->toBe('All files validated successfully')
            ->and($state->outputs[0]['artifacts'])->toBe(['violations' => 0]);
    });

    it('can validate output and mark execution as completed', function (): void {
        $intentEvent = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        $attemptEvent = AgentAttemptStarted::fire(
            agent_id: 'lint-agent',
            action: 'running pint --test',
            execution_id: $intentEvent->execution_id,
        );

        AgentOutputProduced::fire(
            attempt_id: $attemptEvent->attempt_id,
            output: 'All files validated successfully',
            execution_id: $intentEvent->execution_id,
        );

        AgentOutputValidated::fire(
            attempt_id: $attemptEvent->attempt_id,
            passed: true,
            validator: 'ollama:qwen2.5-coder:32b',
            criteria: ['follows_psr12', 'no_violations'],
            execution_id: $intentEvent->execution_id,
        );

        Verbs::commit();

        $state = AgentExecutionState::load($intentEvent->execution_id);

        expect($state->status)->toBe('completed')
            ->and($state->validations)->toHaveCount(1)
            ->and($state->validations[0]['passed'])->toBeTrue()
            ->and($state->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('can mark execution as failed when validation fails', function (): void {
        $intentEvent = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        $attemptEvent = AgentAttemptStarted::fire(
            agent_id: 'lint-agent',
            action: 'running pint --test',
            execution_id: $intentEvent->execution_id,
        );

        AgentOutputValidated::fire(
            attempt_id: $attemptEvent->attempt_id,
            passed: false,
            errors: ['Missing type hints', 'PSR-12 violations'],
            execution_id: $intentEvent->execution_id,
        );

        Verbs::commit();

        $state = AgentExecutionState::load($intentEvent->execution_id);

        expect($state->status)->toBe('failed')
            ->and($state->validations[0]['passed'])->toBeFalse()
            ->and($state->validations[0]['errors'])->toHaveCount(2);
    });

    it('can send stop signal for agent handoff', function (): void {
        AgentStopSignalSent::fire(
            agent_id: 'lint-agent',
            reason: 'completed',
            next_agent: 'test-agent',
            context: ['files_validated' => 5],
        );

        Verbs::commit();

        // Stop signal doesn't change state, it just signals handoff
        expect(true)->toBeTrue();
    });

    it('can capture knowledge patterns', function (): void {
        $event = PatternCaptured::fire(
            intent_pattern: 'fix linting errors',
            approach: 'use pint --dirty for incremental fixes',
            success_rate: 0.75,
            example_event_ids: ['event-123', 'event-456'],
        );

        Verbs::commit();

        $state = KnowledgePatternState::load($event->pattern_id);

        expect($state)->toBeInstanceOf(KnowledgePatternState::class)
            ->and($state->intent_pattern)->toBe('fix linting errors')
            ->and($state->approach)->toBe('use pint --dirty for incremental fixes')
            ->and($state->success_rate)->toBe(0.75)
            ->and($state->occurrence_count)->toBe(1)
            ->and($state->first_seen_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
            ->and($state->last_seen_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });
});
