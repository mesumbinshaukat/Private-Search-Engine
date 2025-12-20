<?php

namespace App\Services;

use App\Models\Metric;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    /**
     * Record a metric.
     */
    public function record(string $type, float $value, ?string $category = null, ?array $metadata = null): void
    {
        Metric::create([
            'metric_type' => $type,
            'category' => $category,
            'value' => $value,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Record fetch rate (URLs per minute).
     */
    public function recordFetchRate(int $urlCount, int $seconds): void
    {
        $rate = ($urlCount / $seconds) * 60;
        $this->record('fetch_rate', $rate);
    }

    /**
     * Record HTTP status distribution.
     */
    public function recordHttpStatus(int $status, string $category): void
    {
        $this->record('http_status', $status, $category);
    }

    /**
     * Record queue backlog size.
     */
    public function recordQueueBacklog(int $size): void
    {
        $this->record('queue_backlog', $size);
    }

    /**
     * Record parse success/failure.
     */
    public function recordParseResult(bool $success, string $category): void
    {
        $this->record('parse_success', $success ? 1 : 0, $category);
    }

    /**
     * Record index build time.
     */
    public function recordIndexBuildTime(float $seconds, string $category): void
    {
        $this->record('index_build_time', $seconds, $category);
    }

    /**
     * Get metrics summary for a time period.
     */
    public function getSummary(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        return [
            'fetch_rate_avg' => Metric::byType('fetch_rate')
                ->where('recorded_at', '>=', $since)
                ->avg('value'),
            
            'http_status_distribution' => Metric::byType('http_status')
                ->where('recorded_at', '>=', $since)
                ->select('value', DB::raw('count(*) as count'))
                ->groupBy('value')
                ->get()
                ->pluck('count', 'value')
                ->toArray(),
            
            'queue_backlog_current' => Metric::byType('queue_backlog')
                ->latest('recorded_at')
                ->value('value'),
            
            'parse_success_rate' => $this->calculateSuccessRate('parse_success', $since),
        ];
    }

    /**
     * Calculate success rate for a metric type.
     */
    private function calculateSuccessRate(string $type, $since): float
    {
        $total = Metric::byType($type)
            ->where('recorded_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $successes = Metric::byType($type)
            ->where('recorded_at', '>=', $since)
            ->where('value', 1)
            ->count();

        return ($successes / $total) * 100;
    }
}
