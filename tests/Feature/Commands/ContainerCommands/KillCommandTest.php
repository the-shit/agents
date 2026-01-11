<?php

declare(strict_types=1);

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;

beforeEach(function () {
    $this->client = Mockery::mock(ContainerDaemonClient::class);
    $this->app->instance(ContainerDaemonClient::class, $this->client);
});

describe('container:kill command', function () {
    it('kills a running container with force flag', function () {
        $this->client
            ->shouldReceive('getContainer')
            ->with('abc123')
            ->once()
            ->andReturn([
                'id' => 'abc123',
                'status' => 'running',
            ]);

        $this->client
            ->shouldReceive('killContainer')
            ->with('abc123')
            ->once()
            ->andReturn(['success' => true]);

        $this->artisan('container:kill', ['id' => 'abc123', '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('terminated successfully');
    });

    it('skips non-running containers', function () {
        $this->client
            ->shouldReceive('getContainer')
            ->andReturn([
                'id' => 'abc123',
                'status' => 'completed',
            ]);

        $this->artisan('container:kill', ['id' => 'abc123', '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('not running');
    });

    it('handles not found errors', function () {
        $this->client
            ->shouldReceive('getContainer')
            ->andThrow(ContainerDaemonException::notFound('invalid'));

        $this->artisan('container:kill', ['id' => 'invalid', '--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('Container not found');
    });

    it('has correct signature', function () {
        $command = $this->app->make(\App\Commands\ContainerKillCommand::class);

        expect($command->getName())->toBe('container:kill');
    });
});
