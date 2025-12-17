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

            $paginated = $this->paginateResults($results, $page, $perPage);

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
                    'index_date' => now()->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Search error', [
                'query' => $query,
                'error' => $e->getMessage(),
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
                if ($this->matchesQuery($record, $query)) {
                    $record['category'] = $cat;
                    $allResults[] = $record;
                }
            }
        }

        return $allResults;
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
}
