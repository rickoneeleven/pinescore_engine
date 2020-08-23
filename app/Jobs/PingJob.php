<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\stat;
use App\ping_result_table;

class PingJob implements ShouldQueue
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
        $ping_ms = $this->pingv2($this->ping_ip_table_row->ip);
        $online_or_offline = $this->onlineOrOffline($ping_ms);
        //logger("pinging " . $this->ping_ip_table_row->ip . " ".$ping_ms."ms | ".$online_or_offline);




        if($online_or_offline != $this->ping_ip_table_row->last_email_status) {
            $change = 1;
            //logger("CHANGE");
            $stat = new stat;
            $stat->ip = $this->ping_ip_table_row->ip;
            $stat->datetime = date('Y-m-d H:i:s');
            $stat->score = -1;
            $stat->save();
        } else {
            $change = 0;
        }


        $ping_result_table = new ping_result_table;
        $ping_result_table->ip = $this->ping_ip_table_row->ip;
        $ping_result_table->datetime =date('Y-m-d H:i:s');
        $ping_result_table->ms = $ping_ms;
        $ping_result_table->result = $online_or_offline;
        $ping_result_table->change = $change;
        $ping_result_table->email_sent = 0;
        $ping_result_table->save();


    }

    public function dave() {
        $last_result = $this->icmpmodel->lastResult($row->ip);

        foreach($last_result as $last_result) {
            $this->hasStatusChanged($last_result, $data_db, $row);
        }
    }

    private function pingv2($host, $timeout = 1) 
    {
        for ($k = 0 ; $k < 2; $k++) {
            $output = array();
            $com = 'ping -n -w ' . $timeout . ' -c 1 ' . escapeshellarg($host);
            $exitcode = 0;
            exec($com, $output, $exitcode);
            
            if ($exitcode == 0 || $exitcode == 1)
            { 
                foreach($output as $cline)
                {
                    if (strpos($cline, 'time') !== FALSE)
                    {
                        $out = (int)ceil(floatval(substr($cline, strpos($cline, 'time=') + 5)));
                        return $out;
                    }
                }
            }
            unset($output);
        }
        
        return FALSE;
    }

    private function onlineOrOffline($online_or_offline) {
        if($online_or_offline == 0) {
            return "Offline";
        } else {
            return "Online";
        }
    }









}
