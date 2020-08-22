<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\PingJob;
use App\Ping_ip_table;

class PingController extends Controller
{
    public function index() {
     
        //protect class from only being able to be ran by certain IP

        //next: work through PingJob

        $start = strtotime('now');
        $end = $start + 58;
        $timeleft = $end - strtotime('now');
        $we_managed_to_cycle_x_times = 0;
        
        $Ping_ip_table = Ping_ip_table::all()->unique('ip');

        Logger("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ Processing ".count($Ping_ip_table)." nodes");

        while($timeleft > 10)
        {
            $queue_size = \Queue::size("default");

            Logger("-------------------------------------- Queue size currently: $queue_size");

            if($queue_size < 1) {
                foreach ($Ping_ip_table as $Ping_ip_table_row)
                {
                    dispatch(new PingJob($Ping_ip_table_row));
                }
                $we_managed_to_cycle_x_times++;
            }
            sleep(9);
            $timeleft = $end - strtotime('now');
        }

        Logger("xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx We managed $we_managed_to_cycle_x_times ping cycles.");
    }
    
}
