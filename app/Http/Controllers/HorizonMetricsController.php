<?php

namespace App\Http\Controllers;

use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\WaitTimeCalculator;
use Laravel\Horizon\Contracts\JobRepository;
use Illuminate\Http\Response;

class HorizonMetricsController extends Controller
{
    protected $metrics;
    protected $waitTime;
    protected $jobs;

    public function __construct(
        MetricsRepository $metrics,
        WaitTimeCalculator $waitTime,
        JobRepository $jobs
    ) {
        $this->metrics = $metrics;
        $this->waitTime = $waitTime;
        $this->jobs = $jobs;
    }

    public function show()
    {
        try {
            // Get jobs per minute
            $jobsPerMinute = $this->metrics->jobsProcessedPerMinute();
            
            // Get failed jobs count
            $failedJobs = $this->getFailedJobsCount();

            // Determine if active based on recent job processing
            $isActive = $jobsPerMinute > 0;

            return response()->json([
                'status' => 'success',
                'metrics' => [
                    'jobs_per_minute' => $jobsPerMinute,
                    'jobs_past_hour' => null,  // TODO: Implement correct historical jobs calculation
                    'failed_jobs_past_day' => $failedJobs,
                    'engine_status' => $isActive ? 'active' : 'inactive'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to retrieve Horizon metrics',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    protected function getFailedJobsCount()
    {
        try {
            $snapshot = $this->jobs->getFailed();
            return count($snapshot);
        } catch (\Exception $e) {
            return 0;
        }
    }
}