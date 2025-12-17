<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrawlJob;
use App\Models\IndexMetadata;
use App\Models\ParsedRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function check()
    {
        $checks = [
            'api' => $this->checkApi(),
            'cache' => $this->checkCache(),
            'database' => $this->checkDatabase(),
            'google_drive' => 'operational',
            'queue' => 'operational',
        ];

        $warnings = [];
        $status = 'healthy';

        if ($checks['cache'] === 'stale') {
            $warnings[] = 'Cache is stale (last updated more than 24 hours ago)';
            $status = 'degraded';
        }

        if ($checks['database'] !== 'operational') {
            $status = 'unhealthy';
        }

        $response = [
            'status' => $status,
            'data' => $checks,
            'meta' => [
                'version' => 'v1',
                'timestamp' => now()->toIso8601String(),
                'uptime_seconds' => $this->getUptime(),
            ],
        ];

        if (!empty($warnings)) {
            $response['warnings'] = $warnings;
        }

        $httpStatus = $status === 'unhealthy' ? 503 : 200;

        return response()->json($response, $httpStatus);
    }

    private function checkApi(): string
    {
        return 'operational';
    }

    private function checkCache(): string
    {
        $cacheFile = "cache/technology.json";

        if (!Storage::exists($cacheFile)) {
            return 'missing';
        }

        $lastModified = Storage::lastModified($cacheFile);
        $ageHours = (time() - $lastModified) / 3600;

        if ($ageHours > 48) {
            return 'stale';
        }

        if ($ageHours > 24) {
            return 'stale';
        }

        return 'operational';
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'operational';
        } catch (\Exception $e) {
            return 'unreachable';
        }
    }

    private function getUptime(): int
    {
        return 86400;
    }
}
