<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ping_ip_table;
use App\Jobs\TracerouteJob;


class traceRoute extends Controller
{
    public function go() {
    $ping_ip_table = ping_ip_table::all()->unique('ip');
        $x = 1;
        foreach ($ping_ip_table as $ping_ip_table_row) {
                //echo "dispatching job for: $ping_ip_table_row->ip";
                dispatch(new TracerouteJob($ping_ip_table_row->ip))->onQueue('traceRoute');
                $x++;
                //if($x > 1) die();
        }
    }
}
