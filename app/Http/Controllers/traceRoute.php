<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ping_ip_table;
use App\Jobs\TracerouteJob;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;


class traceRoute extends Controller
{
    public function go() {
    $ping_ip_table = ping_ip_table::all()->unique('ip');
        $x = 1;
        foreach ($ping_ip_table as $ping_ip_table_row) {
                $lockKey = TracerouteJob::lockKeyForNode($ping_ip_table_row->ip);
                $lockToken = (string) Str::uuid();
                $lockTtl = TracerouteJob::LOCK_TTL_SECONDS;
                $acquired = Redis::command('set', [$lockKey, $lockToken, 'NX', 'EX', $lockTtl]);

                if (!$acquired) {
                    continue;
                }

                dispatch((new TracerouteJob($ping_ip_table_row->ip, $lockToken))->onQueue('traceRoute'));
                $x++;
                //if($x > 1) die();
        }
    }
}
