## [2025-12-20 09:22:00] - URL Normalization Robustness Fix

### Fixed
- **NOT NULL Constraint Violation**: Fixed critical bug where `url_hash` column in `urls` table received NULL values, causing database constraint violations and job failures
- **Parse URL Failures**: Enhanced `UrlNormalizerService::normalize()` to explicitly handle `parse_url()` returning `false` for malformed URLs
- **Missing Validation**: Added defensive validation guards in `CrawlPageJob::handle()` to check normalization results before database insertion
- **Database Error Handling**: Wrapped `Url::firstOrCreate()` in try-catch blocks to gracefully handle database constraint violations

### Changed
- **Auto-Scheme Addition**: URLs without scheme (e.g., "example.com") now automatically get `https://` prepended if they match domain pattern
- **Enhanced Logging**: Added detailed logging for different normalization failure scenarios (empty URL, malformed URL, missing scheme/host, unsupported scheme, empty host)
- **Graceful Degradation**: Failed normalizations now mark jobs as failed with descriptive reasons instead of crashing the queue worker
- **Failed URL Caching**: URLs that fail normalization are cached for 24 hours to prevent infinite retry loops
- **IDN Handling**: Improved Unicode/Internationalized Domain Name conversion with explicit fallback handling

### Added
- **Comprehensive Test Suite**: Created `UrlNormalizerServiceTest.php` with 30+ test cases covering:
  - Empty and whitespace-only URLs
  - Malformed URLs (no host, scheme-only, invalid syntax)
  - Scheme auto-addition for domains
  - Fragment removal (client-side only)
  - Tracking parameter removal (utm_*, fbclid, gclid, etc.)
  - Default port removal (80 for HTTP, 443 for HTTPS)
  - Query parameter sorting for consistent hashing
  - Path normalization with relative segment resolution
  - Hash consistency verification
  - Real-world URL handling (entrepreneur.com, etc.)
- **Troubleshooting Guide**: Added URL normalization error troubleshooting section to `COMMANDS.md`

### Technical Details
- **Branch**: `advanced-crawler-engine`
- **Files Modified**: 
  - `app/Services/UrlNormalizerService.php` (enhanced validation and error handling)
  - `app/Jobs/CrawlPageJob.php` (added validation guards and try-catch blocks)
- **Files Added**:
  - `tests/Unit/UrlNormalizerServiceTest.php` (comprehensive test coverage)
- **Impact**: Prevents crawler failures on edge-case URLs, improves system stability

## [2025-12-20 06:27:18] - Advanced Crawler Engine Foundation (In Progress)

### Added
- **Database Schema**: Implemented 8 new tables for advanced crawling and indexing:
  - `urls` table: Normalized URL storage with depth, priority, scheduling, and status tracking. Supports global deduplication via SHA256 hash.
  - `hosts` table: Robots.txt cache with crawl delays and allow/disallow rules stored as JSON. 24-hour cache expiry.
  - `crawl_queue` table: Worker coordination with locking mechanism for concurrent fetching.
  - `documents` table: Full-text content storage with metadata, language detection, and word count.
  - `links` table: Link graph structure with nofollow tracking and anchor text.
  - `tokens` table: Inverted index vocabulary with document frequency tracking.
  - `postings` table: Term frequencies and positions for TF-IDF/BM25 scoring.
  - `metrics` table: Crawl health monitoring and time-series data.

- **Eloquent Models**: Created 8 models with comprehensive relationships and utility methods:
  - `Url` model: Relationships to documents, links, crawl_queue. Scopes for pending/crawled/failed URLs.
  - `Host` model: Cache expiry checking, crawl delay retrieval methods.
  - `CrawlQueue` model: Lock/unlock methods for worker coordination.
  - `Document` model: Relationships to URLs and postings, document length calculation.
  - `Link` model: Bidirectional URL relationships, follow/nofollow scopes.
  - `Token` model: IDF calculation method for search scoring.
  - `Posting` model: TF-IDF and BM25 scoring methods with configurable parameters.
  - `Metric` model: Time-series query scopes for monitoring.

- **URL Normalization Service**: Comprehensive URL normalization with:
  - Lowercase scheme and host normalization
  - Default port removal (80 for HTTP, 443 for HTTPS)
  - Path normalization with relative segment resolution (., ..)
  - Query parameter alphabetical sorting
  - Tracking parameter removal (utm_*, fbclid, gclid, msclkid, etc.)
  - Unicode/IDN punycode conversion
  - SHA256 hash generation for global uniqueness checking
  - Relative URL to absolute URL resolution
  - URL equivalence checking

- **Robots.txt Service**: Pure PHP robots.txt parser and compliance checker:
  - Automatic robots.txt fetching per host
  - User-agent matching logic (supports wildcards)
  - Allow/disallow rule parsing with pattern matching (* and $ wildcards)
  - Crawl-delay directive extraction and caching
  - 24-hour cache in `hosts` table
  - Graceful handling of missing or invalid robots.txt files

- **Enhanced Crawler Configuration**: Updated `config/crawler.php` with:
  - Fetch engine settings (`fetch_workers`, `fetch_batch_size`)
  - Separate connect and request timeouts
  - Maximum crawl depth configuration
  - User agent rotation array for anti-blocking
  - Increased default max crawls per category to 1000

### Changed
- **ParserService**: Updated to use new `UrlNormalizerService` with array return format. Now returns `url_hash` for deduplication. Removed duplicate `makeAbsolute()` method.
- **Crawler Configuration**: Reorganized into logical sections with comments. Increased default limits for production-scale crawling.

### Removed
- **Old UrlNormalizer**: Replaced basic `UrlNormalizer.php` with comprehensive `UrlNormalizerService.php`.

### Technical Details
- **Branch**: `advanced-crawler-engine` (3 commits, 1,270 lines added)
- **Migration Status**: All 8 migrations tested and ran successfully in 71ms
- **Database Engine**: SQLite with WAL mode (already enabled)
- **Backward Compatibility**: Preserved. Google Drive remains as optional backup.

### In Progress
- Advanced fetch engine with curl_multi for concurrent fetching (100+ URLs) - TO BE IMPLEMENTED
- Enhanced parsing with OG/Schema.org extraction - TO BE IMPLEMENTED
- Full integration testing and production deployment - TO BE IMPLEMENTED

### Completed (2025-12-20 06:50:00) ✅ FULLY TESTED
- ✅ Database schema with 8 tables (urls, hosts, crawl_queue, documents, links, tokens, postings, metrics)
- ✅ 8 Eloquent models with comprehensive relationships
- ✅ URL normalization service with SHA256 deduplication - TESTED
- ✅ Robots.txt service with 24-hour caching - TESTED
- ✅ Inverted index engine with tokenization and stemming - TESTED
- ✅ Metrics service for crawl health tracking - TESTED
- ✅ Crawl scheduler with priority-based queuing - TESTED
- ✅ Enhanced search service with BM25 scoring - TESTED
- ✅ Monitoring command for health dashboard - TESTED
- ✅ CrawlerService integration with robots.txt compliance
- ✅ ParsePageJob integration with urls table and IndexEngine - TESTED
- ✅ End-to-end testing completed successfully

### Testing Results (2025-12-20 06:48:00)
All core services tested and verified:
- URL Normalization: ✅ Tracking params removed, SHA256 hash generated
- Document Indexing: ✅ Tokens and postings created successfully
- Search Functionality: ✅ BM25 scoring working
- Scheduler: ✅ Priority calculation and next crawl scheduling working
- Metrics: ✅ Recording and retrieval working
- Monitor Dashboard: ✅ Displaying all statistics correctly

**Total Commits**: 9
**Total Lines Added**: 2,476
**Total Lines Deleted**: 146
**Net Addition**: 2,330 lines

### Recently Added (2025-12-20 06:35:00)
- **CrawlSchedulerService**: Intelligent URL scheduling with:
  - Priority calculation based on depth, inbound links, and freshness
  - Next crawl time calculation with adaptive intervals
  - Bulk reprioritization of all URLs
  - Stale queue cleanup (removes locks older than 1 hour)

- **ScheduleCrawlCommand**: Artisan command for running scheduler:
  - `php artisan crawler:schedule` - Schedule URLs for crawling
  - `--reprioritize` flag to recalculate all URL priorities
  - `--cleanup` flag to remove stale queue entries

- **EnhancedSearchService**: Database-backed search with advanced scoring:
  - BM25 algorithm for relevance scoring
  - Freshness boost (20% for content indexed < 7 days)
  - Link popularity boost (up to 50% based on inbound links)
  - Search suggestions based on token frequency
  - Category filtering support

- **MonitorCommand**: Comprehensive health dashboard:
  - Database statistics (URLs, documents, tokens, queue size)
  - Performance metrics (fetch rate, parse success rate, queue backlog)
  - HTTP status distribution
  - URL status distribution with percentages

- **CrawlerService Integration**: Updated to use RobotsTxtService:
  - Automatic robots.txt compliance checking
  - Respects crawl-delay directive from robots.txt
  - Graceful handling of missing robots.txt files

### Documentation Updates
- **README.md**: Updated architecture overview with advanced crawler components
- **README.md**: Added new commands section with crawler:schedule and crawler:monitor
- **changelog.md**: Comprehensive tracking of all changes with timestamps

## [2025-12-20 05:40:34] - AI Agent Meta-Governance

### Added
- **AI Rule File**: Created `AGENT_RULE.txt` to provide future AI agents with a comprehensive understanding of the project's architecture, constraints (shared hosting), and development flow.

## [2025-12-20 05:15:34] - Concurrency Safety & Command Documentation

### Added
- **Refresh Cycle Lock**: Implemented `master_refresh_lock` (Cache-based) in `MasterRefreshJob` to prevent multiple refresh cycles from running concurrently.
- **Improved UI Notifications**: Added a "Beautiful Toast" notification system to the search dashboard for non-intrusive feedback.
- **"Busy" State Handling**: The `trigger-refresh` API now returns a `409 Conflict` status if a refresh is already in progress, and the UI displays a warning toast.
- **Command Documentation**: Created `COMMANDS.md` listing all Artisan commands available for managing the search engine.

## [2025-12-20 04:58:34] - Automation Fallbacks & Manual Triggers

### Added
- **Manual Trigger API**: Created `POST /api/v1/trigger-refresh` (accessible via Master Key or Sanctum) to allow manual initiation of the `MasterRefreshJob`.
- **UI Refresh Button**: Added a "↻ Refresh Crawler" button to the search dashboard for authorized users.
- **"Poor Man's Cron"**: Implemented opportunistic scheduler execution in `AppServiceProvider.php`. The system will now attempt to run scheduled tasks on web hits (throttled to once per minute) if no system cron is available.
- **Dual-Auth API Support**: Restored `master_key` middleware to all search endpoints, allowing authentication via either a Sanctum token (Bearer) or the `API_MASTER_KEY` (X-API-MASTER-KEY header or query param).

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
