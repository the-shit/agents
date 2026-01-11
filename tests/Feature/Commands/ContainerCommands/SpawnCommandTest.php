<?php

declare(strict_types=1);

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;

beforeEach(function () {
    $this->client = Mockery::mock(ContainerDaemonClient::class);
    $this->app->instance(ContainerDaemonClient::class, $this->client);
});

describe('container:spawn command', function () {
    it('spawns a container successfully', function () {
        $this->client
            ->shouldReceive('spawnContainer')
            ->with('org/repo', 'Fix bug #123', 'main', 3600)
            ->once()
            ->andReturn([
                'id' => 'abc123def456',
                'repo' => 'org/repo',
                'task' => 'Fix bug #123',
                'status' => 'running',
            ]);

        $this->artisan('container:spawn', [
            '--repo' => 'org/repo',
            '--task' => 'Fix bug #123',
            '--branch' => 'main',
            '--timeout' => '3600',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Container spawned successfully');
    });

    it('handles connection errors', function () {
        $this->client
            ->shouldReceive('spawnContainer')
            ->andThrow(ContainerDaemonException::connectionFailed('http://localhost:9092'));

        $this->artisan('container:spawn', [
            '--repo' => 'org/repo',
            '--task' => 'Fix bug',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Could not connect');
    });

    it('handles auth errors', function () {
        $this->client
            ->shouldReceive('spawnContainer')
            ->andThrow(ContainerDaemonException::authenticationFailed());

        $this->artisan('container:spawn', [
            '--repo' => 'org/repo',
            '--task' => 'Fix bug',
        ])
            ->assertFailed()
            ->expectsOutputToContain('Authentication failed');
    });

    it('has correct signature', function () {
        $command = $this->app->make(\App\Commands\ContainerSpawnCommand::class);

        expect($command->getName())->toBe('container:spawn');
    });
});
