<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\WaitTimeCalculator;
use Laravel\Horizon\Contracts\JobRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UpdateHorizonMetricsCommand extends Command
{
    protected $signature = 'horizon:update-metrics {--debug : Show detailed debug output}';
    protected $description = 'Update Horizon metrics in the database';

    protected $metrics;
    protected $waitTime;
    protected $jobs;

    public function __construct(
        MetricsRepository $metrics,
        WaitTimeCalculator $waitTime,
        JobRepository $jobs
    ) {
        parent::__construct();
        $this->metrics = $metrics;
        $this->waitTime = $waitTime;
        $this->jobs = $jobs;
    }

    public function handle()
    {
        $this->info('Starting Horizon metrics collection...');

        // Get current metrics
        $jobsPerMinute = $this->metrics->jobsProcessedPerMinute();
        $this->line("• Jobs processed per minute: {$jobsPerMinute}");
        
        $failedJobs = count($this->jobs->getFailed());
        $this->line("• Failed jobs count: {$failedJobs}");
        
        $engineStatus = $jobsPerMinute > 0 ? 'active' : 'inactive';
        $this->line("• Engine status: {$engineStatus}");

        $cyclesPerMinute = (int) Redis::get('engine_cycles_last_minute') ?? 0;
        $this->line("• Cycles per minute: {$cyclesPerMinute}");

        if ($this->option('debug')) {
            $this->warn("\nDetailed metrics information:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['jobs_per_minute', $jobsPerMinute],
                    ['failed_jobs_past_day', $failedJobs],
                    ['engine_status', $engineStatus],
                    ['cycles_per_minute', $cyclesPerMinute],
                    ['jobs_past_hour', '0 (not implemented)']
                ]
            );
        }

        // Update or insert metrics
        $metrics = [
            'jobs_per_minute' => $jobsPerMinute,
            'failed_jobs_past_day' => $failedJobs,
            'engine_status' => $engineStatus,
            'cycles_per_minute' => $cyclesPerMinute,
            'jobs_past_hour' => 0
        ];

        $this->info("\nUpdating database records...");

        foreach ($metrics as $metric => $result) {
            try {
                DB::table('health_dashboard')->updateOrInsert(
                    ['metric' => $metric],
                    [
                        'result' => is_numeric($result) ? (string)$result : $result,
                        'updated_at' => now()
                    ]
                );
                $this->line("✓ Updated {$metric}");
            } catch (\Exception $e) {
                $this->error("Failed to update {$metric}: " . $e->getMessage());
            }
        }

        $this->info("\nMetrics update completed!");
    }
}