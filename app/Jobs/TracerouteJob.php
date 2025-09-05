<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use App\traceroute;
use App\ping_ip_table;
use Illuminate\Support\Facades\Log;

class TracerouteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $node;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($node)
    {
        $this->node = $node;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        logger("creating traceroute report for ".$this->node);

        // Skip expensive traceroute if node is known offline; record an entry instead
        $status = ping_ip_table::where('ip', $this->node)
            ->value('last_email_status');

        if ($status && strtolower($status) === 'offline') {
            Log::info('Skipping traceroute for offline node', ['ip' => $this->node, 'status' => $status]);
            $traceroute = new traceroute;
            $traceroute->node = $this->node;
            $traceroute->report = sprintf(
                "Node offline (status: %s) — traceroute skipped at %s",
                $status,
                now()->toDateTimeString()
            );
            $traceroute->save();
            return;
        }

        $script = base_path('trace_hybrid.sh');
        $maxTtl = env('TRACE_MAX_TTL', '30');
        $ttlProbes = env('TRACE_TTL_PROBES', '3');
        $waitSecs = env('TRACE_WAIT_SECS', '1');
        $echoProbes = env('TRACE_ECHO_PROBES', '3');

        $process = new Process(['/bin/bash', $script, $this->node, $maxTtl, $ttlProbes, $waitSecs, $echoProbes]);
        // Guard against long-running child processes; tune via env if needed
        $process->setTimeout((int) env('TRACE_PROCESS_TIMEOUT', 120));
        $process->setIdleTimeout((int) env('TRACE_IDLE_TIMEOUT', 60));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $traceroute = new traceroute;
        $traceroute->node = $this->node;
        $traceroute->report = $process->getOutput();
        $traceroute->save();

        //echo "<pre>".$process->getOutput()."</pre>";
    }
}
