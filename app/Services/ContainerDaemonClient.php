<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ContainerDaemonException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class ContainerDaemonClient
{
    private Client $client;

    private string $baseUrl;

    private string $token;

    public function __construct(?string $baseUrl = null, ?string $token = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? $this->getConfig('container.daemon.url', 'http://localhost:9092'), '/');
        $this->token = $token ?? $this->getConfig('container.daemon.token', '');
        $timeout = $timeout ?? $this->getConfig('container.daemon.timeout', 30);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'headers' => $this->buildHeaders(),
        ]);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Check daemon health.
     *
     * @return array{status: string, version?: string}
     *
     * @throws ContainerDaemonException
     */
    public function health(): array
    {
        return $this->request('GET', '/health');
    }

    /**
     * Check if daemon is reachable.
     */
    public function isReachable(): bool
    {
        try {
            $this->health();

            return true;
        } catch (ContainerDaemonException) {
            return false;
        }
    }

    /**
     * List all containers.
     *
     * @param  string|null  $status  Filter by status (running, completed, failed)
     * @return array<int, array{id: string, repo: string, task: string, status: string, created_at: string}>
     *
     * @throws ContainerDaemonException
     */
    public function listContainers(?string $status = null): array
    {
        $query = $status ? ['status' => $status] : [];

        return $this->request('GET', '/containers', ['query' => $query]);
    }

    /**
     * Get container status.
     *
     * @return array{id: string, repo: string, task: string, status: string, exit_code?: int, output?: string, error?: string, created_at: string, completed_at?: string}
     *
     * @throws ContainerDaemonException
     */
    public function getContainer(string $containerId): array
    {
        return $this->request('GET', "/containers/{$containerId}");
    }

    /**
     * Spawn a new container.
     *
     * @return array{id: string, repo: string, task: string, status: string, created_at: string}
     *
     * @throws ContainerDaemonException
     */
    public function spawnContainer(
        string $repo,
        string $task,
        ?string $branch = null,
        ?int $timeout = null,
    ): array {
        $body = [
            'repo' => $repo,
            'task' => $task,
        ];

        if ($branch !== null) {
            $body['branch'] = $branch;
        }

        if ($timeout !== null) {
            $body['timeout'] = $timeout;
        }

        return $this->request('POST', '/containers', ['json' => $body]);
    }

    /**
     * Kill a container.
     *
     * @return array{success: bool, message?: string}
     *
     * @throws ContainerDaemonException
     */
    public function killContainer(string $containerId): array
    {
        return $this->request('DELETE', "/containers/{$containerId}");
    }

    /**
     * Get container logs.
     *
     * @throws ContainerDaemonException
     */
    public function getLogs(string $containerId, ?int $tail = null): string
    {
        $query = $tail !== null ? ['tail' => $tail] : [];

        $response = $this->request('GET', "/containers/{$containerId}/logs", ['query' => $query]);

        return $response['logs'] ?? '';
    }

    /**
     * Execute command in container.
     *
     * @return array{exit_code: int, stdout: string, stderr: string}
     *
     * @throws ContainerDaemonException
     */
    public function execCommand(string $containerId, string $command): array
    {
        return $this->request('POST', "/containers/{$containerId}/exec", [
            'json' => ['command' => $command],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->token !== '') {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws ContainerDaemonException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);
            $body = (string) $response->getBody();

            return json_decode($body, true) ?? [];
        } catch (ConnectException $e) {
            throw ContainerDaemonException::connectionFailed($this->baseUrl);
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response === null) {
                throw ContainerDaemonException::connectionFailed($this->baseUrl);
            }

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true) ?? [];

            if ($statusCode === 401) {
                throw ContainerDaemonException::authenticationFailed();
            }

            if ($statusCode === 404) {
                throw ContainerDaemonException::notFound($uri);
            }

            throw ContainerDaemonException::fromResponse($statusCode, $body);
        }
    }

    /**
     * Safely get config value with fallback.
     */
    private function getConfig(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            try {
                return config($key, $default);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $default;
    }
}
