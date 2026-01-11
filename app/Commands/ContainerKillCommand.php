<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;
use LaravelZero\Framework\Commands\Command;

class ContainerKillCommand extends Command
{
    protected $signature = 'container:kill
        {id : Container ID to terminate}
        {--force : Skip confirmation prompt}';

    protected $description = 'Terminate an agent container';

    public function handle(ContainerDaemonClient $client): int
    {
        $containerId = $this->argument('id');
        $force = $this->option('force');

        try {
            // Get container info first
            $container = $client->getContainer($containerId);

            if ($container['status'] !== 'running') {
                $this->warn("Container is not running (status: {$container['status']})");

                return self::SUCCESS;
            }

            if (! $force) {
                $confirmed = $this->confirm(
                    "Are you sure you want to terminate container {$containerId}?",
                    false
                );

                if (! $confirmed) {
                    $this->info('Cancelled');

                    return self::SUCCESS;
                }
            }

            $this->info('Terminating container...');

            $result = $client->killContainer($containerId);

            if ($result['success'] ?? false) {
                $this->info('Container terminated successfully');

                return self::SUCCESS;
            }

            $this->error($result['message'] ?? 'Failed to terminate container');

            return self::FAILURE;
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
        } elseif ($e->isNotFound()) {
            $this->error('Container not found');
        } else {
            $this->error($e->getMessage());
        }
    }
}
