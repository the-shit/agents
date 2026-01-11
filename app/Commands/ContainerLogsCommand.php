<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;
use LaravelZero\Framework\Commands\Command;

class ContainerLogsCommand extends Command
{
    protected $signature = 'container:logs
        {id : Container ID}
        {--tail=100 : Number of lines to show}
        {--follow : Follow log output (poll every 2 seconds)}';

    protected $description = 'View container logs';

    public function handle(ContainerDaemonClient $client): int
    {
        $containerId = $this->argument('id');
        $tail = (int) $this->option('tail');
        $follow = $this->option('follow');

        try {
            if ($follow) {
                return $this->followLogs($client, $containerId, $tail);
            }

            $logs = $client->getLogs($containerId, $tail);

            if (empty($logs)) {
                $this->info('No logs available');

                return self::SUCCESS;
            }

            $this->line($logs);

            return self::SUCCESS;
        } catch (ContainerDaemonException $e) {
            $this->handleException($e);

            return self::FAILURE;
        }
    }

    private function followLogs(ContainerDaemonClient $client, string $containerId, int $tail): int
    {
        $this->info("Following logs for {$containerId} (Ctrl+C to stop)...");
        $this->newLine();

        $lastLength = 0;

        while (true) {
            try {
                $container = $client->getContainer($containerId);
                $logs = $client->getLogs($containerId);

                // Only output new content
                if (mb_strlen($logs) > $lastLength) {
                    $newContent = mb_substr($logs, $lastLength);
                    $this->output->write($newContent);
                    $lastLength = mb_strlen($logs);
                }

                // Stop following if container is no longer running
                if ($container['status'] !== 'running') {
                    $this->newLine();
                    $this->info("Container {$container['status']}");

                    if (isset($container['exit_code'])) {
                        $this->line("Exit code: {$container['exit_code']}");
                    }

                    break;
                }

                sleep(2);
            } catch (ContainerDaemonException $e) {
                if ($e->isNotFound()) {
                    $this->newLine();
                    $this->warn('Container no longer exists');

                    break;
                }

                throw $e;
            }
        }

        return self::SUCCESS;
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
