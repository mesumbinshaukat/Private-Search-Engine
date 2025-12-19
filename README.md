# Private Search Engine

## System Purpose

This is a private, category specific search engine designed for personal use. It crawls, indexes, and serves search results for exactly five categories: Technology, Business, AI, Sports, and Politics.

The system operates on a daily refresh cycle, ensuring all indexed data is fresh (maximum 5 days old) and relevant. It is built for local development and testing, with Google Drive serving as the canonical data store.

## Architecture Overview

The system follows a pipeline architecture with distinct phases:

```
Crawl → Parse → Index → Cleanup → Upload → Cache → Serve
```

### Components

**Laravel Orchestration Layer**  
Laravel serves as the orchestration framework, providing job queuing, scheduling, and API routing. Business logic resides in dedicated service classes.

- **Advanced Search Core**: Powered by TNTSearch (BM25 ranking), PHP-Stemmer, and fuzzy matching (Levenshtein).
- **Intelligent Queries**: Supports logical operators (`AND`, `OR`, `NOT`), exact phrase matching (`""`), and automatic synonym expansion (e.g., AI → ML).
- **Rich Results**: Result highlighting, confidence scores (0-1), match scores (1-10), and query suggestions for empty results.
- **Advanced Filtering**: Filter by category, date range (`from_date`, `to_date`), and custom sorting (`relevance`, `date_desc`).

**Crawler Service**  
Implements polite, ethical web crawling with robots.txt compliance, per domain rate limiting, and comprehensive HTTP validation. Handles 429, 5xx, redirects, and timeouts gracefully.

**Parser Service**  
Extracts structured data from raw HTML including title, canonical URL, meta description, and publish date. Implements global duplicate detection via canonical URLs and content hashes across all categories.

**Indexer Service**  
Manages incremental indexing by merging new records with existing JSON data from Google Drive. Enforces 5-day data retention, deduplicates records, and generates timestamped JSON output.

**Storage Service**  
Manages JSON file lifecycle including generation, validation, upload to Google Drive, and integrity verification via checksums.

**Search API**  
Versioned REST API secured by Laravel Sanctum and Master API Key. Serves search results from local cache (synced and merged from Google Drive).

**Cache Manager**  
Maintains local cache by downloading and merging ALL relevant index files from Google Drive for each category.

## Data Lifecycle

### Master Refresh Cycle (Recommended)

The entire system lifecycle is orchestrated via a single master command that runs sequentially:
`Crawl → Process → Index → Upload → Cache Refresh`

This can be triggered manually via `php artisan master:refresh` or automatically via the daily scheduler.

### Detailed Daily Phases

1. **Crawl Phase** (00:00 - 02:00)
   - Queue crawl jobs for seed URLs across all categories
   - Respect robots.txt and enforce rate limiting
   - Validate HTTP responses and content types
   - Store raw HTML temporarily

2. **Parse Phase** (02:00 - 03:00)
   - Extract structured data from raw HTML
   - Normalize URLs and detect duplicates
   - **Global Duplicate Check**: Skips any URL or content hash already existing in any category.

3. **Index Phase** (03:00 - 04:00)
   - Fetch existing records from Google Drive
   - Merge with new local records and deduplicate
   - Enforce 5-day age limit (records > 5 days are purged)
   - Generate timestamped JSON files if an index for today already exists

4. **Cleanup Phase** (04:00 - 04:30)
   - Remove temporary crawl data
   - Purge old index files

5. **Upload Phase** (04:30 - 05:00)
   - Upload validated JSON to Google Drive
   - Verify upload integrity via checksums
   - Skip upload if no new unique records were found

6. **Cache Refresh Phase** (05:00 - 05:30)
   - Download and merge all valid JSON files from Drive per category
   - Update local cache atomically

7. **Serve Phase** (Always Active)
   - API endpoints serve search results from local cache (Sanctum or Master Key required)
   - Handle stale or missing data gracefully

### Data Retention

- Maximum data age: 5 days
- Minimum records per category: 5 (configurable)
- Index files older than 5 days are automatically purged
- Google Drive is the source of truth

## Category Rules

The system supports exactly five categories. This list is immutable:

1. **Technology** - Software, hardware, programming, tech industry news
2. **Business** - Finance, markets, entrepreneurship, corporate news
3. **AI** - Artificial intelligence, machine learning, AI research and applications
4. **Sports** - All sports news, events, and analysis
5. **Politics** - Political news, policy, elections, government

Each category must maintain a minimum record count (default: 5). If this threshold cannot be met, the system logs failure and does not upload incomplete data.

## Security Posture

### Authentication
The system is secured using two primary methods:
1. **Laravel Sanctum**: Used for the Search UI. Users must log in via a secure modal. Tokens are managed via session-based cookies or local storage.
2. **Master API Key**: Used for cross-service authentication. Accessible via `X-API-MASTER-KEY` header or `api_master_key` query parameter.

### Rate Limiting
- Crawling: Maximum 1 request per second per domain
- API: 60 requests per minute per IP address

### Data Privacy
- No user tracking or analytics
- No external service dependencies except Google Drive
- All crawled data is publicly available web content

### Secrets Management
- Google Drive credentials stored in `.env` file (not committed to version control)
- No production secrets used in local development

## Known Limitations

### Technical Limitations

**JavaScript Heavy Sites**  
The crawler does not execute JavaScript. Sites that render content dynamically via JavaScript will not be indexed correctly. This is a known tradeoff for performance and simplicity.

**Crawl Coverage**  
With rate limiting and polite crawling, achieving 1000+ records per category per day requires a substantial seed URL list. Initial setup may require manual curation of seed URLs.

**Google Drive Dependency**  
The system is entirely dependent on Google Drive for persistent storage. Google Drive outages will prevent uploads but will not affect search serving from local cache.

**No Real Time Updates**  
The system operates on a daily refresh cycle. Content published between refresh cycles will not be available until the next cycle completes.

### Operational Limitations

**Local Development Only**  
The system is designed for local development and testing. Production deployment would require additional hardening, monitoring, and infrastructure.

**Manual Seed URL Management**  
Seed URLs must be manually curated and maintained. There is no automatic discovery of new sources.

**No Failure Notifications**  
The system logs failures but does not send notifications. Monitoring must be manual or via log aggregation.

## When Not to Use This System

Do not use this system if you need:

- Real time or near real time search results
- JavaScript rendered content indexing
- Automatic source discovery
- Production grade reliability and monitoring
- Multi user access with authentication
- Categories beyond the five defined categories
- Data retention beyond 5 days
- Guaranteed minimum record counts (system may fail to meet 1000 record threshold)

## Authentication

This system supports two authentication methods for Google Drive:

### Method A: Service Account (Recommended for Production)
1. Create a Service Account in Google Cloud Console.
2. Download the JSON key and save it to `storage/app/credentials/service-account.json`.
3. Share your target Google Drive folder with the Service Account email (Editor access).
4. Configure `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON` in `.env`.

### Method B: OAuth 2.0 (Personal Account)
1. Create "Desktop" OAuth Client in Google Cloud Console.
2. Save credentials to `storage/app/credentials/client_secret.json`.
3. Run `php artisan google-drive:authorize` to log in via browser.
4. Configure `GOOGLE_DRIVE_CLIENT_SECRET_JSON` and `GOOGLE_DRIVE_TOKEN_JSON` in `.env`.

## Quick Start Commands

### 1. Initial Setup
Run these commands to set up the environment and database:

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate --seed --class=CreateUserSeeder
```

### 2. The Master Command (Full Cycle)
Run the entire lifecycle sequentially:
```bash
php artisan master:refresh
```

### 3. Individual Lifecycle Commands
If you prefer to run phases manually:

1. **Authorize** (First time only):
   ```bash
   php artisan google-drive:authorize
   ```

2. **Crawl**: Start the daily crawl for all categories, or a specific one.
   ```bash
   # All categories
   php artisan crawl:daily
   
   # Specific category
   php artisan crawl:category technology
   ```

3. **Queue Worker**: Start the queue worker to process crawl jobs (run in a separate terminal).
   ```bash
   php artisan queue:work --stop-when-empty
   
   # To clear all pending jobs from the queue:
   php artisan queue:clear
   
   # To see queue status and statistics:
   php artisan queue:status
   ```

4. **Index**: Generate JSON search indexes once crawling is complete.
   ```bash
   php artisan index:generate
   ```

5. **Upload**: Sync your local search indexes to Google Drive.
   ```bash
   php artisan upload:index
   ```

6. **Initial Cache Sync**

If you have already generated indexes and uploaded them to Google Drive (e.g., from a local environment or a previous run), you **must** synchronize the production cache:

```bash
php artisan cache:refresh
```

7. **Discover**: Get a random topic for discovery.
   ```bash
   curl "http://localhost:8000/api/v1/topic"
   ```

8. **Search**: Start the server and visit `http://localhost:8000`.
   ```bash
   php artisan serve
   ```



## Documentation Reference

- **[DEPLOYMENT.md](DEPLOYMENT.md)**: Detailed setup for OAuth and environment.
- **[API.md](API.md)**: REST API documentation.
- **[RULES.md](RULES.md)**: Core system constraints and category definitions.

## License

This is a private project for personal use. No license is granted for redistribution or commercial use.
