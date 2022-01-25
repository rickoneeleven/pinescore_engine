<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\group_longterm_scores;
use App\group_monthly_scores;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreMonthlyGroupScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:StoreMonthlyGroupScores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets the average score for each group over the last 30 days and stores in DB';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
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
