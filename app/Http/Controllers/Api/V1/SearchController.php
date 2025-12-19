<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    public function search(SearchRequest $request)
    {
        $query = $request->input('q');
        $category = $request->input('category', 'all');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        try {
            $results = $this->performSearch($query, $category);

            if (empty($results)) {
                return response()->json([
                    'status' => 'error',
                    'error' => [
                        'code' => 'NO_RESULTS',
                        'message' => 'No results found for query',
                    ],
                    'meta' => [
                        'version' => 'v1',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ], 404);
            }

            // Sort results by score descending
            usort($results, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

            $paginated = $this->paginateResults($results, $page, $perPage);
            $indexDate = $this->getLatestIndexDate($results, $category);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'query' => $query,
                    'category' => $category,
                    'results' => $paginated['items'],
                    'pagination' => $paginated['pagination'],
                ],
                'meta' => [
                    'version' => 'v1',
                    'timestamp' => now()->toIso8601String(),
                    'cache_age_seconds' => $this->getCacheAge(),
                    'index_date' => $indexDate,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Search error', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred',
                ],
                'meta' => [
                    'version' => 'v1',
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 500);
        }
    }

    private function performSearch(string $query, string $category): array
    {
        $categories = $category === 'all' 
            ? config('categories.valid_categories') 
            : [$category];

        $allResults = [];

        foreach ($categories as $cat) {
            $cacheFile = "cache/{$cat}.json";

            if (!Storage::exists($cacheFile)) {
                continue;
            }

            $json = Storage::get($cacheFile);
            $data = json_decode($json, true);

            if (!$data || !isset($data['records'])) {
                continue;
            }

            foreach ($data['records'] as $record) {
                $scoreData = $this->calculateScore($record, $query);
                if ($scoreData['score'] > 0) {
                    $record['category'] = $cat;
                    $record['match_score'] = $scoreData['score'];
                    $record['score_details'] = $scoreData['details'];
                    $allResults[] = $record;
                }
            }
        }

        return $allResults;
    }

    private function calculateScore(array $record, string $query): array
    {
        $query = strtolower(trim($query));
        $title = strtolower($record['title'] ?? '');
        $description = strtolower($record['description'] ?? '');
        $terms = explode(' ', $query);
        $terms = array_filter($terms, fn($t) => strlen($t) > 1);

        if (empty($terms)) {
            return ['score' => 0, 'details' => []];
        }

        $score = 0;
        $details = [
            'title_matches' => 0,
            'description_matches' => 0,
            'phrase_match' => false,
        ];

        // 1. Exact phrase match (Highest Priority)
        if (str_contains($title, $query)) {
            $score += 50;
            $details['phrase_match'] = 'title';
        } elseif (str_contains($description, $query)) {
            $score += 25;
            $details['phrase_match'] = 'description';
        }

        // 2. Individual term matches
        foreach ($terms as $term) {
            // Title matches are weighted more (10 points each)
            $titleCount = substr_count($title, $term);
            if ($titleCount > 0) {
                $score += min(30, $titleCount * 10);
                $details['title_matches'] += $titleCount;
            }

            // Description matches are weighted less (3 points each)
            $descCount = substr_count($description, $term);
            if ($descCount > 0) {
                $score += min(15, $descCount * 3);
                $details['description_matches'] += $descCount;
            }
        }

        // 3. Normalization to 1-10 scale
        // A "perfect" result for a simple query might naturally hit 50-80 points.
        // We'll cap the raw score and map it.
        $normalized = min(10, max(1, ceil($score / 8)));

        // Edge case: if it exists at all but score is somehow 0
        if ($score > 0 && $normalized < 1) {
            $normalized = 1;
        }

        return [
            'score' => (int) $normalized,
            'details' => $details
        ];
    }

    private function matchesQuery(array $record, string $query): bool
    {
        $query = strtolower($query);
        $title = strtolower($record['title'] ?? '');
        $description = strtolower($record['description'] ?? '');

        return str_contains($title, $query) || str_contains($description, $query);
    }

    private function paginateResults(array $results, int $page, int $perPage): array
    {
        $total = count($results);
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $items = array_slice($results, $offset, $perPage);

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_results' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1,
            ],
        ];
    }

    private function getCacheAge(): int
    {
        $cacheFile = "cache/technology.json";

        if (!Storage::exists($cacheFile)) {
            return 0;
        }

        $lastModified = Storage::lastModified($cacheFile);
        return time() - $lastModified;
    }

    private function getLatestIndexDate(array $results, string $category): string
    {
        $query = \App\Models\IndexMetadata::query();

        if ($category !== 'all') {
            $query->where('category', $category);
        }

        $latestDate = $query->latest('date')->value('date');

        return $latestDate ? $latestDate->format('Y-m-d') : now()->format('Y-m-d');
    }
}
