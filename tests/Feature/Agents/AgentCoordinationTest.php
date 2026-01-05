<?php

declare(strict_types=1);

use App\Events\Verbs\Agents\AgentStopSignalSent;
use App\Models\AgentIdentity;
use App\Services\Agents\KnowledgeAgent;
use App\Services\Agents\LintAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

uses(RefreshDatabase::class);

describe('Agent Coordination', function (): void {
    beforeEach(function (): void {
        // Create agent identities
        AgentIdentity::create([
            'agent_id' => 'lint-agent',
            'name' => 'Lint Agent',
            'role' => 'VALIDATION',
            'capabilities' => ['pint', 'phpstan'],
            'status' => 'active',
        ]);

        AgentIdentity::create([
            'agent_id' => 'source-control-agent',
            'name' => 'Source Control Agent',
            'role' => 'ATTEMPT',
            'capabilities' => ['git'],
            'status' => 'idle',
        ]);
    });

    it('coordinates full validation to commit flow', function (): void {
        $lintAgent = new LintAgent;

        // LintAgent validates code
        $executionId = $lintAgent->declareIntent('validate code style');
        $attemptId = $lintAgent->startAttempt($executionId, 'pint --test');

        $lintAgent->produceOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            output: 'All files pass validation'
        );

        $lintAgent->validateOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            passed: true,
            validator: 'lint-agent'
        );

        // Send handoff to source control
        $nextAgent = $lintAgent->sendStopSignal(
            reason: 'completed',
            nextAgent: 'source-control-agent',
            context: ['files_validated' => 10]
        );

        Verbs::commit();

        // Verify handoff signal was sent
        $stopSignal = VerbEvent::query()
            ->where('type', AgentStopSignalSent::class)
            ->where('data->next_agent', 'source-control-agent')
            ->latest()
            ->first();

        expect($nextAgent)->toBe('source-control-agent')
            ->and($stopSignal)->not->toBeNull()
            ->and($stopSignal->data['reason'])->toBe('completed')
            ->and($stopSignal->data['context']['files_validated'])->toBe(10);
    });

    it('allows knowledge agent to monitor coordination', function (): void {
        $lintAgent = new LintAgent;

        // Execute lint validation
        $executionId = $lintAgent->declareIntent('validate style');
        $attemptId = $lintAgent->startAttempt($executionId, 'pint');

        $lintAgent->validateOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            passed: true
        );

        Verbs::commit();

        // Knowledge Agent monitors status
        $knowledge = new KnowledgeAgent;
        $status = $knowledge->getCoordinationStatus();

        expect($status)->toHaveKeys(['total_executions', 'by_status', 'executions'])
            ->and($status['total_executions'])->toBe(1)
            ->and($status['by_status']['completed'])->toBe(1);
    });

    it('provides context from historical executions', function (): void {
        $lintAgent = new LintAgent;

        // Create historical successful execution
        $executionId1 = $lintAgent->declareIntent('fix pint violations');
        $attemptId1 = $lintAgent->startAttempt($executionId1, 'pint --dirty');
        $lintAgent->validateOutput($attemptId1, $executionId1, passed: true);

        Verbs::commit();

        // Query for context on new similar task
        $knowledge = new KnowledgeAgent;
        $context = $knowledge->getContextForAgent('lint-agent', 'fix pint');

        expect($context)->toHaveKeys(['similar_executions', 'patterns', 'active_agents'])
            ->and($context['similar_executions'])->toHaveCount(1)
            ->and($context['active_agents'])->toBeArray();
    });

    it('tracks agent status through execution lifecycle', function (): void {
        $lintAgent = new LintAgent;

        // Start execution
        $executionId = $lintAgent->declareIntent('validate');

        $identity = AgentIdentity::where('agent_id', 'lint-agent')->first();
        expect($identity->status)->toBe('active');

        // Complete execution
        $attemptId = $lintAgent->startAttempt($executionId, 'pint');
        $lintAgent->validateOutput($attemptId, $executionId, passed: true);

        Verbs::commit();

        $identity->refresh();
        expect($identity->status)->toBe('idle');
    });

    it('handles validation failure without handoff', function (): void {
        $lintAgent = new LintAgent;

        $executionId = $lintAgent->declareIntent('validate code');
        $attemptId = $lintAgent->startAttempt($executionId, 'pint --test');

        $lintAgent->validateOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            passed: false,
            errors: ['PSR-12 violations found']
        );

        Verbs::commit();

        // No stop signal should be sent for failed validation
        $stopSignals = VerbEvent::query()
            ->where('type', AgentStopSignalSent::class)
            ->where('data->agent_id', 'lint-agent')
            ->get();

        expect($stopSignals)->toBeEmpty();

        // Agent should remain active (waiting for retry)
        $identity = AgentIdentity::where('agent_id', 'lint-agent')->first();
        expect($identity->status)->toBe('active');
    });

    it('enables parallel agent monitoring via knowledge queries', function (): void {
        $lintAgent = new LintAgent;

        // Simulate multiple agents working
        $exec1 = $lintAgent->declareIntent('validate project A');
        $exec2 = $lintAgent->declareIntent('validate project B');

        $attempt1 = $lintAgent->startAttempt($exec1, 'pint project-a');
        $attempt2 = $lintAgent->startAttempt($exec2, 'pint project-b');

        Verbs::commit();

        // Knowledge agent can see all ongoing work
        $knowledge = new KnowledgeAgent;
        $status = $knowledge->getCoordinationStatus();

        expect($status['total_executions'])->toBe(2)
            ->and($status['by_status']['attempting'])->toBe(2);
    });
});
