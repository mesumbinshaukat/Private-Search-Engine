# DEPLOYMENT.md

## Local Environment Setup

This document provides comprehensive instructions for setting up, configuring, and operating the private search engine in a local development environment.

## Prerequisites

### Required Software

- **PHP** >= 8.2 with extensions: mbstring, xml, curl, json, openssl, pdo, tokenizer
- **Composer** >= 2.0
- **SQLite** (for local database)
- **Node.js** >= 18.x (for frontend assets if needed)
- **Git** for version control

### Recommended Software

- **Redis** for queue backend (optional, can use database queue)
- **Supervisor** for queue worker management (optional for development)

## Initial Setup

### 1. Clone or Initialize Project

If starting fresh, the Laravel project is already initialized in the current directory.

### 2 Install Dependencies

#### 2.1 Install Dependencies - LOCAL
```bash
composer install
```

#### 2.3 Install Dependencies - PRODUCTION

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative --ignore-platform-reqs
```

### 3. Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 4. Configure Environment Variables

Edit `.env` file with the following required variables:

```env
APP_NAME="Private Search Engine"
APP_ENV=local
APP_KEY=base64:GENERATED_KEY_HERE
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite

QUEUE_CONNECTION=database

GOOGLE_DRIVE_MOCK=false
GOOGLE_DRIVE_CLIENT_SECRET_JSON=storage/app/credentials/client_secret.json
GOOGLE_DRIVE_TOKEN_JSON=storage/app/credentials/token.json
GOOGLE_DRIVE_FOLDER_ID=your_folder_id_here

CRAWLER_MAX_CONCURRENT_JOBS=10
CRAWLER_REQUEST_TIMEOUT=15
CRAWLER_MAX_PAGE_SIZE=5242880
CRAWLER_RATE_LIMIT_PER_DOMAIN=1
CRAWLER_MAX_CRAWLS_PER_CATEGORY=50
CRAWLER_USER_AGENT="PrivateSearchBot/1.0 (Personal Use; +http://localhost:8000/bot)"

INDEXER_MIN_RECORDS_PER_CATEGORY=5
INDEXER_MAX_DATA_AGE_DAYS=5

API_RATE_LIMIT_PER_MINUTE=60

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password
API_MASTER_KEY=your_generate_master_key

CACHE_DRIVER=file
SESSION_DRIVER=file
```

### 5. Create Database

For SQLite:

```bash
touch database/database.sqlite
```

Run migrations:

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 6. Create Required Directories

```bash
mkdir -p storage/app/crawl
mkdir -p storage/app/index
mkdir -p storage/app/cache
mkdir -p storage/logs
```

Set permissions:

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### 7. Initial Cache Sync

If you have already generated indexes and uploaded them to Google Drive (e.g., from a local environment or a previous run), you **must** synchronize the production cache:

```bash
php artisan cache:refresh
```

This command downloads the latest indices from Google Drive into `storage/app/private/cache`, which is required for the search API to return results.

## Google Drive Authentication Strategy

### Development Environment (Mocked)

For local development and testing without actual Google Drive access, the system uses a mock Google Drive service that simulates upload and download operations using local filesystem.

**Mock Configuration:**

Set in `.env`:

```env
GOOGLE_DRIVE_MOCK=true
GOOGLE_DRIVE_MOCK_PATH=storage/app/google_drive_mock
```

The mock service will:
- Store files locally in the mock path
- Simulate upload delays
- Generate mock file IDs
- Validate checksums locally

### 1. Google Drive API Setup

You can choose between **Service Account** (Automated) or **OAuth 2.0** (Personal).

#### Option A: Service Account
1. [Cloud Console](https://console.cloud.google.com/) > Enable Drive API.
2. Create Service Account > Download JSON key.
3. Place in `storage/app/credentials/service-account.json`.
4. Share Drive folder with Service Account email (Editor).

#### Option B: OAuth 2.0
1. [Cloud Console](https://console.cloud.google.com/) > Enable Drive API.
2. Create OAuth 2.0 Desktop Credentials > Download JSON.
3. Place in `storage/app/credentials/client_secret.json`.
4. Run `php artisan google-drive:authorize`.

### 2. Environment Configuration

```env
# For Service Account
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=storage/app/credentials/service-account.json

# OR For OAuth 2.0
GOOGLE_DRIVE_CLIENT_SECRET_JSON=storage/app/credentials/client_secret.json
GOOGLE_DRIVE_TOKEN_JSON=storage/app/credentials/token.json

GOOGLE_DRIVE_MOCK=false
GOOGLE_DRIVE_FOLDER_ID=your_id
```

### 3. Permissions

Service Accounts work seamlessly in production without manual browser intervention. Ensure the folder ID in `.env` is shared with the Service Account email.

**Security Notes:**
- Never commit the `service-account.json` file to version control.
- Add `storage/app/credentials/` to `.gitignore`.

## Queue and Scheduler Configuration

### Queue Worker

The system uses Laravel queues for asynchronous job processing (crawling, parsing, indexing).

**Start Queue Worker (Development):**

```bash
php artisan queue:work --tries=3 --timeout=300
```

**Start Queue Worker (Production with Supervisor):**

Create supervisor configuration file `/etc/supervisor/conf.d/private-search-queue.conf`:

```ini
[program:private-search-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --timeout=300
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-worker.log
```

Reload supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start private-search-queue:*
```

### Scheduler

The system uses Laravel scheduler for daily refresh cycle.

**Add to Crontab:**

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

*Note: The scheduler is configured to run `MasterRefreshJob` daily.*

**Verify Scheduler (Development):**

```bash
php artisan schedule:list
```

**Run Scheduler Manually (Testing):**

```bash
php artisan schedule:run
```

## Running the Application

### Development Server

Start the Laravel development server:

```bash
php artisan serve
```

Application will be available at `http://localhost:8000`

### Queue Worker

In a separate terminal, start the queue worker:

```bash
php artisan queue:work
```

### Refresh Cycle Simulation

For testing the daily refresh cycle without waiting for cron:

**Recommended: Master Command**
```bash
php artisan master:refresh
```

**Individual Phases:**
```bash
php artisan crawl:daily
```

This command triggers the complete pipeline manually.

## Safe Restart Procedure

### Graceful Shutdown

1. **Stop Queue Workers**

```bash
# If using supervisor
sudo supervisorctl stop private-search-queue:*

# If running manually
# Press Ctrl+C in queue worker terminal
```

2. **Wait for Jobs to Complete**

Check queue status:

```bash
php artisan queue:monitor
```

Wait until all jobs are completed or failed.

3. **Stop Application Server**

```bash
# Press Ctrl+C in artisan serve terminal
```

### Safe Startup

1. **Verify Environment Configuration**

```bash
php artisan config:clear
php artisan cache:clear
```

2. **Check Database Connection**

```bash
php artisan migrate:status
```

3. **Start Queue Workers**

```bash
# If using supervisor
sudo supervisorctl start private-search-queue:*

# If running manually
php artisan queue:work --tries=3 --timeout=600
```

4. **Start Application Server**

```bash
php artisan serve
```

5. **Verify System Health**

Check `http://localhost:8000/api/v1/health`

## Failure Recovery

### Crawler Failure Recovery

**Symptom:** Crawl jobs failing or stuck in queue

**Recovery Steps:**

1. Check queue status:

```bash
php artisan queue:status
```

2. Inspect failure reasons in logs:

```bash
# Windows (PowerShell)
Get-Content -Path storage/logs/laravel.log -Tail 100
```

3. Retry failed jobs:

```bash
php artisan queue:retry all
```

4. If jobs continue failing, clear queue and restart:

```bash
php artisan queue:clear
php artisan crawl:daily
```

### Indexer Failure Recovery

**Symptom:** Index generation failing or not meeting minimum record count

**Recovery Steps:**

1. Check indexer logs:

```bash
# Windows (PowerShell)
Get-Content -Path storage/logs/laravel.log -Tail 100 | Select-String "indexer"
```

2. Verify parsed record counts per category:

```bash
php artisan queue:status
```

3. If below threshold, trigger additional crawl for that category:

```bash
php artisan crawl:category technology
```

4. Retry indexing:

```bash
php artisan index:generate
```

### Upload Failure Recovery

**Symptom:** Google Drive upload failing

**Recovery Steps:**

1. Check upload logs for verification errors:

```bash
# Windows (PowerShell)
Get-Content -Path storage/logs/laravel.log -Tail 100 | Select-String "upload"
```

2. Retry upload command:

```bash
php artisan upload:index
```

### Cache Desync Recovery

**Symptom:** API serving stale data or missing data

**Recovery Steps:**

1. Force cache refresh from Google Drive:

```bash
php artisan cache:refresh --force
```

### Queue Worker Crash Recovery

**Symptom:** Queue worker process terminated unexpectedly

**Recovery Steps:**

1. Check system logs for crash reason:

```bash
dmesg | tail -50
```

2. Check Laravel logs:

```bash
tail -100 storage/logs/laravel.log
```

3. Restart queue worker:

```bash
php artisan queue:restart
php artisan queue:work --tries=3 --timeout=300
```

4. If using supervisor, supervisor will auto restart

### Database Corruption Recovery

**Symptom:** Database errors or corruption

**Recovery Steps:**

1. Backup current database:

```bash
cp database/database.sqlite database/database.sqlite.backup
```

2. Check database integrity:

```bash
sqlite3 database/database.sqlite "PRAGMA integrity_check;"
```

3. If corrupted, restore from backup or rebuild:

```bash
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh
```

4. Rebuild cache from Google Drive:

```bash
php artisan cache:rebuild
```

## Rollback Strategy

### Code Rollback

1. **Identify Last Known Good Commit**

```bash
git log --oneline
```

2. **Rollback to Commit**

```bash
git checkout <commit-hash>
```

3. **Reinstall Dependencies**

```bash
composer install
```

4. **Clear Caches**

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

5. **Restart Services**

Follow safe restart procedure above.

### Data Rollback

1. **Identify Last Known Good Index Files on Google Drive**

Check Google Drive folder for timestamped files.

2. **Download Specific Version**

```bash
php artisan cache:download --date=2025-12-16
```

3. **Verify Downloaded Files**

```bash
php artisan cache:verify
```

4. **Restart API Server**

```bash
php artisan serve
```

### Database Rollback

1. **Backup Current Database**

```bash
cp database/database.sqlite database/database.sqlite.current
```

2. **Restore from Backup**

```bash
cp database/database.sqlite.backup database/database.sqlite
```

3. **Verify Database**

```bash
php artisan migrate:status
```

4. **Restart Services**

Follow safe restart procedure above.

## Monitoring and Logs

### Log Locations

- **Application Logs:** `storage/logs/laravel.log`
- **Queue Worker Logs:** `storage/logs/queue-worker.log` (if using supervisor)
- **Crawler Logs:** `storage/logs/crawler.log`
- **API Logs:** `storage/logs/api.log`

### Log Rotation

Configure log rotation in `config/logging.php`:

```php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
],
```

### Health Check

Access the health endpoint: `GET http://localhost:8000/api/v1/health`

Returns:
- Database connectivity status
- Cache readiness and age
- API operational status
- System uptime

### Performance Monitoring

Access the statistics endpoint: `GET http://localhost:8000/api/v1/stats`

Returns:
- Crawl statistics (success rate, counts)
- Index statistics (record counts, generation dates)
- API request metrics

## Troubleshooting

### Common Issues

**Issue:** Queue jobs not processing  
**Solution:** Ensure queue worker is running: `php artisan queue:work`

**Issue:** Google Drive upload failing  
**Solution:** Verify credentials and test connection: `php artisan google-drive:test`

**Issue:** API returning 404  
**Solution:** Ensure cache is populated: `php artisan cache:refresh`

**Issue:** Crawl jobs timing out  
**Solution:** Increase timeout in `.env`: `CRAWLER_REQUEST_TIMEOUT=30`

**Issue:** Memory exhaustion during parsing  
**Solution:** Reduce max page size in `.env`: `CRAWLER_MAX_PAGE_SIZE=2097152`

## Production Deployment Considerations

While this system is designed for local development, production deployment would require:

1. **Web Server:** Nginx or Apache with PHP-FPM
2. **Process Manager:** Supervisor for queue workers
3. **Database:** PostgreSQL or MySQL instead of SQLite
4. **Cache:** Redis for improved performance
5. **Monitoring:** Application performance monitoring (APM)
6. **Logging:** Centralized log aggregation
7. **Backups:** Automated database and file backups
8. **SSL/TLS:** HTTPS for API endpoints
9. **Firewall:** Restrict API access to authorized IPs
10. **Secrets Management:** Use environment specific secret management

## Shared Hosting Deployment

Deploying Laravel on shared hosting requires specific adjustments due to the lack of `sudo` permissions and process managers like Supervisor.

### 1. Requirements
- **PHP 8.2+**: Ensure your hosting panel (cPanel, etc.) is set to the correct PHP version.
- **SQLite**: No special configuration needed. Ensure `database/database.sqlite` is writable.

### 2. Folder Structure
For security, keep the core project files OUTSIDE of your `public_html` directory:
```
/home/username/
├── private-search-engine/ (Core files)
└── public_html/ (Symlinked or copied from project/public)
```

**Option A: Symlink (Recommended)**
If your host allows symlinks, delete `public_html` and create a symlink:
```bash
ln -s /home/username/private-search-engine/public /home/username/public_html
```

**Option B: Manual Move**
If symlinks aren't allowed, move everything from `public/` into `public_html/` and update `index.php` paths:
```php
// index.php
require __DIR__.'/../private-search-engine/vendor/autoload.php';
$app = require_once __DIR__.'/../private-search-engine/bootstrap/app.php';
```

### 3. Background Tasks (Cron Jobs)
Since you cannot run Supervisor, use Cron jobs to handle the Scheduler and the Queue.

**Step 1: The Scheduler**
This runs the daily refresh cycle.
```cron
* * * * * /usr/local/bin/php /home/username/private-search-engine/artisan schedule:run >> /dev/null 2>&1
```

**Step 2: The Queue Worker**
On shared hosting, the queue worker cannot run indefinitely. Use the `--stop-when-empty` flag in a cron job that runs every minute.
```cron
* * * * * /usr/local/bin/php /home/username/private-search-engine/artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```
*Note: This ensures that if there are jobs, they get processed. If not, the process exits cleanly.*

### 4. Permissions
Ensure the following directories are writable by the web server (usually permission 755 or 775):
- `storage/`
- `bootstrap/cache/`

### 5. Environment Specifics
Set `APP_DEBUG=false` and `APP_ENV=production` in your `.env`.

---

## Security Checklist

- [ ] `.env` file not committed to version control
- [ ] Google Drive credentials secured
- [ ] File permissions set correctly (775 for storage)
- [ ] Debug mode disabled in production (`APP_DEBUG=false`)
- [ ] API rate limiting configured
- [ ] Queue worker running as non root user
- [ ] Database credentials secured
- [ ] Log files not publicly accessible
- [ ] Error messages do not expose sensitive information

## Maintenance Schedule

**Daily:**
- Monitor queue worker status
- Check log files for errors
- Verify index generation completed

**Weekly:**
- Review failed jobs
- Analyze crawl coverage
- Update seed URLs if needed

**Monthly:**
- Review and update dependencies: `composer update`
- Clean old log files
- Verify Google Drive quota

**Quarterly:**
- Security audit
- Performance optimization review
- Documentation updates
