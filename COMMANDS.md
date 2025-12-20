# Private Search Engine Terminal Commands

This document lists all available Artisan commands for managing the search engine cycle and data.

## Core Refresh Cycle

### `php artisan master:refresh`
Runs the entire search engine cycle (crawl, process, index, upload, cache).
- `--async`: (Recommended) Dispatches the job to the queue, avoiding timeouts.
- `--fresh`: Wipes the existing `ParsedRecord` and `CrawlJob` tables before starting. Use this to rebuild the index from scratch.
Example: `php artisan master:refresh --async --fresh`

---

## Advanced Crawler Commands ⭐ NEW

### `php artisan crawler:schedule`
Schedule URLs for crawling based on priority and freshness.
- `--reprioritize`: Recalculate all URL priorities before scheduling
- `--cleanup`: Clean up stale queue entries (locked for >1 hour)
- Purpose: Populates `crawl_queue` table with pending URLs
- Example: `php artisan crawler:schedule --reprioritize --cleanup`

### `php artisan crawler:monitor`
Display comprehensive crawl health monitoring dashboard.
- `--hours=24`: Number of hours of metrics to display (default: 24)
- Shows: Database stats, performance metrics, HTTP status distribution, URL status
- Example: `php artisan crawler:monitor --hours=48`

---

## Crawling & Processing

### `php artisan crawl:daily`
Triggers the daily crawl for all configured categories.
- `--fresh`: Deletes pending/failed jobs and starts fresh seeds.
- Purpose: Seeds the database with URLs from `config/categories.php`.
- **Note**: Now also populates the `urls` table for advanced crawler

### `php artisan queue:work`
Processes the background jobs.
- `--stop-when-empty`: Terminates once the queue is empty (ideal for shared hosting cron)
- `--max-jobs=100`: Limits execution to a specific number of jobs.
- `--tries=3`: Number of attempts per job.
- Example: `php artisan queue:work database --stop-when-empty --tries=3`

---

## Indexing & Management

### `php artisan index:generate`
Generates the searchable JSON index file from `ParsedRecord` data.
- Purpose: Prepares data for fast frontend consumption and Google Drive upload.
- **Note**: Legacy system, will be replaced by database-backed search

### `php artisan upload:index`
Uploads the generated JSON index to Google Drive.
- **Note**: Now optional - database-backed search doesn't require this

### `php artisan cache:refresh`
Downloads and merges all indexes from Google Drive into local cache.
- **Note**: Legacy system for backward compatibility

### `php artisan index:wipe`
Utility command to truncate all `ParsedRecord` and `CrawlJob` data.
- **CAUTION**: This deletes all discovered pages.

---

## Scheduling & Status

### `php artisan schedule:run`
The primary entry point for automation (Poor Man's Cron).
- Purpose: Runs the per-minute queue worker and daily master refresh.
- **Production**: Run this via web hit or cron every minute

### `php artisan schedule:list`
Displays all scheduled commands and their next run times.

### `php artisan queue:status`
Displays current statistics about the queue (pending, processing, failed).

---

## Production Deployment Workflow

### Daily Automated Workflow (via Poor Man's Cron)
```bash
# 1. Trigger via web hit (every minute)
curl https://your-domain.com/

# This automatically runs:
# - schedule:run (every minute)
# - queue:work --stop-when-empty (every minute)
# - master:refresh --async (daily at configured time)
```

### Manual Discovery Crawl (Run Once Daily)
```bash
# 1. Schedule discovered URLs
php artisan crawler:schedule --cleanup

# 2. Process queue (finds 50 new links per category)
php artisan queue:work --stop-when-empty --max-jobs=50

# 3. Monitor progress
php artisan crawler:monitor
```

### One-Time Setup Commands
```bash
# Run migrations (first deployment only)
php artisan migrate

# Verify scheduler
php artisan schedule:list

# Check crawler health
php artisan crawler:monitor
```

---

## Troubleshooting Commands

### Check Database
```bash
php artisan tinker
>>> \App\Models\Url::count()
>>> \App\Models\Document::count()
>>> \App\Models\Token::count()
```

### Clear Failed Jobs
```bash
php artisan queue:flush
```

### Reset Crawler State
```bash
php artisan migrate:fresh
php artisan crawl:daily --fresh
```

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
