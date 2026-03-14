<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Code Vectorization Service
 *
 * Handles indexing and searching code without AI service dependencies.
 * Designed to integrate with Claude Code via MCP for semantic search.
 */
class VectorizationService
{
    protected string $storagePath;

    protected array $config;

    public function __construct()
    {
        $this->config = config('vectorization', []);
        $this->storagePath = $this->config['storage_path'] ?? storage_path('vectors');
    }

    /**
     * Index a codebase directory
     */
    public function index(string $path, ?callable $onProgress = null): array
    {
        $this->ensureStorageExists();

        $files = $this->discoverFiles($path);
        $indexed = [];
        $total = count($files);
        $processed = 0;

        foreach ($files as $file) {
            $chunks = $this->extractChunks($file);

            foreach ($chunks as $chunk) {
                $indexed[] = $this->storeChunk($chunk);
            }

            $processed++;
            if ($onProgress) {
                $onProgress($processed, $total, $file);
            }
        }

        $this->saveIndex($indexed);

        return [
            'files_processed' => $processed,
            'chunks_indexed' => count($indexed),
            'index_path' => $this->getIndexPath(),
        ];
    }

    /**
     * Search indexed code
     */
    public function search(string $query, int $limit = 10): Collection
    {
        $index = $this->loadIndex();

        if ($index->isEmpty()) {
            return collect();
        }

        // Simple keyword-based search (MCP can enhance with embeddings)
        $keywords = $this->extractKeywords($query);

        return $index
            ->map(function ($chunk) use ($keywords) {
                $score = $this->scoreChunk($chunk, $keywords);

                return array_merge($chunk, ['score' => $score]);
            })
            ->filter(fn ($chunk) => $chunk['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * List all indexed symbols
     */
    public function listSymbols(string $type = null): Collection
    {
        $index = $this->loadIndex();

        $symbols = $index
            ->filter(fn ($chunk) => ! empty($chunk['symbol']))
            ->map(fn ($chunk) => [
                'name' => $chunk['symbol'],
                'type' => $chunk['symbol_type'] ?? 'unknown',
                'file' => $chunk['file'],
                'line' => $chunk['start_line'],
            ]);

        if ($type) {
            $symbols = $symbols->filter(fn ($s) => $s['type'] === $type);
        }

        return $symbols->values();
    }

    /**
     * Get context around a specific location
     */
    public function getContext(string $file, int $line, int $radius = 5): ?array
    {
        $index = $this->loadIndex();

        $chunk = $index
            ->filter(fn ($c) => $c['file'] === $file)
            ->filter(fn ($c) => $c['start_line'] <= $line && $c['end_line'] >= $line)
            ->first();

        if (! $chunk) {
            return null;
        }

        // Get surrounding chunks for expanded context
        $surrounding = $index
            ->filter(fn ($c) => $c['file'] === $file)
            ->filter(fn ($c) => abs($c['start_line'] - $line) <= $radius * 10)
            ->sortBy('start_line')
            ->values();

        return [
            'primary' => $chunk,
            'context' => $surrounding->toArray(),
        ];
    }

    /**
     * Clear the index
     */
    public function clear(): void
    {
        if (File::exists($this->getIndexPath())) {
            File::delete($this->getIndexPath());
        }
    }

    /**
     * Get index statistics
     */
    public function stats(): array
    {
        $index = $this->loadIndex();

        return [
            'total_chunks' => $index->count(),
            'total_files' => $index->pluck('file')->unique()->count(),
            'total_symbols' => $index->filter(fn ($c) => ! empty($c['symbol']))->count(),
            'index_path' => $this->getIndexPath(),
            'index_size' => File::exists($this->getIndexPath())
                ? $this->formatBytes(File::size($this->getIndexPath()))
                : '0 B',
        ];
    }

    protected function discoverFiles(string $path): array
    {
        $include = $this->config['patterns']['include'] ?? ['*.php'];
        $exclude = $this->config['patterns']['exclude'] ?? ['vendor/*'];

        $finder = new Finder;
        $finder->files()->in($path);

        foreach ($include as $pattern) {
            $finder->name($pattern);
        }

        foreach ($exclude as $pattern) {
            $finder->notPath($pattern);
        }

        return array_map(
            fn ($file) => $file->getRealPath(),
            iterator_to_array($finder)
        );
    }

    protected function extractChunks(string $filePath): array
    {
        $content = File::get($filePath);
        $lines = explode("\n", $content);
        $strategy = $this->config['chunking']['strategy'] ?? 'symbol';

        return match ($strategy) {
            'symbol' => $this->extractSymbolChunks($filePath, $content, $lines),
            'line' => $this->extractLineChunks($filePath, $lines),
            default => $this->extractSemanticChunks($filePath, $lines),
        };
    }

    protected function extractSymbolChunks(string $filePath, string $content, array $lines): array
    {
        $chunks = [];
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Simple regex-based symbol extraction (works for most languages)
        $patterns = $this->getSymbolPatterns($extension);

        foreach ($patterns as $type => $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as $match) {
                $position = $match[1];
                $lineNumber = substr_count(substr($content, 0, $position), "\n") + 1;
                $symbolName = $matches[1][array_search($match, $matches[0])][0] ?? 'unknown';

                // Extract the symbol body (simple heuristic: until next symbol or end)
                $startLine = $lineNumber;
                $endLine = $this->findSymbolEnd($lines, $startLine - 1);

                $chunks[] = [
                    'file' => $filePath,
                    'symbol' => $symbolName,
                    'symbol_type' => $type,
                    'start_line' => $startLine,
                    'end_line' => $endLine,
                    'content' => implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1)),
                ];
            }
        }

        // If no symbols found, fall back to line chunking
        if (empty($chunks)) {
            return $this->extractLineChunks($filePath, $lines);
        }

        return $chunks;
    }

    protected function extractLineChunks(string $filePath, array $lines): array
    {
        $chunks = [];
        $maxLines = $this->config['chunking']['max_lines'] ?? 100;
        $overlap = $this->config['chunking']['overlap_lines'] ?? 10;

        $totalLines = count($lines);
        $start = 0;

        while ($start < $totalLines) {
            $end = min($start + $maxLines, $totalLines);

            $chunks[] = [
                'file' => $filePath,
                'symbol' => null,
                'symbol_type' => 'chunk',
                'start_line' => $start + 1,
                'end_line' => $end,
                'content' => implode("\n", array_slice($lines, $start, $end - $start)),
            ];

            $start = $end - $overlap;
            if ($start >= $totalLines - $overlap) {
                break;
            }
        }

        return $chunks;
    }

    protected function extractSemanticChunks(string $filePath, array $lines): array
    {
        // Semantic chunking based on blank lines and indentation changes
        $chunks = [];
        $currentChunk = [];
        $chunkStart = 1;

        foreach ($lines as $i => $line) {
            $lineNum = $i + 1;
            $isBlank = trim($line) === '';
            $isSignificantBreak = $isBlank && ! empty($currentChunk) && count($currentChunk) > 3;

            if ($isSignificantBreak) {
                $chunks[] = [
                    'file' => $filePath,
                    'symbol' => null,
                    'symbol_type' => 'block',
                    'start_line' => $chunkStart,
                    'end_line' => $lineNum - 1,
                    'content' => implode("\n", $currentChunk),
                ];
                $currentChunk = [];
                $chunkStart = $lineNum + 1;
            } else {
                $currentChunk[] = $line;
            }
        }

        // Don't forget the last chunk
        if (! empty($currentChunk)) {
            $chunks[] = [
                'file' => $filePath,
                'symbol' => null,
                'symbol_type' => 'block',
                'start_line' => $chunkStart,
                'end_line' => count($lines),
                'content' => implode("\n", $currentChunk),
            ];
        }

        return $chunks;
    }

    protected function getSymbolPatterns(string $extension): array
    {
        return match ($extension) {
            'php' => [
                'class' => '/(?:class|interface|trait|enum)\s+(\w+)/m',
                'function' => '/function\s+(\w+)\s*\(/m',
            ],
            'js', 'ts', 'tsx' => [
                'class' => '/class\s+(\w+)/m',
                'function' => '/(?:function|const|let|var)\s+(\w+)\s*(?:=\s*(?:async\s*)?\(|=\s*function|\()/m',
            ],
            'py' => [
                'class' => '/class\s+(\w+)/m',
                'function' => '/def\s+(\w+)\s*\(/m',
            ],
            'go' => [
                'function' => '/func\s+(?:\([^)]+\)\s+)?(\w+)\s*\(/m',
                'type' => '/type\s+(\w+)\s+(?:struct|interface)/m',
            ],
            'rs' => [
                'function' => '/fn\s+(\w+)/m',
                'struct' => '/struct\s+(\w+)/m',
                'impl' => '/impl(?:<[^>]+>)?\s+(\w+)/m',
            ],
            default => [
                'function' => '/(?:function|def|fn|func)\s+(\w+)/m',
            ],
        };
    }

    protected function findSymbolEnd(array $lines, int $startIndex): int
    {
        $braceCount = 0;
        $started = false;

        for ($i = $startIndex; $i < count($lines); $i++) {
            $line = $lines[$i];
            $braceCount += substr_count($line, '{') - substr_count($line, '}');

            if ($braceCount > 0) {
                $started = true;
            }

            if ($started && $braceCount <= 0) {
                return $i + 1;
            }
        }

        // If no braces found, use indentation or return reasonable default
        return min($startIndex + 50, count($lines));
    }

    protected function storeChunk(array $chunk): array
    {
        // Add a unique ID for referencing
        $chunk['id'] = md5($chunk['file'].$chunk['start_line'].$chunk['end_line']);
        $chunk['indexed_at'] = now()->toIso8601String();

        return $chunk;
    }

    protected function extractKeywords(string $query): array
    {
        // Simple keyword extraction
        $words = preg_split('/\s+/', strtolower($query));

        return array_filter($words, fn ($w) => strlen($w) > 2);
    }

    protected function scoreChunk(array $chunk, array $keywords): float
    {
        $score = 0.0;
        $content = strtolower($chunk['content'] ?? '');
        $symbol = strtolower($chunk['symbol'] ?? '');

        foreach ($keywords as $keyword) {
            // Symbol name matches are weighted heavily
            if (str_contains($symbol, $keyword)) {
                $score += 10.0;
            }

            // Content matches
            $occurrences = substr_count($content, $keyword);
            $score += $occurrences * 1.0;
        }

        // Normalize by content length to avoid bias toward large chunks
        $length = strlen($content);
        if ($length > 0) {
            $score = $score / log($length + 1);
        }

        return $score;
    }

    protected function ensureStorageExists(): void
    {
        if (! File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    protected function getIndexPath(): string
    {
        $indexName = $this->config['index']['name'] ?? 'codebase';

        return $this->storagePath.DIRECTORY_SEPARATOR."{$indexName}.json";
    }

    protected function saveIndex(array $chunks): void
    {
        File::put(
            $this->getIndexPath(),
            json_encode($chunks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function loadIndex(): Collection
    {
        $path = $this->getIndexPath();

        if (! File::exists($path)) {
            return collect();
        }

        $data = json_decode(File::get($path), true);

        return collect($data ?? []);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
