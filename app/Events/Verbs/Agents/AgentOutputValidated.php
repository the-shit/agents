<?php

declare(strict_types=1);

namespace App\Events\Verbs\Agents;

use App\States\AgentExecutionState;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

class AgentOutputValidated extends Event
{
    #[StateId(AgentExecutionState::class)]
    public ?string $execution_id = null;

    public string $attempt_id;

    public bool $passed;

    public string $validator;

    public array $criteria;

    public array $errors;

    public Carbon $timestamp;

    public function __construct(
        string $attempt_id,
        bool $passed,
        string $validator = 'ollama:qwen2.5-coder:32b',
        array $criteria = [],
        array $errors = [],
        ?string $execution_id = null,
    ) {
        $this->attempt_id = $attempt_id;
        $this->passed = $passed;
        $this->validator = $validator;
        $this->criteria = $criteria;
        $this->errors = $errors;
        $this->timestamp = now();
        $this->execution_id = $execution_id;
    }

    public function apply(AgentExecutionState $state): void
    {
        $state->validations[] = [
            'attempt_id' => $this->attempt_id,
            'passed' => $this->passed,
            'validator' => $this->validator,
            'criteria' => $this->criteria,
            'errors' => $this->errors,
            'timestamp' => $this->timestamp,
        ];

        if ($this->passed) {
            $state->status = 'completed';
            $state->completed_at = $this->timestamp;
        } else {
            $state->status = 'failed';
        }
    }
}
