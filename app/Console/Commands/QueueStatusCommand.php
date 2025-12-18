<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueStatusCommand extends Command
{
    protected $signature = 'queue:status';
    protected $description = 'Show queue statistics and status';

    public function handle()
    {
        $this->info('=== Queue Status ===');
        $this->newLine();

        // Pending jobs
        $pending = DB::table('jobs')->count();
        $this->line("📋 Pending Jobs: <fg=yellow>{$pending}</>");

        // Failed jobs
        $failed = DB::table('failed_jobs')->count();
        $this->line("❌ Failed Jobs: <fg=red>{$failed}</>");

        // Completed crawl jobs
        $completed = DB::table('crawl_jobs')
            ->where('status', 'completed')
            ->count();
        $this->line("✅ Completed Crawls: <fg=green>{$completed}</>");

        // Failed crawl jobs
        $failedCrawls = DB::table('crawl_jobs')
            ->where('status', 'failed')
            ->count();
        $this->line("⚠️  Failed Crawls: <fg=red>{$failedCrawls}</>");

        // Processing
        $processing = DB::table('crawl_jobs')
            ->where('status', 'processing')
            ->count();
        $this->line("⚙️  Processing: <fg=cyan>{$processing}</>");

        $this->newLine();

        // Breakdown by category
        $this->info('=== By Category ===');
        $categories = DB::table('crawl_jobs')
            ->select('category', 
                DB::raw('count(*) as total_jobs'),
                DB::raw('sum(case when status = "completed" then 1 else 0 end) as completed_jobs'),
                DB::raw('sum(case when status = "failed" then 1 else 0 end) as failed_jobs')
            )
            ->groupBy('category')
            ->get();

        foreach ($categories as $cat) {
            $records = DB::table('parsed_records')
                ->where('category', $cat->category)
                ->count();
            
            $this->line("<fg=cyan>{$cat->category}</>:");
            $this->line("  Jobs: {$cat->total_jobs} (✅ {$cat->completed_jobs} | ❌ {$cat->failed_jobs})");
            $this->line("  Records: <fg=green>{$records}</>");
        }

        $this->newLine();

        // Parsed records
        $parsed = DB::table('parsed_records')->count();
        $this->info("📝 Total Parsed Records: <fg=green>{$parsed}</>");

        return Command::SUCCESS;
    }
}
