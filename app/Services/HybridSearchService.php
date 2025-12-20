<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;

/**
 * Hybrid Search Service
 * 
 * Intelligently routes search queries between:
 * 1. EnhancedSearchService (new database-backed BM25 system)
 * 2. AdvancedSearchService (old JSON-based TNTSearch system)
 * 
 * Automatically falls back to old system if new system has no data.
 */
class HybridSearchService
{
    private EnhancedSearchService $enhancedSearch;
    private AdvancedSearchService $advancedSearch;
    
    public function __construct(
        EnhancedSearchService $enhancedSearch,
        AdvancedSearchService $advancedSearch
    ) {
        $this->enhancedSearch = $enhancedSearch;
        $this->advancedSearch = $advancedSearch;
    }

    /**
     * Search using hybrid approach.
     * 
     * @param string $query Search query
     * @param array $records Records from JSON cache (for fallback)
     * @param string $category Category filter
     * @param array $options Search options
     * @return array Search results
     */
    public function search(string $query, array $records, string $category, array $options = []): array
    {
        // Check if new system has data
        $hasDocuments = Document::count() > 0;
        
        if ($hasDocuments) {
            // Try new database-backed search first
            try {
                $limit = $options['limit'] ?? 20;
                $categoryFilter = ($category === 'all') ? null : $category;
                
                $results = $this->enhancedSearch->search($query, $limit, $categoryFilter);
                
                if (!empty($results)) {
                    Log::info('Hybrid search: Using EnhancedSearchService', [
                        'query' => $query,
                        'results' => count($results),
                        'category' => $category
                    ]);
                    
                    // Transform to match old format
                    return $this->transformEnhancedResults($results);
                }
            } catch (\Exception $e) {
                Log::warning('EnhancedSearchService failed, falling back to AdvancedSearchService', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Fall back to old JSON-based search
        Log::info('Hybrid search: Using AdvancedSearchService (fallback)', [
            'query' => $query,
            'category' => $category,
            'has_documents' => $hasDocuments
        ]);
        
        return $this->advancedSearch->search($query, $records, $category, $options);
    }

    /**
     * Fuzzy search (delegates to old system).
     */
    public function fuzzySearch(string $query, array $records, string $category): array
    {
        return $this->advancedSearch->fuzzySearch($query, $records, $category);
    }

    /**
     * Get search suggestions.
     */
    public function suggest(string $query, array $records): array
    {
        // Try new system first if it has data
        if (Document::count() > 0) {
            try {
                $suggestions = $this->enhancedSearch->getSuggestions($query, 10);
                if (!empty($suggestions)) {
                    return $suggestions;
                }
            } catch (\Exception $e) {
                Log::debug('EnhancedSearchService suggestions failed', ['error' => $e->getMessage()]);
            }
        }
        
        // Fall back to old system
        return $this->advancedSearch->suggest($query, $records);
    }

    /**
     * Transform EnhancedSearchService results to match AdvancedSearchService format.
     */
    private function transformEnhancedResults(array $results): array
    {
        return array_map(function ($result) {
            return [
                'url' => $result['url'],
                'title' => $result['title'],
                'description' => $result['description'],
                'category' => $result['category'],
                'indexed_at' => $result['indexed_at'],
                'relevance_score' => $result['score'],
                'match_score' => $result['score'],
                'confidence' => min(1.0, $result['score'] / 10), // Normalize
                'highlighted_description' => $result['description'], // TODO: Add highlighting
                'score_details' => [
                    'bm25_score' => $result['score'],
                    'source' => 'database'
                ]
            ];
        }, $results);
    }
}
