<?php

declare(strict_types=1);

namespace App\States;

use Illuminate\Support\Carbon;
use Thunk\Verbs\State;

class KnowledgePatternState extends State
{
    public string $intent_pattern;

    public string $approach;

    public float $success_rate;

    public array $example_event_ids = [];

    public int $occurrence_count = 0;

    public ?Carbon $first_seen_at = null;

    public ?Carbon $last_seen_at = null;
}
