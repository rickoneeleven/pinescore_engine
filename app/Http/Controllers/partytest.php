<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\group_longterm_scores;
use App\group_monthly_scores;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class partytest extends Controller
{
	public function index() {
		$group_longterm_scores = DB::table('group_longterm_scores')
				->whereDate('datetime', '>', Carbon::now()->subMonth(1)->toDateTimeString())
                ->get();

		foreach ($group_longterm_scores as $group_longterm_score) {
			if(!isset($group[$group_longterm_score->group_id])) {
				$group[$group_longterm_score->group_id] = array();
			}
			array_push($group[$group_longterm_score->group_id], $group_longterm_score->score);
			sort($group[$group_longterm_score->group_id]);
		}
		foreach($group as $group_id => $scores) {
			$mean = round(array_sum($scores)/count($scores));
			echo "group: $group_id<br>scores: ". implode(', ', $scores)."<br>mean: $mean<br><br>";
			
			$group_monthly_scores = new group_monthly_scores;
			$group_monthly_scores->group_id = $group_id;
			$group_monthly_scores->score = $mean;
			$group_monthly_scores->save();
		}
	}
}