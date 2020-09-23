<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\ping_ip_table;
use App\ping_result_table;
use App\other;

class PingJobDribblyBits implements ShouldQueue
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
        if($this->ping_ip_table_row->last_email_status == "New") {
            $last_ms = 100;
        } else {
            $last_ms = $this->getAverageOfLastXpings($this->ping_ip_table_row->ip);
        }
        
        $ping_ip_table = ping_ip_table::where('ip', $this->ping_ip_table_row->ip)->get();

        foreach($ping_ip_table as $ping_ip_table_row) { //we have to do this foreach, as the get() command above does not allow
            //save() for multiple returns. and we need to update each row that has this ip, even though we can just do the initial calculation
            //on just the initial row passed to class
            if($ping_ip_table_row->last_email_status == "Online") $ping_ip_table_row->last_online_toggle = date('Y-m-d H:i:s'); //this is
            //used to calulate how long nodes have been online, and change table row colours, and help filter nodes over 72 hours.
            
            $lta_difference_algo = $this->ltaCurrentMsDifference(array(
                'average_longterm_ms'   => $ping_ip_table_row->average_longterm_ms,
                'last_ms'               => $last_ms,
            ));

            $ping_ip_table_row->lta_difference_algo = $lta_difference_algo;
            $ping_ip_table_row->last_ms = $last_ms;
            $ping_ip_table_row->save();
        }

    }

    private function getAverageOfLastXpings($ip) {

        $ping_result_table = ping_result_table::where('ip', $ip)
        ->orderBy('datetime', 'desc')
        ->limit(9) //don't do any more than 9, because if a new node is added, it tries to get the status of say > 10, but because we only have 10 pingt_result
        //histories the engine hangs searching through the whole ping_result table
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

    private function ltaCurrentMsDifference($array) {
        $percent_n_ms_diff = $this->getPercentAndMsForDiff();
        $difference_algo = 0;
        $difference_percent = 0;
        if(!$array['average_longterm_ms']) return $difference_algo;

        $difference_ms = $array['average_longterm_ms'] - $array['last_ms'];
        $difference_percent = round((1 - $array['last_ms']/$array['average_longterm_ms'])*100,0);
        if($difference_ms != 0 && $difference_ms < 0) {
            if($difference_ms <= "-".$percent_n_ms_diff['ms_diff'] && $difference_percent < $percent_n_ms_diff['percent_diff_slower']) { //slower than usual response times
                $difference_algo = $difference_percent;
            }
        } else {
            if($difference_ms >= $percent_n_ms_diff['ms_diff'] && $difference_percent > $percent_n_ms_diff['percent_diff_quicker']) { //faster than usual response times
                $difference_algo = "-".$difference_percent;
            }
        }
        return $difference_algo;
    }

    private function getPercentAndMsForDiff() {
        $returnArray = array();

        $otherTable_percent_quicker = other::find(1);
        $returnArray['percent_diff_quicker'] = $otherTable_percent_quicker['value'];

        $otherTable_percent_slower = other::find(9);
        $returnArray['percent_diff_slower'] = $otherTable_percent_slower['value'];

        $otherTable_ms = other::find(2);
        $returnArray['ms_diff'] = $otherTable_ms['value'];
    
        return $returnArray;
    }

}


