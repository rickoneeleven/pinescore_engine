<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class HorizonMetricsController extends Controller
{
    /**
     * Show the Horizon metrics dashboard data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show()
    {
        try {
            $metrics = DB::table('health_dashboard')
                ->whereIn('metric', [
                    'jobs_per_minute',
                    'jobs_past_hour',
                    'failed_jobs_past_day',
                    'engine_status'
                ])
                ->get()
                ->pluck('result', 'metric')
                ->toArray();

            // Ensure all metrics exist with defaults if not set
            $metrics = array_merge([
                'jobs_per_minute' => 0,
                'jobs_past_hour' => null,
                'failed_jobs_past_day' => 0,
                'engine_status' => 'inactive'
            ], $metrics);

            // Convert numeric strings to integers
            foreach (['jobs_per_minute', 'jobs_past_hour', 'failed_jobs_past_day'] as $numericMetric) {
                if (isset($metrics[$numericMetric]) && is_numeric($metrics[$numericMetric])) {
                    $metrics[$numericMetric] = (int)$metrics[$numericMetric];
                }
            }

            // Check data freshness only if we have any data
            $latestUpdate = DB::table('health_dashboard')->max('updated_at');
            
            if ($latestUpdate && Carbon::parse($latestUpdate)->diffInMinutes(now()) > 5) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Metrics may be stale. Last update was ' . $latestUpdate,
                    'metrics' => $metrics
                ]);
            }

            return response()->json([
                'status' => 'success',
                'metrics' => $metrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to retrieve Horizon metrics',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}