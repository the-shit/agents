<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AgentStatusService
{
    public function deploy(string $kit, array $config): void
    {
        Cache::put("agent.{$kit}.status", [
            'status' => 'deployed',
            'progress' => 0,
            'started_at' => now()->toDateTimeString(),
            'completed_tasks' => 0,
            'pending_tasks' => count($config['tasks'] ?? []),
            'config' => $config,
        ], now()->addDays(7));
    }

    public function getStatus(string $kit): ?array
    {
        return Cache::get("agent.{$kit}.status");
    }

    public function executeTask(string $kit, string $task): void
    {
        $status = $this->getStatus($kit);

        if (!$status) {
            return;
        }

        $status['completed_tasks']++;
        $status['pending_tasks'] = max(0, $status['pending_tasks'] - 1);
        $status['progress'] = $status['pending_tasks'] > 0
            ? round(($status['completed_tasks'] / ($status['completed_tasks'] + $status['pending_tasks'])) * 100)
            : 100;

        if ($status['pending_tasks'] === 0) {
            $status['status'] = 'completed';
        }

        Cache::put("agent.{$kit}.status", $status, now()->addDays(7));
    }

    public function executeAll(string $kit): void
    {
        $status = $this->getStatus($kit);

        if (!$status) {
            return;
        }

        $taskCount = $status['pending_tasks'];

        for ($i = 0; $i < $taskCount; $i++) {
            $this->executeTask($kit, "task-{$i}");
        }
    }
}
