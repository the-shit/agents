<?php

declare(strict_types=1);

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;

beforeEach(function () {
    $this->client = Mockery::mock(ContainerDaemonClient::class);
    $this->app->instance(ContainerDaemonClient::class, $this->client);
});

describe('container:logs command', function () {
    it('shows container logs', function () {
        $this->client
            ->shouldReceive('getLogs')
            ->with('abc123', 100)
            ->once()
            ->andReturn('Test log output');

        $this->artisan('container:logs', ['id' => 'abc123'])
            ->assertSuccessful()
            ->expectsOutputToContain('Test log output');
    });

    it('respects tail option', function () {
        $this->client
            ->shouldReceive('getLogs')
            ->with('abc123', 50)
            ->once()
            ->andReturn('Some logs');

        $this->artisan('container:logs', ['id' => 'abc123', '--tail' => '50'])
            ->assertSuccessful();
    });

    it('shows message when no logs', function () {
        $this->client
            ->shouldReceive('getLogs')
            ->andReturn('');

        $this->artisan('container:logs', ['id' => 'abc123'])
            ->assertSuccessful()
            ->expectsOutputToContain('No logs available');
    });

    it('handles not found errors', function () {
        $this->client
            ->shouldReceive('getLogs')
            ->andThrow(ContainerDaemonException::notFound('invalid'));

        $this->artisan('container:logs', ['id' => 'invalid'])
            ->assertFailed()
            ->expectsOutputToContain('Container not found');
    });

    it('has correct signature', function () {
        $command = $this->app->make(\App\Commands\ContainerLogsCommand::class);

        expect($command->getName())->toBe('container:logs');
    });
});
