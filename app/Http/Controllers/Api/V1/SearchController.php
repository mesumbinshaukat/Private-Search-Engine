<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    protected $hybridSearch;

    public function __construct(\App\Services\HybridSearchService $hybridSearch)
    {
        $this->hybridSearch = $hybridSearch;
    }

    public function search(SearchRequest $request)
    {
        $query = $request->input('q');
        $category = $request->input('category', 'all');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', config('search.default_per_page', 20));
        
        // New optional params
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $sort = $request->input('sort', 'relevance');
        $debug = $request->boolean('debug', false);
        $forceFuzzy = $request->boolean('fuzzy', false);

        // Auto-refresh trigger (1 hour age)
        if ($this->getCacheAge($category) > 3600) {
            $lockKey = "refreshing_cache_" . $category;
            // Only trigger if not already triggered in last 5 minutes
            if (!\Illuminate\Support\Facades\Cache::has($lockKey)) {
                \Illuminate\Support\Facades\Cache::put($lockKey, true, 300);
                \Illuminate\Support\Facades\Log::info("Triggering auto-refresh for {$category}");
                \Illuminate\Support\Facades\Artisan::call('cache:refresh');
            }
        }

        // Cache Search Results (10 min)
        $cacheKey = "search_results:" . $category . ":" . md5(serialize($request->all()));
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function() use ($request, $query, $category, $page, $perPage, $fromDate, $toDate, $sort, $debug, $forceFuzzy) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            try {
                $records = $this->getRecords($category);
                
                // Apply Date Filtering
                if ($fromDate || $toDate) {
                    $records = $this->filterByDate($records, $fromDate, $toDate);
                }

                $options = [
                    'debug' => $debug,
                    'no_fuzzy' => !$forceFuzzy && $request->has('q')
                ];

                if ($forceFuzzy) {
                    $results = $this->hybridSearch->fuzzySearch($query, $records, $category);
                } else {
                    $results = $this->hybridSearch->search($query, $records, $category, $options);
                }

                if (empty($results)) {
                    $suggestions = $this->hybridSearch->suggest($query, $records);
                    
                    return response()->json([
                        'status' => 'error',
                        'error' => [
                            'code' => 'NO_RESULTS',
                            'message' => 'No results found for query',
                            'query_suggestions' => $suggestions,
                        ],
                        'meta' => [
                            'version' => 'v1',
                            'timestamp' => now()->toIso8601String(),
                        ],
                    ], 404);
                }

                // Apply Sorting
                $results = $this->sortResults($results, $sort);

                $paginated = $this->paginateResults($results, $page, $perPage);
                $indexDate = $this->getLatestIndexDate($results, $category);

                $duration = microtime(true) - $startTime;
                $memoryUsed = memory_get_usage() - $startMemory;

                if ($duration > config('search.slow_threshold_seconds', 2.0)) {
                    Log::warning("Slow search detected", [
                        'query' => $query,
                        'duration' => $duration,
                        'memory' => $memoryUsed
                    ]);
                }

                $responseData = [
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
                        'performance' => [
                            'time_ms' => round($duration * 1000, 2),
                            'memory_mb' => round($memoryUsed / 1024 / 1024, 2)
                        ]
                    ],
                ];

                if ($debug) {
                    $responseData['debug'] = [
                        'total_scanned' => count($records),
                        'total_matched' => count($results),
                        'query_expansion' => $query . ' -> (stemmed terms)',
                        'sort_method' => $sort,
                    ];
                }

                return response()->json($responseData);

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
        });
    }

    public function getRandomTopic()
    {
        try {
            $categories = config('categories.valid_categories');
            if (empty($categories)) {
                throw new \Exception("No valid categories configured.");
            }

            // Shuffle categories and try until we find one with records
            shuffle($categories);
            $randomCat = null;
            $records = [];

            foreach ($categories as $cat) {
                $categoryRecords = $this->loadCategoryRecords($cat);
                if (!empty($categoryRecords)) {
                    $randomCat = $cat;
                    $records = $categoryRecords;
                    break;
                }
            }

            if (empty($records)) {
                return response()->json([
                    'status' => 'error',
                    'error' => [
                        'code' => 'NO_DATA',
                        'message' => 'No indexed data available in any category',
                    ]
                ], 404);
            }

            $record = $records[array_rand($records)];
            $title = $record['title'] ?? 'Unknown Topic';
            $topic = $this->deriveTopicFromTitle($title);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'topic' => $topic,
                    'category' => $randomCat,
                    'original_title' => $title,
                    'url' => $record['url'] ?? '',
                ],
                'meta' => [
                    'version' => 'v1',
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Topic generation error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to generate random topic',
                ]
            ], 500);
        }
    }

    private function deriveTopicFromTitle(string $title): string
    {
        // Clean up common noise patterns
        $topic = preg_replace('/ - .+$| \| .+$|: .+$| \.\.\.$/', '', $title);
        $topic = trim($topic);
        
        // Ensure it's not too long for a "topic"
        $words = explode(' ', $topic);
        if (count($words) > 6) {
            $topic = implode(' ', array_slice($words, 0, 6));
        }

        return $topic;
    }

    private function getRecords(string $category): array
    {
        $categories = ($category === 'all') 
            ? config('categories.valid_categories') 
            : [$category];

        $allRecords = [];
        foreach ($categories as $cat) {
            $allRecords = array_merge($allRecords, $this->loadCategoryRecords($cat));
        }

        return $allRecords;
    }

    private function loadCategoryRecords(string $cat): array
    {
        $cacheFile = "cache/{$cat}.json";
        if (!Storage::exists($cacheFile)) return [];

        $data = json_decode(Storage::get($cacheFile), true);
        if (!$data || !isset($data['records'])) return [];

        return array_map(function($record) use ($cat) {
            $record['category'] = $cat;
            return $record;
        }, $data['records']);
    }

    private function filterByDate(array $records, ?string $from, ?string $to): array
    {
        return array_filter($records, function($record) use ($from, $to) {
            if (!isset($record['published_at'])) return true;
            
            $pubDate = strtotime($record['published_at']);
            if ($from && strtotime($from) > $pubDate) return false;
            if ($to && strtotime($to) < $pubDate) return false;
            
            return true;
        });
    }

    private function sortResults(array $results, string $sort): array
    {
        switch ($sort) {
            case 'date_desc':
                usort($results, fn($a, $b) => ($b['published_at'] ?? '') <=> ($a['published_at'] ?? ''));
                break;
            case 'date_asc':
                usort($results, fn($a, $b) => ($a['published_at'] ?? '') <=> ($b['published_at'] ?? ''));
                break;
            case 'relevance':
            default:
                // Prioritize relevance_score then match_score
                usort($results, function($a, $b) {
                    if (($b['relevance_score'] ?? 0) == ($a['relevance_score'] ?? 0)) {
                        return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
                    }
                    return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
                });
                break;
        }

        return $results;
    }

    private function paginateResults(array $results, int $page, int $perPage): array
    {
        $total = count($results);
        $totalPages = (int) ceil($total / $perPage);
        
        // Use array_chunk for efficient access to page
        $chunks = array_chunk($results, $perPage);
        $items = $chunks[$page - 1] ?? [];

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
        $latestDate = \App\Models\IndexMetadata::query()
            ->when($category !== 'all', fn($q) => $q->where('category', $category))
            ->latest('date')
            ->value('date');

        return $latestDate ? $latestDate->format('Y-m-d') : now()->format('Y-m-d');
    }
}
