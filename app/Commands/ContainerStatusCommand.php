<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;
use LaravelZero\Framework\Commands\Command;

class ContainerStatusCommand extends Command
{
    protected $signature = 'container:status {id : Container ID}';

    protected $description = 'Get detailed status of a container';

    public function handle(ContainerDaemonClient $client): int
    {
        $containerId = $this->argument('id');

        try {
            $container = $client->getContainer($containerId);

            // Handle both daemon response formats
            $id = $container['container_id'] ?? $container['id'] ?? $containerId;
            $task = $container['agent_name'] ?? $container['task'] ?? '-';

            $this->newLine();
            $this->info("Container: {$id}");
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $id],
                    ['Repository', $container['repo'] ?? '-'],
                    ['Task', $task],
                    ['Status', $this->formatStatus($container['status'] ?? 'unknown')],
                    ['Exit Code', $container['exit_code'] ?? '-'],
                    ['Created', $container['created_at'] ?? '-'],
                    ['Completed', $container['completed_at'] ?? '-'],
                ]
            );

            if (! empty($container['output'])) {
                $this->newLine();
                $this->info('Output (preview):');
                $this->line($this->truncateOutput($container['output'], 500));
            }

            if (! empty($container['error'])) {
                $this->newLine();
                $this->error('Error:');
                $this->line($container['error']);
            }

            return self::SUCCESS;
        } catch (ContainerDaemonException $e) {
            $this->handleException($e);

            return self::FAILURE;
        }
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'running' => '<fg=yellow>● running</>',
            'completed' => '<fg=green>✓ completed</>',
            'failed' => '<fg=red>✗ failed</>',
            default => $status,
        };
    }

    private function truncateOutput(string $output, int $maxLength): string
    {
        if (mb_strlen($output) <= $maxLength) {
            return $output;
        }

        return mb_substr($output, 0, $maxLength)."\n... (truncated, use container:logs for full output)";
    }

    private function handleException(ContainerDaemonException $e): void
    {
        if ($e->isConnectionError()) {
            $this->error('Could not connect to container daemon');
            $this->line('Make sure the daemon is running at: '.config('container.daemon.url'));
        } elseif ($e->isAuthError()) {
            $this->error('Authentication failed');
        } elseif ($e->isNotFound()) {
            $this->error('Container not found');
        } else {
            $this->error($e->getMessage());
        }
    }
}
