<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\group_longterm_scores;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class partytest extends Controller
{
	//need to create a model for the group_longterm_scores WITHOUT migration to grab the data from the (codeigniter/pinescore original) 
	//table
	
	//$flights = Flight::where('active', 1)
	//->orderBy('name')
	//->take(10)
	//->get();
	public function index() {
		echo "hellos";
		
		$group_longterm_scores = DB::table('group_longterm_scores')->get();
		
		$revenueMonth = group_longterm_scores::where(
			'datetime', '>=', Carbon::now()->subMonth()->toDateTimeString()
		);
		dd($revenueMonth);
		
		foreach ($revenueMonth as $revenueMonth) {
			echo $group_longterm_score->score." willy";
		}

		foreach ($group_longterm_scores as $group_longterm_score) {
			//echo $group_longterm_score->score;
		}
		

		
		
	}
}