<?php

namespace App\Services;

use TeamTNT\TNTSearch\TNTSearch;
use Wamania\Snowball\Stemmer\English;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdvancedSearchService
{
    protected $stemmer;
    protected $synonyms;
    protected $indexManager;
    protected $queryCache = [];

    public function __construct(\App\Services\IndexManager $indexManager)
    {
        $this->stemmer = new English();
        $this->synonyms = config('search.synonyms', []);
        $this->indexManager = $indexManager;
    }

    /**
     * Build an in-memory index for a given set of records.
     */
    public function search(string $query, array $records, string $category, array $options = []): array
    {
        if (empty($records)) {
            return [];
        }

        // 1. Expand Query (Synonyms + Stemming) with Memoization
        $cacheKey = "{$category}_" . md5($query);
        $expandedQuery = $this->queryCache[$cacheKey] ??= $this->expandQuery($query, $category);
        
        try {
            // 2. Get/Build Index via IndexManager
            $tnt = $this->indexManager->getIndex($category, $records);
            
            $isBoolean = $this->isBooleanQuery($query);
            $results = $isBoolean ? $tnt->searchBoolean($expandedQuery) : $tnt->search($expandedQuery);

            Log::info("Search Debug", [
                'query' => $query,
                'expanded' => $expandedQuery,
                'category' => $category,
                'is_boolean' => $isBoolean,
                'tnt_results' => $results
            ]);

            $ids = $isBoolean ? ($results['ids'] ?? $results) : ($results['ids'] ?? []);
            $scores = $isBoolean ? array_fill_keys($ids, 1.0) : ($results['scores'] ?? []);
        } catch (\Exception $e) {
            Log::error("Indexing failure, falling back to string match: " . $e->getMessage());
            return $this->fallbackStringMatch($query, $records);
        }

        // 4. Map results back
        $finalResults = [];
        $totalResults = count($ids);

        if ($totalResults === 0 && !($options['no_fuzzy'] ?? false)) {
            return $this->fuzzySearch($query, $records, $category);
        }

        $maxRawScore = !empty($scores) ? max($scores) : 1;

        foreach ($ids as $id) {
            if (!isset($records[$id])) continue;
            
            $record = $records[$id];
            $score = $scores[$id] ?? 0;
            
            // Normalize relevance score (0.01 - 1.0)
            $normalizedRelevance = ($maxRawScore > 0) ? ($score / $maxRawScore) : 0.01;
            $record['relevance_score'] = (float) max(0.01, min(1.0, $normalizedRelevance));
            
            $record['match_score'] = $this->deriveMatchScore($record, $query, $score);
            $record['confidence'] = $this->calculateConfidence($score, $expandedQuery);
            $record['score_details'] = array_merge($record['score_details'] ?? [], [
                'tf_idf_factor' => $score,
                'stemmed_query' => $expandedQuery,
                'is_boolean' => $isBoolean
            ]);

            $record['highlighted_description'] = $this->highlight($record['description'] ?? '', $query);
            $finalResults[] = $record;
        }

        return $finalResults;
    }

    protected function fallbackStringMatch(string $query, array $records): array
    {
        $results = [];
        $query = strtolower($query);
        foreach ($records as $record) {
            if (str_contains(strtolower($record['title'] ?? ''), $query) || 
                str_contains(strtolower($record['description'] ?? ''), $query)) {
                $record['relevance_score'] = 0.5;
                $record['match_score'] = $this->deriveMatchScore($record, $query, 0.5);
                $record['highlighted_description'] = $this->highlight($record['description'] ?? '', $query);
                $results[] = $record;
            }
        }
        return $results;
    }

    protected function expandQuery(string $query, string $category): string
    {
        $terms = explode(' ', strtolower($query));
        $expanded = [];

        // Determine which synonym dictionaries to use
        $synonymSets = [];
        if ($category === 'all') {
            foreach ($this->synonyms as $catSyns) {
                $synonymSets[] = $catSyns;
            }
        } elseif (isset($this->synonyms[$category])) {
            $synonymSets[] = $this->synonyms[$category];
        }

        foreach ($terms as $term) {
            $term = trim($term, "\"'");
            if (empty($term)) continue;

            // Stem original
            $stemmed = $this->stemmer->stem($term);
            $expanded[] = $stemmed;

            // Add synonyms from all relevant sets
            foreach ($synonymSets as $set) {
                if (isset($set[$term])) {
                    foreach ($set[$term] as $syn) {
                        $expanded[] = $this->stemmer->stem($syn);
                    }
                }
            }
        }

        return implode(' ', array_unique($expanded));
    }

    protected function isBooleanQuery(string $query): bool
    {
        $operators = [' AND ', ' OR ', ' NOT ', '"'];
        foreach ($operators as $op) {
            if (str_contains(strtoupper($query), $op)) return true;
        }
        return false;
    }

    protected function fuzzySearch(string $query, array $records, string $category): array
    {
        $maxDistance = config('search.fuzziness_threshold', 2);
        $terms = explode(' ', strtolower($query));
        $results = [];

        foreach ($records as $record) {
            $title = strtolower($record['title'] ?? '');
            $minDist = 999;
            $matchTerm = '';

            foreach ($terms as $term) {
                // Approximate matching on title words
                $words = explode(' ', $title);
                foreach ($words as $word) {
                    $dist = levenshtein($term, $word);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $matchTerm = $word;
                    }
                }
            }

            if ($minDist <= $maxDistance) {
                $termLen = strlen($query);
                $record['relevance_score'] = (float) max(0.01, 1 - ($minDist / ($termLen ?: 1)));
                $record['match_score'] = $this->deriveMatchScore($record, $query, $record['relevance_score']);
                $record['score_details']['fuzzy_match'] = true;
                $record['score_details']['levenshtein_distance'] = $minDist;
                $record['score_details']['matched_term'] = $matchTerm;
                $record['highlighted_description'] = $this->highlight($record['description'] ?? '', $matchTerm);
                $results[] = $record;
            }
        }

        return $results;
    }

    protected function highlight(string $text, string $query): string
    {
        $terms = explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $query)));
        $terms = array_filter($terms, fn($t) => strlen($t) > 2);
        
        foreach ($terms as $term) {
            $text = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', $text);
        }

        return $text;
    }

    protected function deriveMatchScore(array $record, string $query, float $relevance): int
    {
        $query = strtolower(trim($query));
        $title = strtolower($record['title'] ?? '');
        $description = strtolower($record['description'] ?? '');
        
        // Exact match bonuses
        $bonus = 0;
        $titleMatch = str_contains($title, $query);
        $descMatch = str_contains($description, $query);
        
        if ($titleMatch) $bonus += 3;
        if ($descMatch) $bonus += 1;

        // Phrase match bonus (+2)
        if (str_contains($title, $query) || str_contains($description, $query)) {
            $bonus += 2;
        }

        // BM25 Logarithmic scaling
        $relevanceScore = 0;
        if ($relevance > 0.1) {
            // Cap relevance at log(10, 1.5) as requested
            $relevanceScore = min(log(10, 1.5), log($relevance + 1, 1.5));
        }
        
        $scaled = (int) ceil($relevanceScore + $bonus);
        
        return (int) min(10, max(1, $scaled));
    }

    protected function calculateConfidence(float $score, string $query): float
    {
        // Simple heuristic: normalize score by query length/complexity
        $termCount = count(explode(' ', $query)) ?: 1;
        $normalized = $score / ($termCount * 5); 
        return (float) max(0.2, min(1.0, round($normalized, 2)));
    }

    public function suggest(string $query, array $records): array
    {
        // Simple suggestion logic based on records in current index
        $terms = explode(' ', strtolower($query));
        $suggestions = [];

        foreach ($records as $record) {
            $words = explode(' ', strtolower($record['title'] ?? ''));
            foreach ($words as $word) {
                if (strlen($word) < 4) continue;
                foreach ($terms as $term) {
                    if (levenshtein($term, $word) === 1) {
                        $suggestions[] = $word;
                    }
                }
            }
        }

        return array_values(array_unique($suggestions));
    }
}
