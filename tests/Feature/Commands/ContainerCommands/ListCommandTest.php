<?php

declare(strict_types=1);

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;

beforeEach(function () {
    $this->client = Mockery::mock(ContainerDaemonClient::class);
    $this->app->instance(ContainerDaemonClient::class, $this->client);
});

describe('container:list command', function () {
    it('lists containers successfully', function () {
        $this->client
            ->shouldReceive('listContainers')
            ->with(null)
            ->once()
            ->andReturn([
                ['id' => 'abc123', 'repo' => 'org/repo', 'task' => 'Fix bug', 'status' => 'running', 'created_at' => '2025-01-01 12:00:00'],
                ['id' => 'def456', 'repo' => 'org/other', 'task' => 'Add feature', 'status' => 'completed', 'created_at' => '2025-01-01 11:00:00'],
            ]);

        $this->artisan('container:list')
            ->assertSuccessful()
            ->expectsOutputToContain('org/repo')
            ->expectsOutputToContain('Total: 2 container(s)');
    });

    it('filters by status', function () {
        $this->client
            ->shouldReceive('listContainers')
            ->with('running')
            ->once()
            ->andReturn([
                ['id' => 'abc123', 'repo' => 'org/repo', 'task' => 'Fix bug', 'status' => 'running', 'created_at' => '2025-01-01 12:00:00'],
            ]);

        $this->artisan('container:list', ['--status' => 'running'])
            ->assertSuccessful();
    });

    it('shows message when no containers', function () {
        $this->client
            ->shouldReceive('listContainers')
            ->andReturn([]);

        $this->artisan('container:list')
            ->assertSuccessful()
            ->expectsOutputToContain('No containers found');
    });

    it('handles connection errors', function () {
        $this->client
            ->shouldReceive('listContainers')
            ->andThrow(ContainerDaemonException::connectionFailed('http://localhost:9092'));

        $this->artisan('container:list')
            ->assertFailed()
            ->expectsOutputToContain('Could not connect');
    });

    it('has correct signature', function () {
        $command = $this->app->make(\App\Commands\ContainerListCommand::class);

        expect($command->getName())->toBe('container:list');
    });
});
