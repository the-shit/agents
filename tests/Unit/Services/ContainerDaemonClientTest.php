<?php

declare(strict_types=1);

use App\Exceptions\ContainerDaemonException;
use App\Services\ContainerDaemonClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function createClientWithMock(array $responses): ContainerDaemonClient
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $guzzleClient = new Client(['handler' => $handlerStack]);

    // Create client with explicit values (not relying on config())
    $client = new ContainerDaemonClient('http://localhost:9092', 'test-token');

    // Use reflection to inject mock client
    $reflection = new ReflectionClass($client);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($client, $guzzleClient);

    return $client;
}

describe('ContainerDaemonClient', function () {
    describe('health', function () {
        it('returns health status', function () {
            $client = createClientWithMock([
                new Response(200, [], json_encode(['status' => 'ok', 'version' => '1.0.0'])),
            ]);

            $result = $client->health();

            expect($result)->toBe(['status' => 'ok', 'version' => '1.0.0']);
        });

        it('throws exception on connection failure', function () {
            $client = createClientWithMock([
                new ConnectException('Connection refused', new Request('GET', '/health')),
            ]);

            $client->health();
        })->throws(ContainerDaemonException::class, 'Failed to connect');
    });

    describe('isReachable', function () {
        it('returns true when daemon is reachable', function () {
            $client = createClientWithMock([
                new Response(200, [], json_encode(['status' => 'ok'])),
            ]);

            expect($client->isReachable())->toBeTrue();
        });

        it('returns false when daemon is not reachable', function () {
            $client = createClientWithMock([
                new ConnectException('Connection refused', new Request('GET', '/health')),
            ]);

            expect($client->isReachable())->toBeFalse();
        });
    });

    describe('listContainers', function () {
        it('returns list of containers', function () {
            $containers = [
                ['id' => 'abc123', 'repo' => 'org/repo', 'status' => 'running'],
                ['id' => 'def456', 'repo' => 'org/other', 'status' => 'completed'],
            ];

            $client = createClientWithMock([
                new Response(200, [], json_encode($containers)),
            ]);

            $result = $client->listContainers();

            expect($result)->toBe($containers);
        });

        it('returns empty array when no containers', function () {
            $client = createClientWithMock([
                new Response(200, [], json_encode([])),
            ]);

            expect($client->listContainers())->toBe([]);
        });
    });

    describe('getContainer', function () {
        it('returns container details', function () {
            $container = [
                'id' => 'abc123',
                'repo' => 'org/repo',
                'task' => 'Fix bug',
                'status' => 'running',
            ];

            $client = createClientWithMock([
                new Response(200, [], json_encode($container)),
            ]);

            $result = $client->getContainer('abc123');

            expect($result)->toBe($container);
        });

        it('throws not found exception', function () {
            $client = createClientWithMock([
                new RequestException(
                    'Not Found',
                    new Request('GET', '/containers/invalid'),
                    new Response(404, [], json_encode(['error' => 'Container not found']))
                ),
            ]);

            $client->getContainer('invalid');
        })->throws(ContainerDaemonException::class);
    });

    describe('spawnContainer', function () {
        it('spawns a new container', function () {
            $container = [
                'id' => 'new123',
                'repo' => 'org/repo',
                'task' => 'Fix bug',
                'status' => 'running',
            ];

            $client = createClientWithMock([
                new Response(201, [], json_encode($container)),
            ]);

            $result = $client->spawnContainer('org/repo', 'Fix bug', 'main', 3600);

            expect($result)->toBe($container);
        });
    });

    describe('killContainer', function () {
        it('kills a container', function () {
            $client = createClientWithMock([
                new Response(200, [], json_encode(['success' => true])),
            ]);

            $result = $client->killContainer('abc123');

            expect($result)->toBe(['success' => true]);
        });
    });

    describe('getLogs', function () {
        it('returns container logs', function () {
            $client = createClientWithMock([
                new Response(200, [], json_encode(['logs' => "Line 1\nLine 2\nLine 3"])),
            ]);

            $result = $client->getLogs('abc123', 100);

            expect($result)->toBe("Line 1\nLine 2\nLine 3");
        });

        it('returns empty string when no logs', function () {
            $client = createClientWithMock([
                new Response(200, [], json_encode(['logs' => ''])),
            ]);

            expect($client->getLogs('abc123'))->toBe('');
        });
    });

    describe('authentication', function () {
        it('throws auth exception on 401', function () {
            $client = createClientWithMock([
                new RequestException(
                    'Unauthorized',
                    new Request('GET', '/containers'),
                    new Response(401, [], json_encode(['error' => 'Invalid token']))
                ),
            ]);

            $client->listContainers();
        })->throws(ContainerDaemonException::class, 'Authentication failed');
    });
});

describe('ContainerDaemonException', function () {
    it('identifies connection errors', function () {
        $e = ContainerDaemonException::connectionFailed('http://localhost:9092');

        expect($e->isConnectionError())->toBeTrue();
        expect($e->isAuthError())->toBeFalse();
        expect($e->isNotFound())->toBeFalse();
    });

    it('identifies auth errors', function () {
        $e = ContainerDaemonException::authenticationFailed();

        expect($e->isAuthError())->toBeTrue();
        expect($e->isConnectionError())->toBeFalse();
        expect($e->isNotFound())->toBeFalse();
    });

    it('identifies not found errors', function () {
        $e = ContainerDaemonException::notFound('abc123');

        expect($e->isNotFound())->toBeTrue();
        expect($e->isConnectionError())->toBeFalse();
        expect($e->isAuthError())->toBeFalse();
    });
});
