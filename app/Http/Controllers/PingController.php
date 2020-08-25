<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\PingJob;
use App\Jobs\PingJobDribblyBits;
use App\ping_ip_table;

class PingController extends Controller
{
    public function index() {
    
        //protect class from only being able to be ran by certain IP

        //next: 
        //:get artisan setup to run crons and run pingcontroller (with less logging or a way logs clean out each time?)
        //:finish transfering dave function in PingJob

        $start = strtotime('now');
        $end = $start + 58;
        $timeleft = $end - strtotime('now');
        $we_managed_to_cycle_x_times = 0;
        
        $ping_ip_table = ping_ip_table::all()->unique('ip');

        Logger("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ Processing ".count($ping_ip_table)." nodes");

        while($timeleft > 10)
        {
            $queue_size = \Queue::size("default");

            //Logger("-------------------------------------- Queue size currently: $queue_size");

            if($queue_size < 1) {
                foreach ($ping_ip_table as $ping_ip_table_row)
                {
                    dispatch(new PingJob($ping_ip_table_row));
                    dispatch(new PingJobDribblyBits($ping_ip_table_row));
                    //die("1 bomb");
                }
                $we_managed_to_cycle_x_times++;
            }
            sleep(9);
            $timeleft = $end - strtotime('now');
        }

        Logger("xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx We managed $we_managed_to_cycle_x_times ping cycles.");
    }
    
}
