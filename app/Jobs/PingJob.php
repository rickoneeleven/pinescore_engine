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
use App\alerts; // Keep this import
use App\Notifications\NodeChangeAlert;
use Notification;
use Illuminate\Support\Facades\Log; // Added for logging
use Carbon\Carbon; // Added for explicit time comparison

class PingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ping_ip_table_row;

    /**
     * Create a new job instance.
     *
     * @param ping_ip_table $ping_ip_table_row // Type hint added for clarity
     * @return void
     */
    public function __construct(ping_ip_table $ping_ip_table_row) // Type hint added
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
        Log::debug("PingJob start", ['ip' => $this->ping_ip_table_row->ip, 'id' => $this->ping_ip_table_row->id]);

        try {
            $ping_ms = $this->pingHost($this->ping_ip_table_row->ip);
            $current_status = $this->determineStatus($ping_ms);
            $previous_status = $this->ping_ip_table_row->last_email_status ?: 'New'; // Handle initial null state

            Log::debug("Ping result", [
                'ip' => $this->ping_ip_table_row->ip,
                'ms' => $ping_ms,
                'current_status' => $current_status,
                'previous_status' => $previous_status
            ]);

            $status_changed = ($current_status !== $previous_status);
            $state_change_needs_confirmation = ($status_changed || $this->ping_ip_table_row->count > 0);
            $count_plus_one_for_readability_in_reports = $this->ping_ip_table_row->count + 1; // Calculate here for reuse
            $ping_result_table_duplicate_protection = 0;
            $result_message = "";
            $control_ok = true;

            if ($status_changed) {
                Log::info("Status change detected", ['ip' => $this->ping_ip_table_row->ip, 'from' => $previous_status, 'to' => $current_status]);
                $control_ok = $this->icmpControlCheck();
                if ($control_ok) {
                    $this->recordStatusChangeStat();
                } else {
                    Log::warning("ICMP control failed - suppressing transition", ['ip' => $this->ping_ip_table_row->ip]);
                }
            }

            // Retrieve all ping_ip_table entries for this IP to update them consistently
            $ping_ip_table_entries = ping_ip_table::where('ip', $this->ping_ip_table_row->ip)->get();

            foreach ($ping_ip_table_entries as $ping_ip_table_entry) {

                // Prepare status message
                if ($state_change_needs_confirmation) {
                    if ($status_changed && !$control_ok) {
                        $result_message = "Control check failed - suppressing transition {$previous_status}->{$current_status}";
                    } else
                    if ($previous_status === "Offline" && $current_status === "Online") {
                        $result_message = "Ping received, confirming stability ({$count_plus_one_for_readability_in_reports}/10)";
                    } elseif ($previous_status === "Online" && $current_status === "Offline") {
                        $result_message = "Packet dropped, confirming outage ({$count_plus_one_for_readability_in_reports}/10)";
                    } elseif ($previous_status === "New") {
                         $result_message = "Monitoring new node, detected initial status: {$current_status}";
                    } else {
                        $direction = $ping_ip_table_entry->count_direction ?? 'Stable';
                        $result_message = "Status confirmation count {$ping_ip_table_entry->count}, direction {$direction}";
                    }
                } else {
                     $result_message = "Status stable: {$current_status}";
                }

                // Handle confirmation counter logic (skip any counter/transition work on control failure)
                if ($state_change_needs_confirmation && !($status_changed && !$control_ok)) {
                    if ($ping_ip_table_entry->count >= 9 && $status_changed) { // Threshold met AND status is different
                        Log::info("Confirmation threshold reached. Finalizing status change.", [
                            'ip' => $ping_ip_table_entry->ip,
                            'alert_id' => $ping_ip_table_entry->id,
                            'final_status' => $current_status
                        ]);

                        // Commit the state change first so email cannot block flips
                        $ping_ip_table_entry->last_email_status = $current_status;
                        $ping_ip_table_entry->count = 0;
                        $ping_ip_table_entry->count_direction = null; // Reset direction
                        $result_message = "Status confirmed: {$current_status}"; // Update message for DB log
                        $ping_ip_table_entry->save();

                        // --- Alerting Logic (non-blocking) ---
                        $associated_alerts = alerts::where('ping_ip_id', $ping_ip_table_entry->id)->get();
                        foreach ($associated_alerts as $alerts_row) {
                            if ($alerts_row->disabled_until && Carbon::parse($alerts_row->disabled_until)->isFuture()) {
                                Log::info("Skipping notification (temporarily disabled).", [
                                    'ip' => $ping_ip_table_entry->ip,
                                    'email' => $alerts_row->email,
                                    'alert_rule_id' => $alerts_row->id,
                                    'disabled_until' => $alerts_row->disabled_until
                                ]);
                                continue;
                            }

                            $mailData = [
                                'note' => $ping_ip_table_entry->note ?? ('Node ' . $ping_ip_table_entry->ip),
                                'now_state' => $current_status,
                            ];

                            try {
                                Log::info("Queueing status change notification.", [
                                    'ip' => $ping_ip_table_entry->ip,
                                    'email' => $alerts_row->email,
                                    'alert_rule_id' => $alerts_row->id,
                                    'new_status' => $current_status
                                ]);
                                Notification::route('mail', $alerts_row->email)->notify(new NodeChangeAlert($mailData));
                            } catch (\Throwable $notifyEx) {
                                Log::error("Failed to enqueue notification.", [
                                    'ip' => $ping_ip_table_entry->ip,
                                    'email' => $alerts_row->email,
                                    'alert_rule_id' => $alerts_row->id,
                                    'error' => $notifyEx->getMessage()
                                ]);
                                // Do not rethrow; state is already committed
                            }
                        }
                        // --- End Alerting Logic ---

                        // Record result for this iteration (first time only below)

                    } elseif ($status_changed) { // Status changed, but threshold not met yet
                        $ping_ip_table_entry->count++;
                        $ping_ip_table_entry->count_direction = "Up";
                        Log::debug("Incrementing confirmation counter.", ['ip' => $ping_ip_table_entry->ip, 'alert_id' => $ping_ip_table_entry->id, 'count' => $ping_ip_table_entry->count]);

                    } else { // Status matches last confirmed state, but counter was > 0 (meaning flapping/recovering)
                        $ping_ip_table_entry->count--;
                        $ping_ip_table_entry->count_direction = "Down";
                        Log::debug("Decrementing confirmation counter.", ['ip' => $ping_ip_table_entry->ip, 'alert_id' => $ping_ip_table_entry->id, 'count' => $ping_ip_table_entry->count]);
                    }
                } else { // Status hasn't changed and counter is 0 - normal operation
                     $ping_ip_table_entry->count = 0; // Ensure counter stays at 0
                     $ping_ip_table_entry->count_direction = null;
                }


                // Initial status for new nodes only when control is OK
                if ($previous_status === 'New' && $control_ok) {
                   $ping_ip_table_entry->last_email_status = $current_status;
                   $ping_ip_table_entry->count = 0; // Start fresh
                   $ping_ip_table_entry->count_direction = null;
                   Log::info("Setting initial status for new node.", ['ip' => $ping_ip_table_entry->ip, 'alert_id' => $ping_ip_table_entry->id, 'status' => $current_status]);
                }


                // Save ping_ip_table changes for this specific entry (ensure last_ran always updated)
                $ping_ip_table_entry->last_ran = now(); // Use Laravel helper for consistency
                $ping_ip_table_entry->save();


                // Save result to ping_result_table only once per job execution
                if ($ping_result_table_duplicate_protection === 0) {
                     $this->recordPingResult($ping_ms, $current_status, $status_changed, $result_message);
                     $ping_result_table_duplicate_protection++;
                }

            } // End foreach ping_ip_table_entries

        } catch (\Exception $e) {
            Log::error("Error executing PingJob.", [
                'ip' => $this->ping_ip_table_row->ip ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Include stack trace
            ]);
            // Optionally re-throw or fail the job depending on desired queue behavior
             $this->fail($e); // Mark job as failed
        }

        Log::debug("PingJob finish", ['ip' => $this->ping_ip_table_row->ip]);
    }

    /**
     * Ping the host and return latency in ms or 0 for failure.
     * Uses OS-specific commands.
     *
     * @param string $host
     * @param int $timeout
     * @return int Milliseconds or 0 if offline/timeout.
     */
    private function pingHost(string $host, int $timeout = 2): int
    {
        $deadline = (int) env('PING_DEADLINE_SECONDS', 2);
        $count = (int) env('PING_COUNT', 2);
        $attempts = (int) env('PING_ATTEMPTS', 2);
        $isLinux = (PHP_OS === 'Linux');
        $command = $isLinux
            ? sprintf('ping -n -w %d -c %d %s', $deadline, $count, escapeshellarg($host))
            : sprintf('/sbin/ping -n -t %d -c %d %s', $deadline, $count, escapeshellarg($host));

        Log::debug("Executing ping command", ['command' => $command]);

        for ($k = 0; $k < max(1, $attempts); $k++) {
            $output = [];
            $exitCode = -1; // Initialize with error state
            exec($command, $output, $exitCode);

            Log::debug("Ping execution result", ['exitCode' => $exitCode, 'output' => $output]);

            // If any valid reply is present in output, accept it regardless of exit code.
            // Resolve hostname to IP(s) for validation (if host is not already an IP)
            $resolved_ips = [];
            if (!filter_var($host, FILTER_VALIDATE_IP)) {
                $dns_records = @dns_get_record($host, DNS_A);
                if ($dns_records) {
                    foreach ($dns_records as $record) {
                        if (!empty($record['ip'])) $resolved_ips[] = $record['ip'];
                    }
                }
                if (empty($resolved_ips)) {
                    $resolved_ip = @gethostbyname($host);
                    if ($resolved_ip !== $host) {
                        $resolved_ips[] = $resolved_ip;
                    }
                }
            } else {
                $resolved_ips[] = $host;
            }

            foreach ($output as $line) {
                if (preg_match('/(\d+) bytes from ([0-9.]+):.*time[=<]([0-9.]+)\s*ms/', $line, $matches)) {
                    $response_ip = $matches[2];
                    $latency = (int)ceil((float)$matches[3]);
                    if (in_array($response_ip, $resolved_ips, true)) {
                        Log::debug("Ping latency parsed from valid IP", [
                            'target' => $host,
                            'response_ip' => $response_ip,
                            'ms' => $latency
                        ]);
                        return $latency;
                    } else {
                        Log::warning("Ping response from unrelated IP - ignoring", [
                            'target' => $host,
                            'resolved_ips' => $resolved_ips,
                            'response_ip' => $response_ip,
                            'ms' => $latency,
                            'line' => $line
                        ]);
                    }
                }
            }

            // Successful exit but no parsable line
            if ($exitCode === 0) {
                 // If loop finishes without finding valid response from resolved IPs
                 Log::debug("No valid ping response from target or resolved IPs", [
                     'target' => $host,
                     'resolved_ips' => $resolved_ips
                 ]);
                 break;
            } elseif ($exitCode === 1) {
                Log::warning("Ping exit code 1 - retrying if attempts remain", [
                    'ip' => $host,
                    'exitCode' => $exitCode,
                    'output' => $output,
                    'attempt' => $k+1
                ]);
                continue;
            } else {
                Log::warning("Ping command execution failed.", ['ip' => $host, 'exitCode' => $exitCode, 'attempt' => $k+1]);
                // Optionally sleep briefly before retrying if exit code indicates a potentially transient issue
                // sleep(1);
            }
        }

        Log::warning("Ping failed after retries.", ['ip' => $host]);
        return 0; // Indicate failure/offline
    }

    /**
     * Determine status based on ping latency.
     *
     * @param int $ping_ms
     * @return string "Online" or "Offline".
     */
    private function determineStatus(int $ping_ms): string
    {
        return ($ping_ms > 0) ? "Online" : "Offline";
    }

    /**
     * Perform ICMP control check against predefined reliable hosts.
     *
     * @return bool True if at least one control host is reachable, false otherwise.
     */
    private function icmpControlCheck(): bool
    {
        $controlIp1 = env('CONTROL_IP_1', '8.8.8.8'); // Default to Google DNS
        $controlIp2 = env('CONTROL_IP_2', '1.1.1.1'); // Default to Cloudflare DNS

        Log::debug("Performing ICMP control check.", ['control_ip_1' => $controlIp1, 'control_ip_2' => $controlIp2]);

        if ($this->pingHost($controlIp1) > 0) {
            Log::debug("ICMP control check passed (IP1 reachable).", ['control_ip' => $controlIp1]);
            return true;
        }

        Log::warning("Control IP 1 failed, trying IP 2.", ['control_ip_1' => $controlIp1]);

        if ($this->pingHost($controlIp2) > 0) {
             Log::debug("ICMP control check passed (IP2 reachable).", ['control_ip' => $controlIp2]);
            return true;
        }

        Log::error("ICMP control check failed. Both control IPs unreachable.", ['control_ip_1' => $controlIp1, 'control_ip_2' => $controlIp2]);
        return false;
    }

    /**
     * Record a status change event in the 'stats' table.
     *
     * @return void
     */
    private function recordStatusChangeStat(): void
    {
        try {
            $stat = new stat;
            $stat->ip = $this->ping_ip_table_row->ip;
            $stat->datetime = now(); // Use Laravel helper
            $stat->score = -1; // Assuming -1 signifies a status change event
            $stat->save();
            Log::info("Status change stat recorded.", ['ip' => $this->ping_ip_table_row->ip]);
        } catch (\Exception $e) {
            Log::error("Failed to record status change stat.", [
                'ip' => $this->ping_ip_table_row->ip,
                'error' => $e->getMessage()
            ]);
            // Decide if this error should fail the job or just be logged
        }
    }

     /**
     * Record the ping result in the 'ping_result_table'.
     *
     * @param int $ping_ms
     * @param string $current_status
     * @param bool $status_changed
     * @param string $result_message
     * @return void
     */
    private function recordPingResult(int $ping_ms, string $current_status, bool $status_changed, string $result_message): void
    {
        try {
            $ping_result = new ping_result_table;
            $ping_result->ip = $this->ping_ip_table_row->ip;
            $ping_result->datetime = now();
            $ping_result->ms = $ping_ms;
            $ping_result->result = $current_status;
            $ping_result->change = $status_changed ? 1 : 0; // Store as 1 or 0
            $ping_result->email_sent = $result_message; // Use the determined message
            $ping_result->save();
            Log::debug("Ping result recorded in database.", ['ip' => $this->ping_ip_table_row->ip]);
        } catch (\Exception $e) {
             Log::error("Failed to record ping result.", [
                'ip' => $this->ping_ip_table_row->ip,
                'error' => $e->getMessage()
            ]);
             // Decide if this error should fail the job or just be logged
        }
    }
}
