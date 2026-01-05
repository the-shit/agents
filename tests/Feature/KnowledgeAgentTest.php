<?php

declare(strict_types=1);

use App\Events\Verbs\Agents\AgentAttemptStarted;
use App\Events\Verbs\Agents\AgentIntentDeclared;
use App\Events\Verbs\Agents\AgentOutputValidated;
use App\Events\Verbs\Agents\PatternCaptured;
use App\Models\AgentIdentity;
use App\Services\Agents\KnowledgeAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Facades\Verbs;

uses(RefreshDatabase::class);

describe('Knowledge Agent', function (): void {
    it('can query executions by intent pattern', function (): void {
        $event1 = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        $event2 = AgentIntentDeclared::fire(
            agent_id: 'test-agent',
            intent: 'run unit tests',
        );

        Verbs::commit();

        $knowledge = new KnowledgeAgent;
        $results = $knowledge->queryByIntent('validate code style');

        expect($results)->toHaveCount(1)
            ->and($results->first()['execution_id'])->toBe($event1->execution_id)
            ->and($results->first()['intent'])->toBe('validate code style');
    });

    it('can get full execution history', function (): void {
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
            passed: true,
            execution_id: $intentEvent->execution_id,
        );

        Verbs::commit();

        $knowledge = new KnowledgeAgent;
        $history = $knowledge->getExecutionHistory($intentEvent->execution_id);

        expect($history)->toHaveCount(3)
            ->and($history->pluck('type')->toArray())->toBe([
                'AgentIntentDeclared',
                'AgentAttemptStarted',
                'AgentOutputValidated',
            ]);
    });

    it('can get current execution state', function (): void {
        $event = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        Verbs::commit();

        $knowledge = new KnowledgeAgent;
        $state = $knowledge->getExecutionState($event->execution_id);

        expect($state)->not->toBeNull()
            ->and($state->agent_id)->toBe('lint-agent')
            ->and($state->intent)->toBe('validate code style')
            ->and($state->status)->toBe('declared');
    });

    it('can find successful approaches for an intent', function (): void {
        // Create a successful execution
        $intentEvent = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'fix linting errors',
        );

        $attemptEvent = AgentAttemptStarted::fire(
            agent_id: 'lint-agent',
            action: 'running pint --dirty',
            execution_id: $intentEvent->execution_id,
        );

        AgentOutputValidated::fire(
            attempt_id: $attemptEvent->attempt_id,
            passed: true,
            execution_id: $intentEvent->execution_id,
        );

        // Create a failed execution (should not be returned)
        $failedIntent = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'fix linting errors',
        );

        $failedAttempt = AgentAttemptStarted::fire(
            agent_id: 'lint-agent',
            action: 'running pint',
            execution_id: $failedIntent->execution_id,
        );

        AgentOutputValidated::fire(
            attempt_id: $failedAttempt->attempt_id,
            passed: false,
            execution_id: $failedIntent->execution_id,
        );

        Verbs::commit();

        $knowledge = new KnowledgeAgent;
        $approaches = $knowledge->findSuccessfulApproaches('linting');

        expect($approaches)->toHaveCount(1)
            ->and($approaches->first()['execution_id'])->toBe($intentEvent->execution_id)
            ->and($approaches->first()['success'])->toBeTrue();
    });

    it('can query knowledge patterns', function (): void {
        PatternCaptured::fire(
            intent_pattern: 'fix linting errors',
            approach: 'use pint --dirty for incremental fixes',
            success_rate: 0.75,
            example_event_ids: ['event-123'],
        );

        PatternCaptured::fire(
            intent_pattern: 'run tests',
            approach: 'use pest --parallel',
            success_rate: 0.90,
            example_event_ids: ['event-456'],
        );

        Verbs::commit();

        $knowledge = new KnowledgeAgent;
        $patterns = $knowledge->queryPatterns('linting');

        expect($patterns)->toHaveCount(1)
            ->and($patterns->first()['intent_pattern'])->toBe('fix linting errors')
            ->and($patterns->first()['success_rate'])->toBe(0.75);
    });

    it('can get active agents', function (): void {
        AgentIdentity::create([
            'agent_id' => 'lint-agent',
            'name' => 'Lint Agent',
            'role' => 'VALIDATION',
            'capabilities' => ['pint', 'phpstan'],
            'status' => 'active',
        ]);

        AgentIdentity::create([
            'agent_id' => 'test-agent',
            'name' => 'Test Agent',
            'role' => 'VALIDATION',
            'capabilities' => ['pest', 'phpunit'],
            'status' => 'idle',
        ]);

        $knowledge = new KnowledgeAgent;
        $activeAgents = $knowledge->getActiveAgents();

        expect($activeAgents)->toHaveCount(1)
            ->and($activeAgents->first()->agent_id)->toBe('lint-agent');
    });

    it('can provide context for agent decision making', function (): void {
        // Create some historical data
        $intentEvent = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        AgentOutputValidated::fire(
            attempt_id: 'test-attempt',
            passed: true,
            execution_id: $intentEvent->execution_id,
        );

        PatternCaptured::fire(
            intent_pattern: 'validate code',
            approach: 'use pint with PSR-12',
            success_rate: 0.85,
        );

        AgentIdentity::create([
            'agent_id' => 'lint-agent',
            'name' => 'Lint Agent',
            'role' => 'VALIDATION',
            'capabilities' => ['pint'],
            'status' => 'active',
        ]);

        Verbs::commit();

        $knowledge = new KnowledgeAgent;
        $context = $knowledge->getContextForAgent('lint-agent', 'validate code');

        expect($context)->toHaveKeys(['similar_executions', 'patterns', 'active_agents', 'timestamp'])
            ->and($context['active_agents'])->toHaveCount(1)
            ->and($context['patterns'])->toHaveCount(1);
    });

    it('can monitor ongoing agent coordination', function (): void {
        AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'validate code style',
        );

        $intentEvent2 = AgentIntentDeclared::fire(
            agent_id: 'test-agent',
            intent: 'run tests',
        );

        AgentOutputValidated::fire(
            attempt_id: 'test-attempt',
            passed: true,
            execution_id: $intentEvent2->execution_id,
        );

        Verbs::commit();

        $knowledge = new KnowledgeAgent;
        $status = $knowledge->getCoordinationStatus();

        expect($status)->toHaveKeys(['total_executions', 'by_status', 'executions'])
            ->and($status['total_executions'])->toBe(2)
            ->and($status['by_status'])->toHaveKey('declared')
            ->and($status['by_status'])->toHaveKey('completed');
    });
});
