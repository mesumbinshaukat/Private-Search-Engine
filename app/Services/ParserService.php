<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class ParserService
{
    private UrlNormalizerService $urlNormalizer;

    public function __construct(UrlNormalizerService $urlNormalizer)
    {
        $this->urlNormalizer = $urlNormalizer;
    }

    public function extractLinks(string $html, string $baseUrl): array
    {
        try {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $links = $xpath->query('//a[@href]/@href');
            $absoluteLinks = [];

            foreach ($links as $link) {
                $href = trim($link->nodeValue);
                if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                    continue;
                }

                $absoluteUrl = $this->urlNormalizer->makeAbsolute($href, $baseUrl);
                if ($absoluteUrl) {
                    $normalized = $this->urlNormalizer->normalize($absoluteUrl);
                    if ($normalized) {
                        $absoluteLinks[] = $normalized['normalized'];
                    }
                }
            }

            return array_unique($absoluteLinks);

        } catch (\Exception $e) {
            Log::error('Link extraction exception', [
                'base_url' => $baseUrl,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function parse(string $html, string $originalUrl): ?array
    {
        try {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);

            $title = $this->extractTitle($xpath);
            $canonicalUrl = $this->extractCanonicalUrl($xpath, $originalUrl);
            $description = $this->extractDescription($xpath);
            $publishedAt = $this->extractPublishedDate($xpath);

            if (!$title) {
                Log::warning('No title found', ['url' => $originalUrl]);
                return null;
            }

            $normalizedData = $this->urlNormalizer->normalize($canonicalUrl);
            if (!$normalizedData) {
                Log::warning('URL normalization failed', ['url' => $canonicalUrl]);
                return null;
            }

            $contentHash = $this->generateContentHash($title, $description);

            return [
                'url' => $originalUrl,
                'canonical_url' => $normalizedData['normalized'],
                'url_hash' => $normalizedData['hash'],
                'title' => $title,
                'description' => $description,
                'published_at' => $publishedAt,
                'content_hash' => $contentHash,
            ];

        } catch (\Exception $e) {
            Log::error('Parser exception', [
                'url' => $originalUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function extractTitle(DOMXPath $xpath): ?string
    {
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            return trim($ogTitle->item(0)->nodeValue);
        }

        $twitterTitle = $xpath->query('//meta[@name="twitter:title"]/@content');
        if ($twitterTitle->length > 0) {
            return trim($twitterTitle->item(0)->nodeValue);
        }

        $titleTag = $xpath->query('//title');
        if ($titleTag->length > 0) {
            return trim($titleTag->item(0)->nodeValue);
        }

        $h1 = $xpath->query('//h1');
        if ($h1->length > 0) {
            return trim($h1->item(0)->nodeValue);
        }

        return null;
    }

    private function extractCanonicalUrl(DOMXPath $xpath, string $fallback): string
    {
        $canonical = $xpath->query('//link[@rel="canonical"]/@href');
        if ($canonical->length > 0) {
            return trim($canonical->item(0)->nodeValue);
        }

        $ogUrl = $xpath->query('//meta[@property="og:url"]/@content');
        if ($ogUrl->length > 0) {
            return trim($ogUrl->item(0)->nodeValue);
        }

        return $fallback;
    }

    private function extractDescription(DOMXPath $xpath): ?string
    {
        $ogDescription = $xpath->query('//meta[@property="og:description"]/@content');
        if ($ogDescription->length > 0) {
            return trim($ogDescription->item(0)->nodeValue);
        }

        $metaDescription = $xpath->query('//meta[@name="description"]/@content');
        if ($metaDescription->length > 0) {
            return trim($metaDescription->item(0)->nodeValue);
        }

        $twitterDescription = $xpath->query('//meta[@name="twitter:description"]/@content');
        if ($twitterDescription->length > 0) {
            return trim($twitterDescription->item(0)->nodeValue);
        }

        return null;
    }

    private function extractPublishedDate(DOMXPath $xpath): ?string
    {
        $articlePublished = $xpath->query('//meta[@property="article:published_time"]/@content');
        if ($articlePublished->length > 0) {
            return trim($articlePublished->item(0)->nodeValue);
        }

        $datePublished = $xpath->query('//meta[@property="datePublished"]/@content');
        if ($datePublished->length > 0) {
            return trim($datePublished->item(0)->nodeValue);
        }

        $timeElement = $xpath->query('//time[@datetime]/@datetime');
        if ($timeElement->length > 0) {
            return trim($timeElement->item(0)->nodeValue);
        }

        return null;
    }

    private function generateContentHash(string $title, ?string $description): string
    {
        $content = $title . ($description ?? '');
        return hash('sha256', $content);
    }
}
