<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\PingJob;
use App\Ips;
use App\Ping_ip_table;

class PingController extends Controller
{
    public function index() {

        //print out novascore ping controller code and write out in english each step it's doing here, for example
            //getting a list of all nodes
            //pinging each node and updating DB with status
        

        //yeah so i'd like a cycle for offline nodes, and one for on. and offline nodes includes those that were on, and have just dropped even one ping. because
            //i don't need that shit tieing up the online nodes cycles if they (or a number of them) are begging to fail
        
        //protect class from only being able to be ran by certain IP
        //get a list of all online/offline IPs depending on which ones i'm checking
        //check to see if they are pinging, and do all the assocaited updating and what not [THIS IS THE BIG TASK, DO WE NEED TO BReAK THIS DOWN???]

        //questions111
        //q: are we going to run both online and offline node checks in the same controller and class?
        //a: learn how php queues work first

        $Ips = Ips::all();
        $Ping_ip_table = Ping_ip_table::all();

        foreach ($Ping_ip_table as $node_being_monitored)
        {
            dispatch(new PingJob($node_being_monitored));
        }

        echo "Finished";

    }

    
}
