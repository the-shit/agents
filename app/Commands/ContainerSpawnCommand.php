<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;
use LaravelZero\Framework\Commands\Command;

class ContainerSpawnCommand extends Command
{
    protected $signature = 'container:spawn
        {--repo= : Repository in org/repo format}
        {--task= : Task description for the agent}
        {--branch= : Branch to work on (default: main)}
        {--timeout= : Timeout in seconds (default: 3600)}';

    protected $description = 'Spawn a new agent container';

    public function handle(ContainerDaemonClient $client): int
    {
        $repo = $this->option('repo');
        $task = $this->option('task');

        if (! $repo) {
            $repo = $this->ask('Repository (org/repo format)');
        }

        if (! $task) {
            $task = $this->ask('Task description');
        }

        if (! $repo || ! $task) {
            $this->error('Repository and task are required');

            return self::FAILURE;
        }

        $branch = $this->option('branch');
        $timeout = $this->option('timeout') ? (int) $this->option('timeout') : null;

        $this->info('Spawning agent container...');

        try {
            $container = $client->spawnContainer($repo, $task, $branch, $timeout);

            // Handle both daemon response formats
            $id = $container['container_id'] ?? $container['id'] ?? 'unknown';

            $this->newLine();
            $this->info('Container spawned successfully!');
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $id],
                    ['Repository', $container['repo'] ?? $repo],
                    ['Branch', $branch ?? 'main'],
                    ['Task', mb_substr($task, 0, 50).(mb_strlen($task) > 50 ? '...' : '')],
                    ['Status', $container['status'] ?? 'running'],
                ]
            );

            return self::SUCCESS;
        } catch (ContainerDaemonException $e) {
            $this->handleException($e);

            return self::FAILURE;
        }
    }

    private function handleException(ContainerDaemonException $e): void
    {
        if ($e->isConnectionError()) {
            $this->error('Could not connect to container daemon');
            $this->line('Make sure the daemon is running at: '.config('container.daemon.url'));
        } elseif ($e->isAuthError()) {
            $this->error('Authentication failed');
            $this->line('Check your CONTAINER_DAEMON_TOKEN environment variable');
        } else {
            $this->error($e->getMessage());
        }
    }
}
