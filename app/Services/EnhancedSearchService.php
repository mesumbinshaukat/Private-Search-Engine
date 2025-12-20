<?php

namespace App\Services;

use App\Models\Token;
use App\Models\Posting;
use App\Models\Document;
use App\Models\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnhancedSearchService
{
    private IndexEngineService $indexEngine;

    public function __construct(IndexEngineService $indexEngine)
    {
        $this->indexEngine = $indexEngine;
    }

    /**
     * Search documents using BM25 scoring.
     *
     * @param string $query Search query
     * @param int $limit Maximum results to return
     * @param string|null $category Filter by category
     * @return array Search results with scores
     */
    public function search(string $query, int $limit = 20, ?string $category = null): array
    {
        $queryTokens = $this->tokenizeQuery($query);
        
        if (empty($queryTokens)) {
            return [];
        }

        // Get token IDs
        $tokens = Token::whereIn('token', $queryTokens)->get()->keyBy('token');
        
        if ($tokens->isEmpty()) {
            return [];
        }

        // Get postings for all query tokens
        $tokenIds = $tokens->pluck('id')->toArray();
        $postings = Posting::whereIn('token_id', $tokenIds)
            ->with(['url.document'])
            ->get()
            ->groupBy('url_id');

        // Calculate BM25 scores
        $totalDocs = $this->indexEngine->getTotalDocumentCount();
        $avgDocLength = $this->indexEngine->getAverageDocumentLength();
        $scores = [];

        foreach ($postings as $urlId => $urlPostings) {
            $document = $urlPostings->first()->url->document;
            if (!$document) {
                continue;
            }

            // Filter by category if specified
            if ($category && $urlPostings->first()->url->category !== $category) {
                continue;
            }

            $score = 0.0;

            foreach ($urlPostings as $posting) {
                $token = $tokens->firstWhere('id', $posting->token_id);
                if (!$token) {
                    continue;
                }

                $idf = $token->calculateIdf($totalDocs);
                $bm25 = $posting->calculateBm25(
                    $idf,
                    $document->word_count,
                    $avgDocLength
                );

                $score += $bm25;
            }

            // Apply freshness boost
            if ($document->indexed_at) {
                $daysSinceIndex = $document->indexed_at->diffInDays(now());
                if ($daysSinceIndex < 7) {
                    $score *= 1.2; // 20% boost for recent content
                }
            }

            // Apply link popularity boost
            $inboundLinks = $urlPostings->first()->url->inboundLinks()->count();
            if ($inboundLinks > 0) {
                $score *= (1 + (min($inboundLinks, 10) * 0.05)); // Up to 50% boost
            }

            $scores[$urlId] = $score;
        }

        // Sort by score descending
        arsort($scores);
        $scores = array_slice($scores, 0, $limit, true);

        // Build result array
        $results = [];
        foreach ($scores as $urlId => $score) {
            $url = Url::with('document')->find($urlId);
            if (!$url || !$url->document) {
                continue;
            }

            $results[] = [
                'url' => $url->normalized_url,
                'title' => $url->document->title,
                'description' => $url->document->description,
                'score' => round($score, 4),
                'indexed_at' => $url->document->indexed_at?->toIso8601String(),
                'category' => $url->category,
            ];
        }

        Log::info('Search completed', [
            'query' => $query,
            'results' => count($results),
            'category' => $category,
        ]);

        return $results;
    }

    /**
     * Tokenize search query.
     */
    private function tokenizeQuery(string $query): array
    {
        // Normalize
        $query = strtolower($query);
        $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
        
        // Split into words
        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter short words
        return array_filter($words, fn($word) => strlen($word) >= 3);
    }

    /**
     * Get search suggestions based on token frequency.
     */
    public function getSuggestions(string $prefix, int $limit = 10): array
    {
        $prefix = strtolower(trim($prefix));
        
        if (strlen($prefix) < 2) {
            return [];
        }

        return Token::where('token', 'like', $prefix . '%')
            ->orderBy('document_frequency', 'desc')
            ->limit($limit)
            ->pluck('token')
            ->toArray();
    }
}
