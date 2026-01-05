<?php

declare(strict_types=1);

namespace App\Events\Verbs\Agents;

use App\States\AgentExecutionState;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Event;

class AgentStopSignalSent extends Event
{
    public string $agent_id;

    public string $reason;

    public ?string $next_agent;

    public array $context;

    public Carbon $timestamp;

    public function __construct(
        string $agent_id,
        string $reason,
        ?string $next_agent = null,
        array $context = [],
    ) {
        $this->agent_id = $agent_id;
        $this->reason = $reason;
        $this->next_agent = $next_agent;
        $this->context = $context;
        $this->timestamp = now();
    }

    public function apply(AgentExecutionState $state): void
    {
        // Stop signal doesn't change state, it just signals handoff
        // The next agent will create its own execution state
    }
}
