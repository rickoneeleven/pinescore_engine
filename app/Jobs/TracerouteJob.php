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
        $process = new Process([env('TRACEROUTE_BIN_LOCATION', '/usr/sbin/traceroute'), '-I', '-q1', '-w1', '-m30', $this->node]); //different locate than usual
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
