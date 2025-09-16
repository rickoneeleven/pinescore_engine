<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use App\traceroute;
use App\ping_ip_table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TracerouteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $node;
    protected $lockToken;
    protected $lockReleased = false;

    public const LOCK_TTL_SECONDS = 600;

    /**
     * Allow more time than the process timeout to avoid worker kill.
     * Also restrict retries to avoid repeated heavy traces.
     */
    public $timeout;   // seconds
    public $tries;     // max attempts


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($node, $lockToken)
    {
        $this->node = $node;
        $this->lockToken = $lockToken;
        $processTimeout = (int) env('TRACE_PROCESS_TIMEOUT', 120);
        // Give the job a buffer over process timeout so we can catch and persist
        $this->timeout = (int) env('TRACE_JOB_TIMEOUT', max($processTimeout + 30, 180));
        $this->tries = (int) env('TRACE_JOB_TRIES', 1);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
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

            try {
                $process->run();
            } catch (ProcessTimedOutException $e) {
                Log::warning('Traceroute timed out', [
                    'ip'       => $this->node,
                    'message'  => $e->getMessage(),
                    'timeout'  => $process->getTimeout(),
                    'idle'     => $process->getIdleTimeout(),
                ]);

                $traceroute = new traceroute;
                $traceroute->node = $this->node;
                $partial = trim($process->getOutput());
                $traceroute->report = "Traceroute timed out after " . $process->getTimeout() . "s\n" . ($partial ?: '');
                $traceroute->save();
                return;
            } catch (\Throwable $e) {
                Log::error('Traceroute process threw', [
                    'ip'      => $this->node,
                    'class'   => get_class($e),
                    'message' => $e->getMessage(),
                ]);

                $traceroute = new traceroute;
                $traceroute->node = $this->node;
                $traceroute->report = "Traceroute error: " . $e->getMessage();
                $traceroute->save();
                return;
            }

            if (!$process->isSuccessful()) {
                // Don't fail the job; persist diagnostic output so the engine remains stable
                Log::error('Traceroute command failed', [
                    'ip'         => $this->node,
                    'exitCode'   => $process->getExitCode(),
                    'error'      => trim($process->getErrorOutput()),
                    'output'     => trim($process->getOutput()),
                ]);

                $traceroute = new traceroute;
                $traceroute->node = $this->node;
                $errorOutput = trim($process->getErrorOutput());
                $stdout = trim($process->getOutput());
                $traceroute->report = "Traceroute failed (exit code: " . $process->getExitCode() . ")\n" .
                    ($errorOutput !== '' ? $errorOutput : $stdout);
                $traceroute->save();
                return;
            }

            $traceroute = new traceroute;
            $traceroute->node = $this->node;
            $traceroute->report = $process->getOutput();
            $traceroute->save();

            //echo "<pre>".$process->getOutput()."</pre>";
        } finally {
            $this->releaseLock();
        }
    }

    public function failed($exception)
    {
        $this->releaseLock();
    }

    public static function lockKeyForNode($node)
    {
        return 'trace-route-lock:' . $node;
    }

    protected function releaseLock(): void
    {
        if ($this->lockReleased) {
            return;
        }

        $this->lockReleased = true;

        try {
            $key = self::lockKeyForNode($this->node);
            $currentToken = Redis::get($key);

            if ($currentToken === $this->lockToken) {
                Redis::del($key);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to release traceroute lock', [
                'ip'     => $this->node,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
