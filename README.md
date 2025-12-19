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

**Crawler Service**  
Implements polite, ethical web crawling with robots.txt compliance, per domain rate limiting, and comprehensive HTTP validation. Handles 429, 5xx, redirects, and timeouts gracefully.

**Parser Service**  
Extracts structured data from raw HTML including title, canonical URL, meta description, and publish date. Implements URL normalization and duplicate detection via canonical URLs and content hashes.

**Indexer Service**  
Groups parsed records by category, enforces minimum record count (1000 per category), removes data older than 5 days, and generates deterministic JSON output with metadata headers.

**Storage Service**  
Manages JSON file lifecycle including generation, validation, upload to Google Drive, and integrity verification via checksums.

**Search API**  
Versioned REST API that serves search results from local cache (synced from Google Drive) with support for pagination, category filtering, and graceful handling of stale or missing data.

**Cache Manager**  
Maintains local cache of index files downloaded from Google Drive for fast API serving. Implements atomic updates and cache invalidation.

## Data Lifecycle

### Daily Refresh Cycle

1. **Crawl Phase** (00:00 - 02:00)
   - Queue crawl jobs for seed URLs across all categories
   - Respect robots.txt and enforce rate limiting
   - Validate HTTP responses and content types
   - Store raw HTML temporarily

2. **Parse Phase** (02:00 - 03:00)
   - Extract structured data from raw HTML
   - Normalize URLs and detect duplicates
   - Filter invalid records

3. **Index Phase** (03:00 - 04:00)
   - Group records by category
   - Enforce minimum 1000 records per category
   - Remove data older than 5 days
   - Generate deterministic JSON files

4. **Cleanup Phase** (04:00 - 04:30)
   - Remove temporary crawl data
   - Purge old index files

5. **Upload Phase** (04:30 - 05:00)
   - Upload validated JSON to Google Drive
   - Verify upload integrity via checksums

6. **Cache Refresh Phase** (05:00 - 05:30)
   - Download latest index files from Google Drive
   - Update local cache atomically

7. **Serve Phase** (Always Active)
   - API endpoints serve search results from local cache
   - Handle stale or missing data gracefully

### Data Retention

- Maximum data age: 5 days
- Minimum records per category: 1000
- Index files older than 5 days are automatically purged
- Google Drive is the source of truth

## Category Rules

The system supports exactly five categories. This list is immutable:

1. **Technology** - Software, hardware, programming, tech industry news
2. **Business** - Finance, markets, entrepreneurship, corporate news
3. **AI** - Artificial intelligence, machine learning, AI research and applications
4. **Sports** - All sports news, events, and analysis
5. **Politics** - Political news, policy, elections, government

Each category must maintain a minimum of 1000 valid, live records per day. If this threshold cannot be met, the system logs failure and does not upload incomplete data.

## Security Posture

### Authentication
API endpoints are currently unauthenticated as this is a private, local development system. Production deployment would require API key authentication.

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

**Limited Search Features**  
Search is basic keyword matching within the indexed JSON. No advanced features like fuzzy matching, stemming, or relevance ranking are implemented.

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
- Advanced search features (fuzzy matching, relevance ranking, faceted search)
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

Run these commands in order to execute the full data lifecycle:

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

6. **Search**: Start the server and visit `http://localhost:8000`.
   ```bash
   php artisan serve
   ```

## Documentation Reference

- **[DEPLOYMENT.md](DEPLOYMENT.md)**: Detailed setup for OAuth and environment.
- **[API.md](API.md)**: REST API documentation.
- **[RULES.md](RULES.md)**: Core system constraints and category definitions.

## License

This is a private project for personal use. No license is granted for redistribution or commercial use.
