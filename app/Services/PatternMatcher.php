<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Thunk\Verbs\Models\VerbEvent;

class PatternMatcher
{
    public function __construct(
        protected float $fuzzyThreshold = 0.6,
        protected int $embeddingTopK = 5,
    ) {}

    /**
     * Match a query using multi-layered approach:
     * 1. Exact match (fastest)
     * 2. Fuzzy/similarity match
     * 3. Vector embeddings (Ollama) - placeholder for now
     * 4. Keyword extraction fallback
     */
    public function match(string $query): Collection
    {
        // Layer 1: Exact match (fastest)
        $exact = $this->exactMatch($query);
        if ($exact->isNotEmpty()) {
            return $exact->map(fn ($p) => [
                'pattern' => $p,
                'confidence' => 1.0,
                'method' => 'exact',
            ]);
        }

        // Layer 2: Fuzzy/similarity match
        $fuzzy = $this->fuzzyMatch($query, $this->fuzzyThreshold);
        if ($fuzzy->isNotEmpty()) {
            return $fuzzy->map(fn ($p, $score) => [
                'pattern' => $p,
                'confidence' => $score,
                'method' => 'fuzzy',
            ]);
        }

        // Layer 3: Vector embeddings (placeholder - requires Ollama integration)
        // $embeddings = $this->embeddingMatch($query, $this->embeddingTopK);
        // if ($embeddings->isNotEmpty()) {
        //     return $embeddings;
        // }

        // Layer 4: Keyword extraction fallback
        return $this->keywordMatch($query)->map(fn ($p, $score) => [
            'pattern' => $p,
            'confidence' => $score,
            'method' => 'keyword',
        ]);
    }

    /**
     * Exact string match
     */
    protected function exactMatch(string $query): Collection
    {
        return VerbEvent::query()
            ->where('type', 'App\\Events\\Verbs\\Agents\\PatternCaptured')
            ->where('data->intent_pattern', $query)
            ->get()
            ->map(fn (VerbEvent $event) => $event->data);
    }

    /**
     * Fuzzy string similarity match using Levenshtein distance
     */
    protected function fuzzyMatch(string $query, float $threshold): Collection
    {
        $patterns = VerbEvent::query()
            ->where('type', 'App\\Events\\Verbs\\Agents\\PatternCaptured')
            ->get();

        $matches = collect();

        foreach ($patterns as $event) {
            $pattern = $event->data['intent_pattern'] ?? '';
            $similarity = $this->calculateSimilarity($query, $pattern);

            if ($similarity >= $threshold) {
                $matches->put($similarity, $event->data);
            }
        }

        return $matches->sortKeysDesc();
    }

    /**
     * Calculate string similarity (0.0 to 1.0)
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);

        similar_text($str1, $str2, $percent);

        return $percent / 100;
    }

    /**
     * Keyword-based matching (extract keywords and find patterns with matching keywords)
     */
    protected function keywordMatch(string $query): Collection
    {
        $keywords = $this->extractKeywords($query);

        $patterns = VerbEvent::query()
            ->where('type', 'App\\Events\\Verbs\\Agents\\PatternCaptured')
            ->get();

        $matches = collect();

        foreach ($patterns as $event) {
            $pattern = $event->data['intent_pattern'] ?? '';
            $patternKeywords = $this->extractKeywords($pattern);

            $overlap = count(array_intersect($keywords, $patternKeywords));
            if ($overlap > 0) {
                $score = $overlap / max(count($keywords), count($patternKeywords));
                $matches->put($score, $event->data);
            }
        }

        return $matches->sortKeysDesc();
    }

    /**
     * Extract keywords from text (simple implementation)
     */
    protected function extractKeywords(string $text): array
    {
        $text = strtolower($text);

        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        $words = preg_split('/\s+/', $text);

        return array_values(array_diff($words, $stopWords));
    }

    /**
     * Placeholder for embedding-based match (requires Ollama integration)
     */
    protected function embeddingMatch(string $query, int $topK): Collection
    {
        // TODO: Integrate with Ollama to generate embeddings
        // 1. Generate embedding for query using Ollama
        // 2. Store pattern embeddings in database (vector column)
        // 3. Use vector similarity search (cosine similarity)
        // 4. Return top K matches

        return collect();
    }
}
