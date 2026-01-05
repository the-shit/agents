<?php

declare(strict_types=1);

namespace App\Events\Verbs\Agents;

use App\States\AgentExecutionState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

class AgentAttemptStarted extends Event
{
    #[StateId(AgentExecutionState::class)]
    public ?string $execution_id = null;

    public string $agent_id;

    public string $attempt_id;

    public string $action;

    public array $parameters;

    public Carbon $timestamp;

    public function __construct(
        string $agent_id,
        string $action,
        array $parameters = [],
        ?string $execution_id = null,
    ) {
        $this->agent_id = $agent_id;
        $this->attempt_id = (string) Str::uuid();
        $this->action = $action;
        $this->parameters = $parameters;
        $this->timestamp = now();
        $this->execution_id = $execution_id;
    }

    public function apply(AgentExecutionState $state): void
    {
        $state->current_attempt_id = $this->attempt_id;
        $state->status = 'attempting';
    }
}
