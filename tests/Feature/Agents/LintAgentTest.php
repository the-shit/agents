<?php

declare(strict_types=1);

use App\Events\Verbs\Agents\AgentAttemptStarted;
use App\Events\Verbs\Agents\AgentIntentDeclared;
use App\Events\Verbs\Agents\AgentOutputValidated;
use App\Models\AgentIdentity;
use App\Services\Agents\KnowledgeAgent;
use App\Services\Agents\LintAgent;
use App\States\AgentExecutionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Facades\Verbs;

uses(RefreshDatabase::class);

describe('Lint Agent', function (): void {
    beforeEach(function (): void {
        // Create lint agent identity
        AgentIdentity::create([
            'agent_id' => 'lint-agent',
            'name' => 'Lint Agent',
            'role' => 'VALIDATION',
            'capabilities' => ['pint', 'phpstan', 'psalm'],
            'status' => 'active',
        ]);
    });

    it('can declare lint intent', function (): void {
        $agent = new LintAgent;
        $executionId = $agent->declareIntent(
            intent: 'validate code style',
            context: ['files' => ['app/Models/User.php']]
        );

        Verbs::commit();

        $state = AgentExecutionState::load($executionId);

        expect($state)->toBeInstanceOf(AgentExecutionState::class)
            ->and($state->agent_id)->toBe('lint-agent')
            ->and($state->intent)->toBe('validate code style')
            ->and($state->status)->toBe('declared');
    });

    it('can execute pint validation', function (): void {
        $agent = new LintAgent;
        $executionId = $agent->declareIntent('run pint validation');

        $attemptId = $agent->startAttempt(
            executionId: $executionId,
            action: 'running pint --test',
            parameters: ['files' => ['app']]
        );

        Verbs::commit();

        $state = AgentExecutionState::load($executionId);

        expect($state->status)->toBe('attempting')
            ->and($state->current_attempt_id)->toBe($attemptId);
    });

    it('can produce lint output', function (): void {
        $agent = new LintAgent;
        $executionId = $agent->declareIntent('validate code');
        $attemptId = $agent->startAttempt(
            executionId: $executionId,
            action: 'running pint'
        );

        $agent->produceOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            output: 'All files pass PSR-12 validation',
            artifacts: ['violations' => 0, 'files_checked' => 10]
        );

        Verbs::commit();

        $state = AgentExecutionState::load($executionId);

        expect($state->outputs)->toHaveCount(1)
            ->and($state->outputs[0]['output'])->toBe('All files pass PSR-12 validation')
            ->and($state->outputs[0]['artifacts']['violations'])->toBe(0);
    });

    it('can validate successful lint execution', function (): void {
        $agent = new LintAgent;
        $executionId = $agent->declareIntent('run pint');
        $attemptId = $agent->startAttempt($executionId, 'pint --test');

        $agent->produceOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            output: 'No violations'
        );

        $agent->validateOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            passed: true,
            criteria: ['psr12_compliant', 'no_violations']
        );

        Verbs::commit();

        $state = AgentExecutionState::load($executionId);

        expect($state->status)->toBe('completed')
            ->and($state->validations)->toHaveCount(1)
            ->and($state->validations[0]['passed'])->toBeTrue()
            ->and($state->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('marks execution as failed when validation fails', function (): void {
        $agent = new LintAgent;
        $executionId = $agent->declareIntent('validate code');
        $attemptId = $agent->startAttempt($executionId, 'pint --test');

        $agent->validateOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            passed: false,
            errors: ['PSR-12 violations found', '5 files need fixing']
        );

        Verbs::commit();

        $state = AgentExecutionState::load($executionId);

        expect($state->status)->toBe('failed')
            ->and($state->validations[0]['passed'])->toBeFalse()
            ->and($state->validations[0]['errors'])->toHaveCount(2);
    });

    it('can query knowledge agent for similar lint patterns', function (): void {
        // Create historical pattern
        $pastExecution = AgentIntentDeclared::fire(
            agent_id: 'lint-agent',
            intent: 'fix pint violations',
        );

        $pastAttempt = AgentAttemptStarted::fire(
            agent_id: 'lint-agent',
            action: 'running pint --dirty',
            execution_id: $pastExecution->execution_id,
        );

        AgentOutputValidated::fire(
            attempt_id: $pastAttempt->attempt_id,
            passed: true,
            execution_id: $pastExecution->execution_id,
        );

        Verbs::commit();

        // Query for similar patterns
        $knowledge = new KnowledgeAgent;
        $context = $knowledge->getContextForAgent('lint-agent', 'fix pint');

        expect($context)->toHaveKeys(['similar_executions', 'patterns', 'active_agents'])
            ->and($context['similar_executions'])->not->toBeEmpty();
    });

    it('can coordinate with other agents via stop signal', function (): void {
        $agent = new LintAgent;
        $executionId = $agent->declareIntent('validate before commit');
        $attemptId = $agent->startAttempt($executionId, 'pint --test');

        $agent->validateOutput(
            attemptId: $attemptId,
            executionId: $executionId,
            passed: true
        );

        $nextAgent = $agent->sendStopSignal(
            reason: 'completed',
            nextAgent: 'source-control-agent',
            context: ['files_validated' => 10, 'violations' => 0]
        );

        Verbs::commit();

        expect($nextAgent)->toBe('source-control-agent');
    });

    it('updates agent identity status during execution', function (): void {
        $agent = new LintAgent;
        $executionId = $agent->declareIntent('validate code');

        $agent->startAttempt($executionId, 'pint --test');

        Verbs::commit();

        $identity = AgentIdentity::where('agent_id', 'lint-agent')->first();

        expect($identity->status)->toBe('active')
            ->and($identity->last_active_at)->not->toBeNull();
    });
});
