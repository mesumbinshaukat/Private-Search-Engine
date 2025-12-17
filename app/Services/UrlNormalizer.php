<?php

namespace App\Services;

class UrlNormalizer
{
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid',
    ];

    public function normalize(string $url): string
    {
        $parsedUrl = parse_url($url);

        if (!$parsedUrl) {
            return $url;
        }

        $scheme = isset($parsedUrl['scheme']) ? strtolower($parsedUrl['scheme']) : 'https';
        $host = isset($parsedUrl['host']) ? strtolower($parsedUrl['host']) : '';
        $path = $parsedUrl['path'] ?? '/';
        $query = $parsedUrl['query'] ?? '';

        $path = rtrim($path, '/') ?: '/';

        if ($query) {
            parse_str($query, $params);
            $params = $this->removeTrackingParams($params);
            ksort($params);
            $query = http_build_query($params);
        }

        $normalized = $scheme . '://' . $host . $path;

        if ($query) {
            $normalized .= '?' . $query;
        }

        return $normalized;
    }

    private function removeTrackingParams(array $params): array
    {
        foreach (self::TRACKING_PARAMS as $trackingParam) {
            unset($params[$trackingParam]);
        }

        return $params;
    }
}
