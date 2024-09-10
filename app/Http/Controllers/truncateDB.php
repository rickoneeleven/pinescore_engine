<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\group_monthly_scores;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class truncateDB extends Controller
{
    public function itsShowtime()
    {
        $messages = [];

        // Delete old records
        try {
            $deletedCount = group_monthly_scores::whereDate('created_at', '<=', now()->subMonths(13))->delete();
            $messages[] = "Deleted $deletedCount old records from group_monthly_scores.";
        } catch (\Exception $e) {
            $messages[] = "Error deleting old records: " . $e->getMessage();
        }

        // Optimize the failed_jobs table
        if (Schema::hasTable('failed_jobs')) {
            try {
                DB::statement('OPTIMIZE TABLE failed_jobs');
                $messages[] = "Optimized failed_jobs table.";
            } catch (\Exception $e) {
                $messages[] = "Error optimizing failed_jobs table: " . $e->getMessage();
            }
        } else {
            $messages[] = "Table 'failed_jobs' does not exist. Skipping optimization.";
        }

        $result = implode("\n", $messages);
        
        // Echo the result
        echo $result;

        // Also return the result
        return $result;
    }
}