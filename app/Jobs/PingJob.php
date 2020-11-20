<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\stat;
use App\ping_result_table;
use App\ping_ip_table;
use App\alerts;
use App\Notifications\NodeChangeAlert;
use Notification;

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
            if(!$this->icmpControl()) return false;
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

        $ping_result_table_duplicate_protection = 0;
        $ping_ip_table = ping_ip_table::where('ip', $this->ping_ip_table_row->ip)->get(); //we've not had this table in here before, we just
        //passed a single row via the contruct.
        foreach($ping_ip_table as $ping_ip_table_row) { //we have to do this foreach, as the get() command above does not allow
            //save() for multiple returns.

            $count_plus_one_for_readability_in_reports = $ping_ip_table_row->count +1;
            $result = "packet dropped (".$count_plus_one_for_readability_in_reports."/10 consecutive)";
            if($this->ping_ip_table_row->last_email_status == "Offline") $result = "node offline, trying to re-establish stable session (".$count_plus_one_for_readability_in_reports."/10 consecutive)";
            if($this->ping_ip_table_row->last_email_status == "New") $result = "Sending welcome parcel to new node...i think it's $online_or_offline";


            if($ping_ip_table_row->last_email_status == "") { //if no status is set at all, new node, update to that this most recent result
                $ping_ip_table_row->last_email_status = $online_or_offline;
                $ping_ip_table_row->save();
            }


            if($ping_ip_table_row->last_email_status != $online_or_offline || $ping_ip_table_row->count > 0) {

                if ($ping_ip_table_row->count > 9 && $ping_ip_table_row->last_email_status != $online_or_offline) { //count is at 10 and still different
                    //from last_email_status so we need to take some action
                    $associated_alerts = alerts::where('ping_ip_id', $ping_ip_table_row->id)->get();
                    foreach($associated_alerts as $alerts_row) {
                        $data['alerts_row'] = $alerts_row;
                        $data['ping_ip_table_row'] = $ping_ip_table_row;
                        $data['now_state'] = $online_or_offline;

                        Notification::route('mail', $alerts_row->email)->notify(new NodeChangeAlert($data));

                    }
 
                    $ping_ip_table_row->last_email_status = $online_or_offline;
                    $ping_ip_table_row->count = 0;
                    $ping_ip_table_row->save();

                    if($ping_result_table_duplicate_protection === 0) {
                        $ping_result_table = new ping_result_table;
                        $ping_result_table->ip = $this->ping_ip_table_row->ip;
                        $ping_result_table->datetime =date('Y-m-d H:i:s');
                        $ping_result_table->ms = $ping_ms;
                        $ping_result_table->result = $online_or_offline;
                        $ping_result_table->change = $change;
                        $ping_result_table->email_sent = "Node is now <strong>$online_or_offline</strong>";
                        $ping_result_table->save();
                        $ping_result_table_duplicate_protection++;
                    }

                    
                } else if($ping_ip_table_row->last_email_status != $online_or_offline) { 

                    $ping_ip_table_row->count = ++$ping_ip_table_row->count;
                    $ping_ip_table_row->count_direction = "Up";
                    $ping_ip_table_row->save();
                
                } else {

                    $ping_ip_table_row->count = --$ping_ip_table_row->count;
                    $ping_ip_table_row->count_direction = "Down";
                    $ping_ip_table_row->save();

                }
                
            } 

            if($ping_result_table_duplicate_protection === 0) {
                $ping_result_table = new ping_result_table;
                $ping_result_table->ip = $this->ping_ip_table_row->ip;
                $ping_result_table->datetime =date('Y-m-d H:i:s');
                $ping_result_table->ms = $ping_ms;
                $ping_result_table->result = $online_or_offline;
                $ping_result_table->change = $change;
                $ping_result_table->email_sent = $result;
                $ping_result_table->save();
                $ping_result_table_duplicate_protection++;
            }

            $ping_ip_table_row->last_ran = date('Y-m-d H:i:s');
            $ping_ip_table_row->save();
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

    private function icmpControl() {
        $opendns = $this->pingv2("opendns.com");

        if($opendns > 1) {
            return true;
        } else {
            $usetoday = $this->pingv2("usatoday.com");
            if($usetoday > 1) {
                return true;
            } else {
                Logger("Killing engine, control failed");
                return false;
            }

        }

    }









}
