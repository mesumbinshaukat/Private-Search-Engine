# Cron & Automation Guide

This guide explains how to properly automate the Private Search Engine using Cron jobs on a Linux/Production server.

## 1. The Main Laravel Scheduler (Recommended)
Laravel provides a built-in scheduler that handles all internal tasks (like the daily Refresh Cycle). You only need to add **one** entry to your server's crontab.

### Setup
Run `crontab -e` and add the following line:

```cron
* * * * * cd /home/u146506433/domains/worldoftech.company/public_html/search && php artisan schedule:run >> /dev/null 2>&1
```

**What this does:**
- Runs every minute (`* * * * *`).
- Automatically triggers the `MasterRefreshJob` daily (at midnight).
- Automatically clears the Master API key cache daily.

---

## 2. Manual Master Refresh Automation
If you prefer to trigger the full refresh cycle (Crawl -> Index -> Upload) at a specific time without using the Laravel scheduler, you can add it directly to crontab.

### Example: Run daily at 2 AM
```cron
0 2 * * * cd /home/u146506433/domains/worldoftech.company/public_html/search && php artisan master:refresh --async >> /home/u146506433/domains/worldoftech.company/public_html/search/storage/logs/master_refresh.log 2>&1
```

---

## 3. Perpetual Queue Worker (Critical for Crawling)
Since the crawler dispatches thousands of jobs, you need a background process to work through them. You have two options:

### Option A: stop-when-empty (Via Master Refresh)
The `master:refresh` command already includes a step to run `php artisan queue:work --stop-when-empty`. This is good for occasional refreshes.

### Option B: Daemon Mode (Recommended for high volume)
To keep the worker running 24/7 (handling 2000+ crawls smoothly), use a process manager like **Supervisor** or a screen session.

#### Using Cron to ensure it's always running:
```cron
* * * * * pgrep -f "queue:work" > /dev/null || cd /home/u146506433/domains/worldoftech.company/public_html/search && php artisan queue:work --tries=3 --timeout=600 >> /dev/null 2>&1
```

---

## 4. Summary of Commands

| Command | Usage | Frequency |
|---------|-------|-----------|
| `php artisan master:refresh` | Full cycle + real-time output | Manual / Daily |
| `php artisan master:refresh --async` | Full cycle in background | Scheduled |
| `php artisan queue:work` | Process the crawl/parse queue | 24/7 |
| `php artisan queue:status` | Check how many crawls are left | As needed |
| `php artisan optimize:clear` | Clear all system caches | After code changes |

## 5. Log Monitoring
You should monitor the logs to ensure your cron jobs are succeeding:
```bash
tail -f storage/logs/laravel.log
```
