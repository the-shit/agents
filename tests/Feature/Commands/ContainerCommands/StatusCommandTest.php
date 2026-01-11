<?php

declare(strict_types=1);

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;

beforeEach(function () {
    $this->client = Mockery::mock(ContainerDaemonClient::class);
    $this->app->instance(ContainerDaemonClient::class, $this->client);
});

describe('container:status command', function () {
    it('shows container status', function () {
        $this->client
            ->shouldReceive('getContainer')
            ->with('abc123')
            ->once()
            ->andReturn([
                'id' => 'abc123',
                'repo' => 'org/repo',
                'task' => 'Fix bug #123',
                'status' => 'running',
                'created_at' => '2025-01-01 12:00:00',
            ]);

        $this->artisan('container:status', ['id' => 'abc123'])
            ->assertSuccessful()
            ->expectsOutputToContain('abc123')
            ->expectsOutputToContain('org/repo');
    });

    it('shows output preview when available', function () {
        $this->client
            ->shouldReceive('getContainer')
            ->andReturn([
                'id' => 'abc123',
                'repo' => 'org/repo',
                'task' => 'Fix bug',
                'status' => 'completed',
                'output' => 'Task completed successfully',
                'created_at' => '2025-01-01 12:00:00',
            ]);

        $this->artisan('container:status', ['id' => 'abc123'])
            ->assertSuccessful()
            ->expectsOutputToContain('Task completed successfully');
    });

    it('handles not found errors', function () {
        $this->client
            ->shouldReceive('getContainer')
            ->andThrow(ContainerDaemonException::notFound('invalid'));

        $this->artisan('container:status', ['id' => 'invalid'])
            ->assertFailed()
            ->expectsOutputToContain('Container not found');
    });

    it('has correct signature', function () {
        $command = $this->app->make(\App\Commands\ContainerStatusCommand::class);

        expect($command->getName())->toBe('container:status');
    });
});
