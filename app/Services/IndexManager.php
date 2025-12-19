<?php

namespace App\Services;

use TeamTNT\TNTSearch\TNTSearch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class IndexManager
{
    protected string $storagePath;
    protected $stemmer;

    public function __construct()
    {
        $this->storagePath = storage_path('app/search/');
        if (!file_exists($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
        $this->stemmer = new \Wamania\Snowball\Stemmer\English();
    }

    /**
     * Get a TNTSearch instance for a given category.
     * Rebuilds if the index doesn't exist or cache is newer.
     */
    public function getIndex(string $category, array $records): TNTSearch
    {
        $indexName = "{$category}.index";
        $indexPath = $this->storagePath . $indexName;
        
        $tnt = new TNTSearch;
        $tnt->loadConfig([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'storage' => $this->storagePath,
        ]);

        if (!$this->shouldRebuild($category, $indexPath)) {
            $tnt->selectIndex($indexName);
            return $tnt;
        }

        return $this->buildIndex($category, $records, $indexName);
    }

    protected function shouldRebuild(string $category, string $indexPath): bool
    {
        if (!file_exists($indexPath)) {
            return true;
        }

        $cacheFile = "cache/{$category}.json";
        if (!Storage::exists($cacheFile)) {
            return false; // Should not happen if we have records
        }

        $cacheTime = Storage::lastModified($cacheFile);
        $indexTime = filemtime($indexPath);

        return $cacheTime > $indexTime;
    }

    protected function buildIndex(string $category, array $records, string $indexName): TNTSearch
    {
        Log::info("Building search index for category: {$category}", ['record_count' => count($records)]);
        
        $tnt = new TNTSearch;
        $tnt->loadConfig([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'storage' => $this->storagePath,
        ]);

        $indexer = $tnt->createIndex($indexName);
        $indexer->setPrimaryKey('id');
        
        $count = 0;
        foreach ($records as $index => $record) {
            $title = $this->stemText($record['title'] ?? '');
            $description = $this->stemText($record['description'] ?? '');

            $indexer->insert([
                'id' => $index,
                'title' => $title,
                'description' => $description,
                'url' => $record['url'] ?? '',
            ]);
            $count++;
        }

        Log::info("Index build complete for {$category}", ['inserted' => $count]);

        $tnt->selectIndex($indexName);
        return $tnt;
    }

    protected function stemText(string $text): string
    {
        $words = explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $text)));
        $stemmed = array_map([$this->stemmer, 'stem'], array_filter($words));
        return implode(' ', $stemmed);
    }
}
