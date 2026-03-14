<?php

namespace App\Commands;

use App\Services\VectorizationService;
use LaravelZero\Framework\Commands\Command;

class VectorizeCommand extends Command
{
    protected $signature = 'vectorize
        {action=index : Action to perform (index, search, symbols, stats, clear)}
        {--path= : Path to index (defaults to current directory)}
        {--query= : Search query}
        {--limit=10 : Number of results to return}
        {--type= : Symbol type filter (class, function, etc)}';

    protected $description = 'Code vectorization for semantic search';

    public function handle(VectorizationService $vectorization): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'index' => $this->handleIndex($vectorization),
            'search' => $this->handleSearch($vectorization),
            'symbols' => $this->handleSymbols($vectorization),
            'stats' => $this->handleStats($vectorization),
            'clear' => $this->handleClear($vectorization),
            default => $this->showHelp(),
        };
    }

    protected function handleIndex(VectorizationService $vectorization): int
    {
        $path = $this->option('path') ?? getcwd();

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $this->info('Indexing codebase...');
        $this->newLine();

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $result = $vectorization->index($path, function ($current, $total, $file) use ($bar) {
            $bar->setMaxSteps($total);
            $bar->setProgress($current);
            $bar->setMessage(basename($file));
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Files Processed', $result['files_processed']],
                ['Chunks Indexed', $result['chunks_indexed']],
                ['Index Path', $result['index_path']],
            ]
        );

        $this->info('Indexing complete.');

        return self::SUCCESS;
    }

    protected function handleSearch(VectorizationService $vectorization): int
    {
        $query = $this->option('query');

        if (! $query) {
            $query = $this->ask('Enter search query');
        }

        if (! $query) {
            $this->error('Search query is required');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $results = $vectorization->search($query, $limit);

        if ($results->isEmpty()) {
            $this->warn('No results found.');

            return self::SUCCESS;
        }

        $this->info("Found {$results->count()} results:");
        $this->newLine();

        foreach ($results as $i => $result) {
            $this->line(sprintf(
                '<comment>[%d]</comment> <info>%s</info> (%s:%d-%d)',
                $i + 1,
                $result['symbol'] ?? 'chunk',
                basename($result['file']),
                $result['start_line'],
                $result['end_line']
            ));

            // Show snippet
            $snippet = $this->truncate($result['content'], 200);
            $this->line("    {$snippet}");
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function handleSymbols(VectorizationService $vectorization): int
    {
        $type = $this->option('type');
        $symbols = $vectorization->listSymbols($type);

        if ($symbols->isEmpty()) {
            $this->warn('No symbols found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Symbol', 'Type', 'File', 'Line'],
            $symbols->map(fn ($s) => [
                $s['name'],
                $s['type'],
                basename($s['file']),
                $s['line'],
            ])->toArray()
        );

        return self::SUCCESS;
    }

    protected function handleStats(VectorizationService $vectorization): int
    {
        $stats = $vectorization->stats();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Chunks', $stats['total_chunks']],
                ['Total Files', $stats['total_files']],
                ['Total Symbols', $stats['total_symbols']],
                ['Index Size', $stats['index_size']],
                ['Index Path', $stats['index_path']],
            ]
        );

        return self::SUCCESS;
    }

    protected function handleClear(VectorizationService $vectorization): int
    {
        if (! $this->confirm('Are you sure you want to clear the index?')) {
            return self::SUCCESS;
        }

        $vectorization->clear();
        $this->info('Index cleared.');

        return self::SUCCESS;
    }

    protected function showHelp(): int
    {
        $this->info('Code Vectorization Commands:');
        $this->newLine();

        $this->line('  <comment>vectorize index</comment>   - Index the codebase');
        $this->line('  <comment>vectorize search</comment>  - Search indexed code');
        $this->line('  <comment>vectorize symbols</comment> - List indexed symbols');
        $this->line('  <comment>vectorize stats</comment>   - Show index statistics');
        $this->line('  <comment>vectorize clear</comment>   - Clear the index');

        $this->newLine();
        $this->line('Examples:');
        $this->line('  agents vectorize index --path=/path/to/project');
        $this->line('  agents vectorize search --query="user authentication"');
        $this->line('  agents vectorize symbols --type=class');

        return self::SUCCESS;
    }

    protected function truncate(string $text, int $length): string
    {
        $text = str_replace(["\n", "\r"], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length).'...';
    }
}
