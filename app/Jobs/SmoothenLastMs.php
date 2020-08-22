<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\ping_ip_table;
use App\ping_result_table;

class SmoothenLastMs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ping_ip_table_row;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ping_ip_table_row)
    {
        $this->ping_ip_table_row = $ping_ip_table_row;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $last_ms = $this->getAverageOfLastXpings($this->ping_ip_table_row->ip);
        $ping_ip_table = ping_ip_table::where('ip', $this->ping_ip_table_row->ip)->get();

        foreach($ping_ip_table as $ping_ip_table_row) {
            $ping_ip_table_row->last_ran = date('Y-m-d H:i:s');
            $ping_ip_table_row->last_ms = $last_ms;
            $ping_ip_table_row->save();
        }

    }

    private function getAverageOfLastXpings($ip) {

        $ping_result_table = ping_result_table::where('ip', $ip)
        ->orderBy('datetime', 'desc')
        ->limit(11)
        ->get();

        $average = array();
        foreach ($ping_result_table as $ping_result_table_row)
        {
	        $average[] = $ping_result_table_row->ms; //start adding the last x recent response ms's to an array, so we can get the average later.
        }


        $average = $this->remove_element($average,0);
        if(empty($average)) {
            $return_average = 0;
        } else {
            $return_average = array_sum($average) / count($average);
        }
	    return $return_average;
    }

    private function remove_element($array, $value) {
        foreach (array_keys($array, $value) as $key) {
            unset($array[$key]);
        }
        return $array;
    }
}
