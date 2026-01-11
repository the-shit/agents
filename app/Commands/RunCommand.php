<?php

namespace App\Commands;

use App\Services\AgentStatusService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class RunCommand extends Command
{
    protected $signature = 'run {kit? : Agent kit to execute} {--task= : Specific task to execute}';

    protected $description = 'Execute agent work';

    public function handle(AgentStatusService $statusService): int
    {
        $kit = $this->argument('kit');
        $task = $this->option('task');

        if (! $kit) {
            $kit = $this->choice(
                'Select agent kit to run',
                ['architect', 'implementer', 'tester', 'reviewer'],
                0
            );
        }

        $config = config("agents.kits.{$kit}");

        if (! $config) {
            $this->error("Unknown agent kit: {$kit}");

            return self::FAILURE;
        }

        $this->info("Running {$kit} agent swarm...");
        $this->newLine();

        if ($task) {
            $this->task("Executing task: {$task}", function () use ($kit, $task, $statusService) {
                $statusService->executeTask($kit, $task);

                return true;
            });
        } else {
            $this->task('Executing all pending tasks', function () use ($kit, $statusService) {
                $statusService->executeAll($kit);

                return true;
            });
        }

        $this->info('Agent work completed');

        return self::SUCCESS;
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
