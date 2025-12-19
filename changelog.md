## [2025-12-20 04:45:34] - Enabled Cross-Domain Discovery & Record Growth

### Added
- **Cross-Domain Discovery**: Relaxed the strict same-domain restriction in `ParsePageJob`. The crawler can now follow high-quality external links.
- **Smart Discovery Filtering**: Integrated an optional `CRAWLER_ALLOWED_EXTERNAL_DOMAINS` allow-list. If empty, it falls back to a keyword-based filter (tech, ai, sports, etc.) to keep discovery relevant.
- **Record Freshness (7-Day Rule)**: Updated duplicate logic to automatically delete and recreate `ParsedRecord` entries if they are older than 7 days, ensuring search results stay current with new content.

### Changed
- **Scheduler Batching**: Updated the per-minute queue worker in `routes/console.php` to explicitly use the `--max-jobs` limit from environment variables.
- **Crawl Transparency**: `CrawlDailyCommand` now explicitly prints the current `CRAWLER_MAX_CRAWLS_PER_CATEGORY` limit at the start of each run.

## [2025-12-20 04:15:34] - Shared Hosting Optimizations (Hostinger/hPanel)

### Added
- **Scheduled Queue Worker**: Added a per-minute scheduled task in `routes/console.php` to process the queue in safe batches (`--stop-when-empty --max-jobs=100`). This ensures reliability on shared hosting by staying within LVE limits.
- **Queue Fault Tolerance**: Implemented a global `Queue::failing` listener in `AppServiceProvider.php` to log job failures and identify queue stalls.
- **Configurable Batching**: Introduced `QUEUE_BATCH_MAX_JOBS` environment variable to control the number of jobs processed per worker run.

### Changed
- **Master Refresh Robustness**: Refactored `master:refresh` to use chunked queue processing and improved logging per step. Enhanced `--fresh` flag to reliably wipe ParsedRecords and CrawlJobs.
- **SQLite Concurrency**: Explicitly enabled SQLite **Write-Ahead Logging (WAL)** and **Normal Synchronous** mode in `AppServiceProvider.php` to maximize concurrent database access.
- **Resumable Crawl Logic**: Updated `CrawlDailyCommand` to gracefully handle existing seeds and report total pending jobs, making the crawl cycle truly resumable.
- **Duplicate Prevention**: Strengthened `ParsePageJob` with global canonical URL checks and fallback raw URL matching to prevent duplicate records across categories.

### Fixed
- **LVE Exit Code 12**: Resolved issues where long-running synchronous processes were killed by hosting provider limits by transitioning to chunked, scheduled background processing.
- **Parsing Stalls**: Improved logging and error handling in `ParsePageJob` to ensure parsing failures are logged without blocking the worker.

### Fixed
- **Seeding Error**: Resolved `Call to undefined function Database\Factories\fake()` in production environments by removing the `fakerphp/faker` dependency from `DatabaseSeeder.php` and redirecting to the production-safe `CreateUserSeeder`.
- **Authentication**: Fixed "no such table: personal_access_tokens" error by publishing Sanctum migrations and ensuring they are run during setup.
- **Crawl Depth**: Fixed an issue where the crawler would stop after 25 jobs regardless of limits. The crawler now correctly reaches the `CRAWLER_MAX_CRAWLS_PER_CATEGORY` (e.g., 800) by allowing link discovery on previously known pages.
- **Master Refresh Logic**: Changed `master:refresh` to be **non-blocking** and **resumable**. By default, it will now pick up where it left off (idempotent). You can use the new `--fresh` flag to force a full reset and start from zero.
- **SQLite Optimization**: Enabled **WAL (Write-Ahead Logging)** and increased the **Busy Timeout** to 10 seconds. This resolves "database is locked" errors and allows 10+ concurrent workers to crawl stably.
- **Master Refresh Feedback**: Updated `php artisan master:refresh` to stream real-time output from all internal steps (including `queue:work`), allowing you to monitor high-volume crawls (2000+ jobs) directly from the terminal.

### Changed
- **Data Lifecycle**: Transformed the "Incremental Growth" model into a "True Refresh Cycle". `master:refresh` now resets daily counters, clears job logs, and updates existing records instead of skipping them.
- **Configurability**: Externalized all hardcoded system limits into environment variables. You can now tune Crawler depth, Indexer thresholds, Search scoring (BM25/Fuzziness), and System timeouts directly via `.env`.
- **Master Refresh Job**: Refined `MasterRefreshJob` to stop execution upon step failure, protecting the integrity of the search index and cache.

### Added
- **Sanctum Integration**: Published Sanctum configuration and migrations to enable full token-based API authentication.
- **Automation Guide**: Created `cron.md` to explain how to set up and monitor the Search Engine's scheduled tasks and queue workers.
- **Crawl Job Cleanup**: Improved cleanup logic in `CrawlDailyCommand` to only reset data for the categories being re-crawled, making manual one-by-one crawls safe.
- **Documentation**: Updated `README.md`, `DEPLOYMENT.md`, and `API.md` to reflect the new authentication setup, refresh commands, and environment variables.
- **Changelog**: Introduced this `changelog.md` to track project evolution.
