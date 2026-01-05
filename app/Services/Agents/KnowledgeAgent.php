<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Events\Verbs\Agents\AgentIntentDeclared;
use App\Events\Verbs\Agents\PatternCaptured;
use App\States\AgentExecutionState;
use Illuminate\Support\Collection;
use Thunk\Verbs\Models\VerbEvent;

class KnowledgeAgent
{
    /**
     * Query recent agent executions by intent pattern
     */
    public function queryByIntent(string $intentPattern, int $limit = 10): Collection
    {
        return VerbEvent::query()
            ->where('type', AgentIntentDeclared::class)
            ->whereJsonContains('data->intent', $intentPattern)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (VerbEvent $event) => [
                'execution_id' => $event->data['execution_id'],
                'agent_id' => $event->data['agent_id'],
                'intent' => $event->data['intent'],
                'context' => $event->data['context'] ?? [],
                'timestamp' => $event->created_at,
            ]);
    }

    /**
     * Get full execution history for a specific execution
     */
    public function getExecutionHistory(string $executionId): Collection
    {
        return VerbEvent::query()
            ->whereJsonContains('data->execution_id', $executionId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (VerbEvent $event) => [
                'type' => class_basename($event->type),
                'data' => $event->data,
                'timestamp' => $event->created_at,
            ]);
    }

    /**
     * Get current state of an execution
     */
    public function getExecutionState(string $executionId): ?AgentExecutionState
    {
        try {
            return AgentExecutionState::load($executionId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find successful approaches for a given intent pattern
     */
    public function findSuccessfulApproaches(string $intentPattern): Collection
    {
        // Query for completed executions matching the intent
        $successfulExecutions = VerbEvent::query()
            ->where('type', AgentIntentDeclared::class)
            ->where('data->intent', 'like', "%{$intentPattern}%")
            ->get()
            ->filter(function (VerbEvent $event) {
                $state = $this->getExecutionState($event->data['execution_id']);

                return $state && $state->status === 'completed';
            })
            ->map(function (VerbEvent $event) {
                $state = $this->getExecutionState($event->data['execution_id']);

                return [
                    'execution_id' => $event->data['execution_id'],
                    'intent' => $event->data['intent'],
                    'approach' => $this->extractApproach($event->data['execution_id']),
                    'success' => true,
                    'timestamp' => $event->created_at,
                ];
            });

        return $successfulExecutions;
    }

    /**
     * Extract the approach used in an execution (from attempt events)
     */
    protected function extractApproach(string $executionId): array
    {
        return VerbEvent::query()
            ->whereJsonContains('data->execution_id', $executionId)
            ->where('type', 'like', '%AgentAttempt%')
            ->get()
            ->map(fn (VerbEvent $event) => [
                'action' => $event->data['action'] ?? null,
                'parameters' => $event->data['parameters'] ?? [],
            ])
            ->toArray();
    }

    /**
     * Get all active agents
     */
    public function getActiveAgents(): Collection
    {
        return \App\Models\AgentIdentity::query()
            ->where('status', 'active')
            ->get();
    }

    /**
     * Query knowledge patterns
     */
    public function queryPatterns(string $search = '', int $limit = 10): Collection
    {
        return VerbEvent::query()
            ->where('type', PatternCaptured::class)
            ->when($search, fn ($q) => $q->where('data->intent_pattern', 'like', "%{$search}%"))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (VerbEvent $event) => [
                'pattern_id' => $event->data['pattern_id'],
                'intent_pattern' => $event->data['intent_pattern'],
                'approach' => $event->data['approach'],
                'success_rate' => $event->data['success_rate'],
                'example_event_ids' => $event->data['example_event_ids'] ?? [],
                'timestamp' => $event->created_at,
            ]);
    }

    /**
     * Get context for an agent to make decisions
     */
    public function getContextForAgent(string $agentId, string $intent): array
    {
        // Find similar past executions
        $similarExecutions = $this->findSuccessfulApproaches($intent);

        // Find relevant patterns
        $patterns = $this->queryPatterns($intent, 5);

        // Get currently active agents
        $activeAgents = $this->getActiveAgents();

        return [
            'similar_executions' => $similarExecutions->toArray(),
            'patterns' => $patterns->toArray(),
            'active_agents' => $activeAgents->map(fn ($agent) => [
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'role' => $agent->role,
                'status' => $agent->status,
            ])->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Monitor ongoing agent coordination
     */
    public function getCoordinationStatus(): array
    {
        // Get all recent executions (last hour)
        $recentExecutions = VerbEvent::query()
            ->where('type', AgentIntentDeclared::class)
            ->where('created_at', '>=', now()->subHour())
            ->get()
            ->map(function (VerbEvent $event) {
                $state = $this->getExecutionState($event->data['execution_id']);

                return [
                    'execution_id' => $event->data['execution_id'],
                    'agent_id' => $event->data['agent_id'],
                    'intent' => $event->data['intent'],
                    'status' => $state?->status ?? 'unknown',
                    'started_at' => $event->created_at,
                ];
            });

        return [
            'total_executions' => $recentExecutions->count(),
            'by_status' => $recentExecutions->groupBy('status')->map->count()->toArray(),
            'executions' => $recentExecutions->toArray(),
        ];
    }
}
