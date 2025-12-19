# Cron & Automation Guide (Shared Hosting Optimized)

This guide explains how to properly automate the Private Search Engine on shared hosting environments (Hostinger/hPanel) where long-running processes are often killed by LVE limits.

## 1. The Universal Scheduler (Recommended)
Laravel provides a built-in scheduler. By adding **one** entry to your server's crontab, you enable automatic crawling, indexing, and queue processing.

### Setup
Run `crontab -e` via terminal or use the Cron Jobs section in your hosting panel (hPanel). Add:

```cron
* * * * * cd /home/u146506433/domains/worldoftech.company/public_html/search && php artisan schedule:run >> /dev/null 2>&1
```

**Why this works on Shared Hosting:**
- **Chunked Processing**: The scheduler now triggers `queue:work` in small batches every minute.
- **Auto-stop**: Each batch stops after 100 jobs or when empty, staying under memory/time limits.
- **Resumable**: If a process is killed, the next minute's run picks up where it left off.

---

## 2. Manual Refresh / One-Off Runs
If you need to trigger a full refresh manually, use:

```bash
php artisan master:refresh
```

This runs synchronously but uses the same chunking logic safely. Use `--fresh` ONLY if you want to wipe everything and start from zero.

---

## 3. Monitoring & Health
Since there is no "Supervisor" on shared hosting, use these commands to monitor progress:

### Check Queue Status
See how many jobs are pending:
```bash
php artisan queue:status
```

### Tail Logs
Monitor real-time errors or crawl completions:
```bash
tail -f storage/logs/laravel.log
```

### Check Scheduler
Verify that the schedule is recognized:
```bash
php artisan schedule:list
```

---

## 4. Summary of Strategy

| Strategy | Implementation | Benefit |
|---------|-------|-----------|
| **Queue Worker** | Scheduled every minute | Prevents LVE timeouts |
| **Database** | SQLite WAL Mode | Resolves "Database Locked" errors |
| **Resumption** | Idempotent Jobs | Continues after crashes |
| **Monitoring** | Log tailing | Easy debugging |
