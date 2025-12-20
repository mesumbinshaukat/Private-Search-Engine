# Private Search Engine Terminal Commands

This document lists all available Artisan commands for managing the search engine cycle and data.

## Core Refresh Cycle

### `php artisan master:refresh`
Runs the entire search engine cycle (crawl, process, index, upload, cache).
- `--async`: (Recommended) Dispatches the job to the queue, avoiding timeouts.
- `--fresh`: Wipes the existing `ParsedRecord` and `CrawlJob` tables before starting. Use this to rebuild the index from scratch.
Example: `php artisan master:refresh --async --fresh`

### Reset Crawler State
```bash
php artisan migrate:fresh
php artisan crawl:daily --fresh
```

### URL Normalization Errors
If you encounter "NOT NULL constraint violation" errors on `url_hash`:

**1. Check logs for normalization failures:**
```bash
# Windows PowerShell
Get-Content storage/logs/laravel.log -Tail 100 | Select-String "normalization failed"

# Linux/Mac
tail -f storage/logs/laravel.log | grep "normalization failed"
```

**2. Identify problematic URLs in failed jobs:**
```bash
php artisan tinker
>>> \App\Models\CrawlJob::where('status', 'failed')->where('failed_reason', 'like', '%normalization%')->get(['url', 'failed_reason'])
```

**3. Clear cached failed URLs (if you've fixed the source):**
```bash
php artisan cache:clear
```

**4. Manually test URL normalization:**
```bash
php artisan tinker
>>> $normalizer = app(\App\Services\UrlNormalizerService::class);
>>> $normalizer->normalize('https://example.com');  // Should return array
>>> $normalizer->normalize('');  // Should return null
>>> $normalizer->normalize('://bad');  // Should return null
```

**5. Common causes:**
- Empty or whitespace-only URLs in seed data
- Malformed URLs without proper scheme (use https://)
- Relative URLs without base context
- URLs with unsupported schemes (ftp://, file://, etc.)

---

## Crawling & Processing

### `php artisan crawl:daily`
Triggers the daily crawl for all configured categories.
- `--fresh`: Deletes pending/failed jobs and starts fresh seeds.
- Purpose: Seeds the database with URLs from `config/categories.php`.

### `php artisan queue:work`
Processes the background jobs.
- `--stop-when-empty`: Terminates once the queue is empty (ideal for shared hosting cron).
- `--max-jobs=100`: Limits execution to a specific number of jobs.
- `--tries=3`: Number of attempts per job.
Example: `php artisan queue:work database --stop-when-empty --tries=3`

---

## Indexing & Management

### `php artisan index:generate`
Generates the searchable JSON index file from `ParsedRecord` data.
- Purpose: Prepares data for fast frontend consumption and S3 upload.

### `php artisan upload:index`
Uploads the generated JSON index to the configured S3 compatible storage.

### `php artisan cache:refresh`
Triggers the frontend to bust its cache and fetch the latest uploaded index.

### `php artisan index:wipe`
Utility command to truncate all `ParsedRecord` and `CrawlJob` data.
- **CAUTION**: This deletes all discovered pages.

---

## Scheduling & Status

### `php artisan schedule:run`
The primary entry point for automation. 
- Purpose: Runs the per-minute queue worker and daily master refresh.

### `php artisan schedule:list`
Displays all scheduled commands and their next run times.

### `php artisan queue:status`
Displays current statistics about the queue (pending, processing, failed).
