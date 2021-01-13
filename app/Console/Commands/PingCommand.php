<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\PingJob;
use App\Jobs\PingJobDribblyBits;
use App\ping_ip_table;
use App\oscheck;

class PingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:PingCommand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'the main man behind pinescore, pings all nodes and updates database';

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
    //wip111 protect class from only being able to be ran by certain IP

    {
        $start = strtotime('now');
        $end = $start + 58;
        $timeleft = $end - strtotime('now');
        $we_managed_to_cycle_x_times = 0;

    $ping_ip_table = ping_ip_table::all()->unique('ip');

        Logger("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ Processing ".count($ping_ip_table)." nodes.         mem used: ".$this->get_server_memory_usage(). "      cpu: ".$this->get_server_cpu_usage());

        while($timeleft > 10)
        {
            $queue_size = \Queue::size("default");

            Logger("-------------------------------------- Queue size currently: $queue_size");

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

        Logger("xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx We managed $we_managed_to_cycle_x_times ping cycles.                    mem used: ".$this->get_server_memory_usage(). "      cpu: ".$this->get_server_cpu_usage());
    }

    private function get_server_memory_usage(){
        $oscheck = new oscheck;
        if(!$oscheck->isLinux()) //macos - development laptop. no mem command available
            {
                return "lol macos";
            } else {
                $free = shell_exec('free');
                $free = (string)trim($free);
                $free_arr = explode("\n", $free);
                $mem = explode(" ", $free_arr[1]);
                $mem = array_filter($mem);
                $mem = array_merge($mem);
                $memory_usage = $mem[2] / $mem[1] * 100;

                return round($memory_usage,0);
            }
    }

    private function get_server_cpu_usage(){

        $load = sys_getloadavg();
        return $load[0];

    }
}
