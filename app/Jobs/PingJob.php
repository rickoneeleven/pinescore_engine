<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\stat;

class PingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $Ping_ip_table_row;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($Ping_ip_table_row)
    {
        $this->Ping_ip_table_row = $Ping_ip_table_row;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ping_ms = $this->pingv2($this->Ping_ip_table_row->ip);
        $online_or_offline = $this->onlineOrOffline($ping_ms);
        logger("pinging " . $this->Ping_ip_table_row->ip . " ".$ping_ms."ms | ".$online_or_offline);



        if($online_or_offline != $this->Ping_ip_table_row->last_email_status) {
            $change = 1;
            logger("CHANGE");
            $stat = new stat;
            $stat->ip = $this->Ping_ip_table_row->ip;
            $stat->datetime = date('Y-m-d H:i:s');
            $stat->score = -1;
            $stat->save();
            //$this->lemon->score($row->ip);
        } else {
            $change = 0;
        }
    }

    public function dave() {
        //$last_result = $this->icmpmodel->lastResult($row->ip);
        //$last_result_result = $this->icmpmodel->lastResultResult($row->ip);



        $data_db = array(
            'ip' => $row->ip ,
            'datetime' => date('Y-m-d H:i:s'),
            'ms' => $ping_ms,
            'result' => $online_or_offline,
            'change' => $change,
            'email_sent' => 0,
        );
        $this->db->insert('ping_result_table', $data_db); //insert into big results table
/////////////////////////////////////////////////////////////////////////////
        $data_static = array( //to stop the users/auto refresh table talking to results table, we store it here
            'last_ran' => date('Y-m-d H:i:s'),
            'last_ms' => $last_result_result['average']
        );
        $this->db->where('ip', $row->ip);
        $this->db->update('ping_ip_table', $data_static); //insert into quick table
/////////////////////////////////////////////////////////////////////////////

        if($data_db['result']=="Online") { //update last online date toggle so we can filter for stuff that is offline with a 72hour+ online toggle and then it can be hidden 
            $data2 = array( //update table status
                'last_online_toggle' => date('Y-m-d H:i:s'),
            );
            $this->db->where('ip', $row->ip);
            $this->db->update('ping_ip_table', $data2); 
/////////////////////////////////////////////////////////////////////////////
        }
        $arrayForAlgo = array(
                'last_ms'               => $last_result_result['average'],
                'average_longterm_ms'   => $row->average_longterm_ms,
            );
        $lta_difference_algo = $this->average30days_model->ltaCurrentMsDifference($arrayForAlgo);
        $this->db->where('ip', $row->ip);
        $this->db->update('ping_ip_table', array('lta_difference_algo' => $lta_difference_algo));
/////////////////////////////////////////////////////////////////////////////

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
