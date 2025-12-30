<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CategoryAwareDiscoveryService
{
    /**
     * Score a URL's relevance to a specific category.
     * Returns a score from 0-100.
     */
    public function scoreUrlRelevance(string $url, string $category): int
    {
        $score = 0;
        $categoryConfig = config("categories.categories.{$category}");
        
        if (!$categoryConfig) {
            return 0;
        }

        $keywords = $categoryConfig['keywords'] ?? [];
        $urlLower = strtolower($url);
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        // URL path keyword matching (40 points max)
        $pathScore = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($urlLower, "/{$keyword}/") || str_contains($urlLower, "/{$keyword}-")) {
                $pathScore += 20;
                break;
            }
            if (str_contains($path, $keyword)) {
                $pathScore += 10;
            }
        }
        $score += min(40, $pathScore);

        // Domain authority (30 points max)
        $authorityDomains = $this->getAuthorityDomains($category);
        foreach ($authorityDomains as $authDomain) {
            if (str_contains($host, $authDomain)) {
                $score += 30;
                break;
            }
        }

        // Category-specific URL patterns (30 points max)
        $patternScore = $this->scoreByPattern($url, $category);
        $score += $patternScore;

        return min(100, $score);
    }

    /**
     * Determine if a link should be followed based on category and relevance.
     */
    public function shouldFollowLink(string $sourceUrl, string $category, string $targetUrl, int $depth): bool
    {
        $relevanceScore = $this->scoreUrlRelevance($targetUrl, $category);
        
        // Depth-based thresholds
        $thresholds = [
            0 => 15,  // Seed URLs: very permissive
            1 => 15,  // Depth 1: permissive (accepts single pattern match)
            2 => 25,  // Depth 2: moderate (needs path + pattern, or authority)
            3 => 40,  // Depth 3: strict
            4 => 50,  // Depth 4: very strict
        ];

        $threshold = $thresholds[$depth] ?? 80;

        if ($relevanceScore >= $threshold) {
            Log::debug('Link approved for crawling', [
                'target' => $targetUrl,
                'category' => $category,
                'depth' => $depth,
                'score' => $relevanceScore,
                'threshold' => $threshold,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get authority domains for a category.
     */
    private function getAuthorityDomains(string $category): array
    {
        $seedUrls = config("categories.categories.{$category}.seed_urls", []);
        $domains = [];

        foreach ($seedUrls as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host) {
                // Extract base domain (e.g., cnn.com from www.cnn.com)
                $parts = explode('.', $host);
                if (count($parts) >= 2) {
                    $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
                    $domains[] = $baseDomain;
                }
            }
        }

        return array_unique($domains);
    }

    /**
     * Score URL based on category-specific patterns.
     */
    private function scoreByPattern(string $url, string $category): int
    {
        $score = 0;
        $urlLower = strtolower($url);

        // Common news/article patterns
        $articlePatterns = ['/article/', '/news/', '/story/', '/post/', '/blog/', '/\d{4}/\d{2}/'];
        foreach ($articlePatterns as $pattern) {
            if (preg_match($pattern, $urlLower)) {
                $score += 10;
                break;
            }
        }

        // Category-specific patterns
        switch ($category) {
            case 'technology':
                if (preg_match('/(tech|software|hardware|app|device|gadget|review)/', $urlLower)) {
                    $score += 15;
                }
                break;
            case 'business':
                if (preg_match('/(market|stock|finance|invest|economy|company|startup)/', $urlLower)) {
                    $score += 15;
                }
                break;
            case 'ai':
                if (preg_match('/(ai|ml|machine-learning|neural|deep-learning|chatbot|llm)/', $urlLower)) {
                    $score += 15;
                }
                break;
            case 'sports':
                if (preg_match('/(sport|game|match|player|team|league|nfl|nba|mlb|nhl|soccer)/', $urlLower)) {
                    $score += 15;
                }
                break;
            case 'politics':
                if (preg_match('/(politic|election|congress|senate|government|policy|vote|campaign)/', $urlLower)) {
                    $score += 15;
                }
                break;
        }

        return min(30, $score);
    }

    /**
     * Extract category keywords from page content.
     */
    public function extractCategoryFromContent(string $title, string $description, string $url): ?string
    {
        $text = strtolower($title . ' ' . $description . ' ' . $url);
        $categories = config('categories.valid_categories', []);
        $scores = [];

        foreach ($categories as $category) {
            $keywords = config("categories.categories.{$category}.keywords", []);
            $score = 0;

            foreach ($keywords as $keyword) {
                $count = substr_count($text, strtolower($keyword));
                $score += $count;
            }

            $scores[$category] = $score;
        }

        arsort($scores);
        $topCategory = array_key_first($scores);
        $topScore = $scores[$topCategory] ?? 0;

        // Require at least 2 keyword matches to assign a category
        return $topScore >= 2 ? $topCategory : null;
    }
}
