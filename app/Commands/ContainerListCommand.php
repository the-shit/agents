<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;
use LaravelZero\Framework\Commands\Command;

class ContainerListCommand extends Command
{
    protected $signature = 'container:list
        {--status= : Filter by status (running, completed, failed)}';

    protected $description = 'List all agent containers';

    public function handle(ContainerDaemonClient $client): int
    {
        $status = $this->option('status');

        try {
            $containers = $client->listContainers($status);

            if (empty($containers)) {
                $this->info($status
                    ? "No containers with status: {$status}"
                    : 'No containers found');

                return self::SUCCESS;
            }

            $rows = array_map(fn (array $container) => [
                $this->truncate($container['id'], 12),
                $container['repo'],
                $this->truncate($container['task'] ?? '', 30),
                $this->formatStatus($container['status']),
                $container['created_at'] ?? '-',
            ], $containers);

            $this->table(
                ['ID', 'Repository', 'Task', 'Status', 'Created'],
                $rows
            );

            $this->newLine();
            $this->line(sprintf('Total: %d container(s)', count($containers)));

            return self::SUCCESS;
        } catch (ContainerDaemonException $e) {
            $this->handleException($e);

            return self::FAILURE;
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3).'...';
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'running' => '<fg=yellow>running</>',
            'completed' => '<fg=green>completed</>',
            'failed' => '<fg=red>failed</>',
            default => $status,
        };
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
