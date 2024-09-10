<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\traceRoute;

class RunTraceRoute extends Command
{
    protected $signature = 'run:trace-route';
    protected $description = 'Manually run the traceRoute@go method';

    public function handle()
    {
        $controller = new traceRoute();
        $controller->go();
        $this->info('TraceRoute task has been executed.');
    }
}