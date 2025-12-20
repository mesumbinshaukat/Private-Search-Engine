<?php

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Models\Url;
use App\Models\CrawlQueue;
use App\Models\Document;
use App\Models\Token;
use Illuminate\Console\Command;

class MonitorCommand extends Command
{
    protected $signature = 'crawler:monitor {--hours=24 : Hours of metrics to display}';

    protected $description = 'Display crawl health monitoring dashboard';

    public function handle(MetricsService $metrics): int
    {
        $hours = (int) $this->option('hours');

        $this->info("=== Crawler Health Dashboard (Last {$hours} hours) ===\n");

        // Database Statistics
        $this->line('<fg=cyan>Database Statistics:</>');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total URLs', number_format(Url::count())],
                ['Pending URLs', number_format(Url::pending()->count())],
                ['Crawled URLs', number_format(Url::crawled()->count())],
                ['Failed URLs', number_format(Url::failed()->count())],
                ['Queue Size', number_format(CrawlQueue::count())],
                ['Locked Queue Items', number_format(CrawlQueue::locked()->count())],
                ['Documents Indexed', number_format(Document::count())],
                ['Unique Tokens', number_format(Token::count())],
            ]
        );

        // Metrics Summary
        $summary = $metrics->getSummary($hours);

        $this->newLine();
        $this->line('<fg=cyan>Performance Metrics:</>');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Avg Fetch Rate', round($summary['fetch_rate_avg'] ?? 0, 2) . ' URLs/min'],
                ['Current Queue Backlog', number_format($summary['queue_backlog_current'] ?? 0)],
                ['Parse Success Rate', round($summary['parse_success_rate'] ?? 0, 2) . '%'],
            ]
        );

        // HTTP Status Distribution
        if (!empty($summary['http_status_distribution'])) {
            $this->newLine();
            $this->line('<fg=cyan>HTTP Status Distribution:</>');
            
            $statusData = [];
            foreach ($summary['http_status_distribution'] as $status => $count) {
                $statusData[] = [$status, number_format($count)];
            }
            
            $this->table(['Status Code', 'Count'], $statusData);
        }

        // URL Status Distribution
        $this->newLine();
        $this->line('<fg=cyan>URL Status Distribution:</>');
        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                [
                    'Pending',
                    number_format(Url::pending()->count()),
                    round((Url::pending()->count() / max(Url::count(), 1)) * 100, 2) . '%'
                ],
                [
                    'Crawled',
                    number_format(Url::crawled()->count()),
                    round((Url::crawled()->count() / max(Url::count(), 1)) * 100, 2) . '%'
                ],
                [
                    'Failed',
                    number_format(Url::failed()->count()),
                    round((Url::failed()->count() / max(Url::count(), 1)) * 100, 2) . '%'
                ],
            ]
        );

        $this->newLine();
        $this->info('Dashboard refreshed successfully!');

        return self::SUCCESS;
    }
}
