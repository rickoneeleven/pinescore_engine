<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\WaitTimeCalculator;
use Laravel\Horizon\Contracts\JobRepository;
use Illuminate\Support\Facades\DB;

class UpdateHorizonMetricsCommand extends Command
{
    protected $signature = 'horizon:update-metrics';
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
        // Get current metrics
        $jobsPerMinute = $this->metrics->jobsProcessedPerMinute();
        $failedJobs = count($this->jobs->getFailed());
        $engineStatus = $jobsPerMinute > 0 ? 'active' : 'inactive';

        // Update or insert metrics
        $metrics = [
            'jobs_per_minute' => $jobsPerMinute,
            'failed_jobs_past_day' => $failedJobs,
            'engine_status' => $engineStatus,
            'jobs_past_hour' => null // Placeholder for future implementation
        ];

        foreach ($metrics as $metric => $result) {
            DB::table('health_dashboard')->updateOrInsert(
                ['metric' => $metric],
                [
                    'result' => is_numeric($result) ? (string)$result : $result,
                    'updated_at' => now()
                ]
            );
        }
    }
}