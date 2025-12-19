# Deep-Dive Developer & Architecture Guide

This guide provides a exhaustive technical breakdown of every file, service, and logic flow within the Private Search Engine.

---

## 1. Project Philosophy
This engine is built for **Efficiency**, **Privacy**, and **Stability**. It uses a decoupled architecture where data collection (Crawl/Parse) is separated from data serving (Search API). It caches heavily to minimize I/O and uses advanced ranking algorithms to provide expert-level results without needing a massive infrastructure.

---

## 2. Core System Flows

### A. The Master Refresh Cycle (`master:refresh`)
The engine's lifecycle is automated via `MasterRefreshCommand.php` and `MasterRefreshJob.php`.
1. **Resume (Default)**: `CrawlDailyCommand` is now **idempotent**. It checks if seed jobs already exist and skips them if they do. This allows you to restart a failed refresh without losing progress.
2. **Reset (`--fresh`)**: Use the `--fresh` flag to force-delete existing jobs and reset daily counters to zero.
3. **Crawl**: `CrawlPageJob` downloads HTML. `ParsePageJob` extracts data and discovers new links up to the `CRAWLER_MAX_CRAWLS_PER_CATEGORY` limit.
3. **Index**: `GenerateIndexCommand` runs, invoking `IndexerService` to compile the parsed records into JSON indexes.
4. **Upload**: `UploadIndexCommand` sends the JSON files to the secure storage (Cloud or Mock).
5. **Sync**: `RefreshCacheCommand` downloads the latest files and updates the local API cache.

### B. Search Flow (`/api/v1/search`)
1. **Request**: Handled by `SearchController.php` with validation from `SearchRequest.php`.
2. **Expansion**: `AdvancedSearchService` stems the query and adds synonyms from `config/search.php`.
3. **Scoring**: `IndexManager` loads the in-memory index, and `AdvancedSearchService` applies the **BM25 algorithm** and **Levenshtein Fuzziness**.
4. **Ranking**: Results are ranked by `relevance_score` and `match_score` before being returned as JSON.

---

## 3. Directory & File Reference

### 📂 `app/Http/Controllers/Api/V1`
- **`SearchController.php`**: The primary API gateway. Handles search queries, pagination, date filtering, and performance logging.
- **`CategoryController.php`**: Exposes the list of valid categories defined in `config/categories.php`.
- **`StatsController.php`**: Provides a high-level overview of indexed volume, crawl success rates, and system Health.
- **`HealthController.php`**: A heartbeat endpoint for monitoring the API's status.

### 📂 `app/Services` (The Logic Layer)
- **`AdvancedSearchService.php`**: The brain of the search engine. Implements BM25, Fuzziness, Highlighting, and Confidence scoring.
- **`CrawlerService.php`**: High-performance HTTP client for web crawling with proxy and user-agent support.
- **`ParserService.php`**: Specialized HTML scraper that extracts semantic data while stripping noise (scripts, ads).
- **`IndexerService.php`**: Orchestrates the conversion of DB records into standardized, versioned JSON index files.
- **`GoogleDriveService.php`**: The production storage layer using OAuth2/Service Accounts.
- **`MockGoogleDriveService.php`**: Local fallback for testing storage without hitting external APIs.
- **`IndexManager.php`**: Manages the loading and lifecycle of in-memory TNTSearch indexes.
- **`RateLimiter.php`**: Ensures we never hit a domain too fast, preventing IP bans.
- **`RobotsTxtParser.php`**: Strict adherence to web standards for polite crawling.
- **`UrlNormalizer.php`**: Sanitizes URLs to prevent duplicate crawling of the same page.
- **`StorageService.php`**: Abstracted file management for both local and cloud environments.

### 📂 `app/Jobs` (Asynchronous Tasks)
- **`CrawlPageJob.php`**: The atomic unit of crawling. Handles retries and backoffs.
- **`ParsePageJob.php`**: Handles the recursive discovery of new links and duplicate detection.
- **`MasterRefreshJob.php`**: The background version of the full system refresh.

### 📂 `app/Console/Commands` (CLI Tools)
- **`MasterRefreshCommand.php`**: The main automation command. Supports real-time streaming output.
- **`CrawlDailyCommand.php`**: Triggers the initial seed crawl for all categories.
- **`CrawlCategoryCommand.php`**: Targeted crawl for a single category.
- **`GenerateIndexCommand.php`**: Rebuilds the JSON indexes from the database.
- **`UploadIndexCommand.php`**: Pushes local indexes to the cloud.
- **`RefreshCacheCommand.php`**: Forcefully refreshes the local API cache from storage.
- **`QueueStatusCommand.php`**: Real-time visual dashboard of the crawl queue status.
- **`AuthorizeGoogleDriveCommand.php`**: Interactive CLI for setting up OAuth credentials.
- **`ClearQueueCommand.php`**: Cleanup tool for resetting the system queue.

### 📂 `app/Models` (Database Schema)
- **`CrawlJob.php`**: Tracks every URL's crawl status, HTTP code, and failure reason.
- **`ParsedRecord.php`**: Stores the extracted clean data. Uses `content_hash` for global duplicate prevention.
- **`IndexMetadata.php`**: Tracks index versioning and timestamps for incremental updates.
- **`User.php`**: Standard Laravel user model with **Sanctum** `HasApiTokens` for secure API access.

### 📂 `app/Http/Middleware`
- **`ApiMasterKeyMiddleware.php`**: Implements the "Master Key" security layer, allowing service-to-service communication without Sanctum tokens.

### 📂 `database` (Migrations & Seeders)
- **Migrations**: Covers Users, Jobs, Cache, Crawl Logs, and Parsed Records.
- **`CreateUserSeeder.php`**: Production-safe admin setup. **Crucially bypasses Faker** to avoid seeder errors.
- **`DatabaseSeeder.php`**: Entry point that calls the production-safe seeder.
- **`TestRecordSeeder.php`**: Generates mock data for local UI/Search testing.

### 📂 `config` (Deep Settings)
- **`search.php`**: BM25 constants, Fuzziness thresholds, and Category Synonyms.
- **`crawler.php`**: User agents, timeouts, and recursion depth limits.
- **`indexer.php`**: Checksum algorithms, JSON formatting, and schema versions.
- **`categories.php`**: The definitive list of Seed URLs and valid category IDs.
- **`sanctum.php`**: Configuration for token-based authentication.

### 📂 `resources/views`
- **`search.blade.php`**: The premium frontend UI. Includes the Glassmorphism design and the `advanced-search.js` logic for Bearer token auth.

### 📂 `routes`
- **`api.php`**: All Search, Health, and Login endpoints (versioned under `/v1`).
- **`web.php`**: The main entry point for the Search UI.
- **`console.php`**: Home of the Laravel Scheduler (`Schedule::job(new MasterRefreshJob)->daily()`).

---

## 4. Maintenance & Safety

1. **Wait for Success**: `MasterRefreshJob` is designed to **stop on failure**. If the crawler fails, it won't push a broken index.
2. **Log Monitoring**: Always check `tail -f storage/logs/laravel.log` during a master refresh.
3. **SQLite vs MySQL**: The system is fully compatible with SQLite for simple deployment but can scale to MySQL/Postgres by changing `DB_CONNECTION`.
4. **Disk Space**: Large crawls (800 per category) will generate significant temp files in `storage/app/crawl`. These are automatically deleted after parsing.

---

## 5. Deployment Checklist
- [ ] Run `composer install --no-dev`
- [ ] `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=CreateUserSeeder`
- [ ] Set up Cron as per `cron.md`
