<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = config('categories.categories');
        $categoryData = [];

        foreach ($categories as $id => $category) {
            $cacheFile = "cache/{$id}.json";
            $recordCount = 0;
            $lastUpdated = null;

            if (Storage::exists($cacheFile)) {
                $json = Storage::get($cacheFile);
                $data = json_decode($json, true);

                if ($data && isset($data['meta'])) {
                    $recordCount = $data['meta']['record_count'] ?? 0;
                    $lastUpdated = $data['meta']['generated_at'] ?? null;
                }
            }

            $categoryData[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'description' => $category['description'],
                'record_count' => $recordCount,
                'last_updated' => $lastUpdated,
            ];
        }

        $totalRecords = array_sum(array_column($categoryData, 'record_count'));

        return response()->json([
            'status' => 'success',
            'data' => [
                'categories' => $categoryData,
                'total_categories' => count($categoryData),
                'total_records' => $totalRecords,
            ],
            'meta' => [
                'version' => 'v1',
                'timestamp' => now()->toIso8601String(),
                'cache_age_seconds' => $this->getCacheAge(),
            ],
        ]);
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
