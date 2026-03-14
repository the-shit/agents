<?php

namespace App\Commands;

use App\Services\VectorizationService;
use LaravelZero\Framework\Commands\Command;

/**
 * MCP Server Command
 *
 * Exposes vectorization capabilities via Model Context Protocol (MCP).
 * Allows Claude Code to directly query indexed code.
 */
class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve
        {--port=3100 : Port to listen on}
        {--host=127.0.0.1 : Host to bind to}';

    protected $description = 'Start MCP server for Claude Code integration';

    protected VectorizationService $vectorization;

    public function handle(VectorizationService $vectorization): int
    {
        $this->vectorization = $vectorization;

        $host = $this->option('host');
        $port = (int) $this->option('port');

        $this->info("Starting MCP server on {$host}:{$port}...");
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        // For now, we'll output the MCP manifest that can be read by Claude Code
        // A full socket implementation would require additional dependencies
        $this->outputManifest();

        // In stdio mode, read from stdin and respond to stdout
        if ($this->isStdioMode()) {
            return $this->handleStdio();
        }

        return self::SUCCESS;
    }

    protected function isStdioMode(): bool
    {
        return ! posix_isatty(STDIN);
    }

    protected function handleStdio(): int
    {
        while ($line = fgets(STDIN)) {
            $request = json_decode(trim($line), true);

            if (! $request) {
                continue;
            }

            $response = $this->handleRequest($request);
            echo json_encode($response)."\n";
        }

        return self::SUCCESS;
    }

    protected function handleRequest(array $request): array
    {
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        $result = match ($method) {
            'initialize' => $this->handleInitialize($params),
            'tools/list' => $this->handleToolsList(),
            'tools/call' => $this->handleToolsCall($params),
            default => ['error' => "Unknown method: {$method}"],
        };

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    protected function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => [],
            ],
            'serverInfo' => [
                'name' => 'agents-vectorization',
                'version' => '1.0.0',
            ],
        ];
    }

    protected function handleToolsList(): array
    {
        return [
            'tools' => [
                [
                    'name' => 'search_code',
                    'description' => 'Search indexed codebase for relevant code snippets',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search query (keywords or natural language)',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum results to return',
                                'default' => 10,
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'list_symbols',
                    'description' => 'List all indexed symbols (classes, functions, etc)',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'description' => 'Filter by symbol type (class, function, etc)',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'get_context',
                    'description' => 'Get code context around a specific file location',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'file' => [
                                'type' => 'string',
                                'description' => 'File path',
                            ],
                            'line' => [
                                'type' => 'integer',
                                'description' => 'Line number',
                            ],
                            'radius' => [
                                'type' => 'integer',
                                'description' => 'Context radius',
                                'default' => 5,
                            ],
                        ],
                        'required' => ['file', 'line'],
                    ],
                ],
                [
                    'name' => 'index_stats',
                    'description' => 'Get vectorization index statistics',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
        ];
    }

    protected function handleToolsCall(array $params): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        return match ($name) {
            'search_code' => $this->toolSearchCode($arguments),
            'list_symbols' => $this->toolListSymbols($arguments),
            'get_context' => $this->toolGetContext($arguments),
            'index_stats' => $this->toolIndexStats(),
            default => [
                'content' => [
                    ['type' => 'text', 'text' => "Unknown tool: {$name}"],
                ],
                'isError' => true,
            ],
        };
    }

    protected function toolSearchCode(array $args): array
    {
        $query = $args['query'] ?? '';
        $limit = $args['limit'] ?? 10;

        $results = $this->vectorization->search($query, $limit);

        $formatted = $results->map(fn ($r) => [
            'symbol' => $r['symbol'] ?? 'chunk',
            'file' => $r['file'],
            'lines' => "{$r['start_line']}-{$r['end_line']}",
            'score' => round($r['score'], 2),
            'snippet' => substr($r['content'], 0, 300),
        ])->toArray();

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($formatted, JSON_PRETTY_PRINT)],
            ],
        ];
    }

    protected function toolListSymbols(array $args): array
    {
        $type = $args['type'] ?? null;
        $symbols = $this->vectorization->listSymbols($type);

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($symbols->toArray(), JSON_PRETTY_PRINT)],
            ],
        ];
    }

    protected function toolGetContext(array $args): array
    {
        $file = $args['file'] ?? '';
        $line = $args['line'] ?? 1;
        $radius = $args['radius'] ?? 5;

        $context = $this->vectorization->getContext($file, $line, $radius);

        if (! $context) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => 'No context found for the specified location'],
                ],
            ];
        }

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($context, JSON_PRETTY_PRINT)],
            ],
        ];
    }

    protected function toolIndexStats(): array
    {
        $stats = $this->vectorization->stats();

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($stats, JSON_PRETTY_PRINT)],
            ],
        ];
    }

    protected function outputManifest(): void
    {
        $manifest = [
            'name' => 'agents-vectorization',
            'version' => '1.0.0',
            'description' => 'Code vectorization and semantic search for Claude Code',
            'tools' => $this->handleToolsList()['tools'],
        ];

        $this->line('MCP Manifest:');
        $this->line(json_encode($manifest, JSON_PRETTY_PRINT));
        $this->newLine();

        $this->info('Add to Claude Code settings.json:');
        $this->line(json_encode([
            'mcpServers' => [
                'agents-vectorization' => [
                    'command' => 'php',
                    'args' => [realpath(base_path('application')), 'mcp:serve'],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
