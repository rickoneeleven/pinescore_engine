<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\group_monthly_scores;
use Carbon\Carbon;

class truncateDB extends Controller
{
    public function itsShowtime() {
        group_monthly_scores::whereDate( 'created_at', '<=', now()->subMonths( 13 ) )->delete();
        
        echo "it is done.";
    }
    
}
