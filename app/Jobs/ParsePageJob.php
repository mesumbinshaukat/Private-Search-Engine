<?php

namespace App\Jobs;

use App\Models\CrawlJob;
use App\Models\ParsedRecord;
use App\Services\ParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ParsePageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;

    private CrawlJob $crawlJob;
    private string $filename;

    public function __construct(CrawlJob $crawlJob, string $filename)
    {
        $this->crawlJob = $crawlJob;
        $this->filename = $filename;
    }

    public function handle(ParserService $parser): void
    {
        Log::info('Starting parse', [
            'url' => $this->crawlJob->url,
            'filename' => $this->filename,
        ]);

        if (!Storage::exists($this->filename)) {
            Log::error('HTML file not found', ['filename' => $this->filename]);
            return;
        }

        $html = Storage::get($this->filename);
        $parsed = $parser->parse($html, $this->crawlJob->url);

        if (!$parsed) {
            Log::warning('Parse failed', ['url' => $this->crawlJob->url]);
            return;
        }

        if (ParsedRecord::isDuplicate($parsed['canonical_url'])) {
            Log::info('Duplicate URL detected (canonical)', [
                'url' => $this->crawlJob->url,
                'canonical_url' => $parsed['canonical_url'],
            ]);
            return;
        }

        if (ParsedRecord::isDuplicateByHash($parsed['content_hash'])) {
            Log::info('Duplicate content detected (hash)', [
                'url' => $this->crawlJob->url,
                'content_hash' => $parsed['content_hash'],
            ]);
            return;
        }

        ParsedRecord::create([
            'url' => $parsed['url'],
            'canonical_url' => $parsed['canonical_url'],
            'title' => $parsed['title'],
            'description' => $parsed['description'],
            'published_at' => $parsed['published_at'],
            'category' => $this->crawlJob->category,
            'content_hash' => $parsed['content_hash'],
            'parsed_at' => now(),
        ]);

        Log::info('Parse completed', [
            'url' => $this->crawlJob->url,
            'title' => $parsed['title'],
        ]);

        Storage::delete($this->filename);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Parse job failed', [
            'url' => $this->crawlJob->url,
            'error' => $exception->getMessage(),
        ]);
    }
}
