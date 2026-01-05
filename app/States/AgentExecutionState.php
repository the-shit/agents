<?php

declare(strict_types=1);

namespace App\States;

use Illuminate\Support\Carbon;
use Thunk\Verbs\State;

class AgentExecutionState extends State
{
    public string $agent_id;

    public string $intent;

    public ?string $current_attempt_id = null;

    public string $status = 'declared'; // declared, attempting, validating, completed, failed

    public array $outputs = [];

    public array $validations = [];

    public ?Carbon $started_at = null;

    public ?Carbon $completed_at = null;
}
