<?php

declare(strict_types=1);

namespace App\Events\Verbs\Agents;

use App\States\AgentExecutionState;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

class AgentOutputProduced extends Event
{
    #[StateId(AgentExecutionState::class)]
    public ?string $execution_id = null;

    public string $attempt_id;

    public string $output;

    public array $artifacts;

    public Carbon $timestamp;

    public function __construct(
        string $attempt_id,
        string $output,
        array $artifacts = [],
        ?string $execution_id = null,
    ) {
        $this->attempt_id = $attempt_id;
        $this->output = $output;
        $this->artifacts = $artifacts;
        $this->timestamp = now();
        $this->execution_id = $execution_id;
    }

    public function apply(AgentExecutionState $state): void
    {
        $state->outputs[] = [
            'attempt_id' => $this->attempt_id,
            'output' => $this->output,
            'artifacts' => $this->artifacts,
            'timestamp' => $this->timestamp,
        ];
    }
}
