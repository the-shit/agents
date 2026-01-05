<?php

declare(strict_types=1);

namespace App\Events\Verbs\Agents;

use App\States\AgentExecutionState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

class AgentIntentDeclared extends Event
{
    #[StateId(AgentExecutionState::class)]
    public ?string $execution_id = null;

    public string $agent_id;

    public string $intent;

    public array $context;

    public Carbon $timestamp;

    public function __construct(
        string $agent_id,
        string $intent,
        array $context = [],
    ) {
        $this->agent_id = $agent_id;
        $this->intent = $intent;
        $this->context = $context;
        $this->timestamp = now();
        $this->execution_id = (string) Str::uuid();
    }

    public function apply(AgentExecutionState $state): void
    {
        $state->agent_id = $this->agent_id;
        $state->intent = $this->intent;
        $state->status = 'declared';
        $state->started_at = $this->timestamp;
    }
}
