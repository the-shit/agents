<?php

namespace App\Commands;

use App\Services\AgentStatusService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class WatchCommand extends Command
{
    protected $signature = 'watch {kit? : Agent kit to monitor}';

    protected $description = 'Monitor agent progress';

    public function handle(AgentStatusService $statusService): int
    {
        $kit = $this->argument('kit');

        if (! $kit) {
            $kit = $this->choice(
                'Select agent kit to monitor',
                ['architect', 'implementer', 'tester', 'reviewer'],
                0
            );
        }

        $this->info("Monitoring {$kit} agent swarm...");
        $this->newLine();

        $status = $statusService->getStatus($kit);

        if (! $status) {
            $this->warn("No active deployment for '{$kit}' agent kit");

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Kit', $kit],
                ['Status', $status['status']],
                ['Progress', $status['progress'].'%'],
                ['Started', $status['started_at']],
                ['Tasks Completed', $status['completed_tasks']],
                ['Tasks Pending', $status['pending_tasks']],
            ]
        );

        return self::SUCCESS;
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
