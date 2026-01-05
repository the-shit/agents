<?php

declare(strict_types=1);

namespace App\Events\Verbs\Agents;

use App\States\KnowledgePatternState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

class PatternCaptured extends Event
{
    #[StateId(KnowledgePatternState::class)]
    public ?string $pattern_id = null;

    public string $intent_pattern;

    public string $approach;

    public float $success_rate;

    public array $example_event_ids;

    public Carbon $timestamp;

    public function __construct(
        string $intent_pattern,
        string $approach,
        float $success_rate,
        array $example_event_ids = [],
    ) {
        $this->intent_pattern = $intent_pattern;
        $this->approach = $approach;
        $this->success_rate = $success_rate;
        $this->example_event_ids = $example_event_ids;
        $this->timestamp = now();
        $this->pattern_id = (string) Str::uuid();
    }

    public function apply(KnowledgePatternState $state): void
    {
        $state->intent_pattern = $this->intent_pattern;
        $state->approach = $this->approach;
        $state->success_rate = $this->success_rate;
        $state->example_event_ids = $this->example_event_ids;
        $state->occurrence_count++;

        if (! $state->first_seen_at) {
            $state->first_seen_at = $this->timestamp;
        }

        $state->last_seen_at = $this->timestamp;
    }
}
