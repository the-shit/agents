<?php

namespace App\Commands;

use App\Services\AgentStatusService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class DeployCommand extends Command
{
    protected $signature = 'deploy {kit? : Agent kit to deploy (architect, implementer, tester, reviewer)}';

    protected $description = 'Deploy agent swarms';

    public function handle(AgentStatusService $statusService): int
    {
        $kit = $this->argument('kit');

        if (!$kit) {
            $kit = $this->choice(
                'Select agent kit to deploy',
                ['architect', 'implementer', 'tester', 'reviewer'],
                0
            );
        }

        $config = config("agents.kits.{$kit}");

        if (!$config) {
            $this->error("Unknown agent kit: {$kit}");
            return self::FAILURE;
        }

        $this->task("Deploying {$kit} agent swarm", function () use ($kit, $config, $statusService) {
            $statusService->deploy($kit, $config);
            return true;
        });

        $this->info("Agent swarm '{$kit}' deployed successfully");
        $this->newLine();
        $this->line("Run 'agents watch {$kit}' to monitor progress");

        return self::SUCCESS;
    }

    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
