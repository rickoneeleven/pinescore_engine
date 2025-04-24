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
use App\oscheck;
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
        // Log job start with relevant data
        Log::info("PingJob started.", ['ip' => $this->ping_ip_table_row->ip, 'id' => $this->ping_ip_table_row->id]);

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
            $result_message = ""; // Initialize message

            // Update stat table if status changed AND ICMP control check passes
            if ($status_changed) {
                Log::info("Status change detected", ['ip' => $this->ping_ip_table_row->ip, 'from' => $previous_status, 'to' => $current_status]);
                if ($this->icmpControlCheck()) {
                    $this->recordStatusChangeStat();
                } else {
                    Log::warning("ICMP Control Check failed. Aborting PingJob execution.", ['ip' => $this->ping_ip_table_row->ip]);
                    // Optionally: re-throw an exception or handle differently if control failure is critical
                    return; // Stop processing this job if control fails
                }
            }

            // Retrieve all ping_ip_table entries for this IP to update them consistently
            $ping_ip_table_entries = ping_ip_table::where('ip', $this->ping_ip_table_row->ip)->get();

            foreach ($ping_ip_table_entries as $ping_ip_table_entry) {

                // Prepare status message for logging/DB entry
                if ($state_change_needs_confirmation) {
                    if ($previous_status === "Offline" && $current_status === "Online") {
                        $result_message = "Ping received, confirming stability ({$count_plus_one_for_readability_in_reports}/10)";
                    } elseif ($previous_status === "Online" && $current_status === "Offline") {
                        $result_message = "Packet dropped, confirming outage ({$count_plus_one_for_readability_in_reports}/10)";
                    } elseif ($previous_status === "New") {
                         $result_message = "Monitoring new node, detected initial status: {$current_status}";
                    } else {
                        // Handle edge cases or ongoing confirmation counts
                        $direction = $ping_ip_table_entry->count_direction ?? 'Stable';
                        $result_message = "Status confirmation count {$ping_ip_table_entry->count}, direction {$direction}";
                    }
                } else {
                     $result_message = "Status stable: {$current_status}";
                }

                // Handle confirmation counter logic
                if ($state_change_needs_confirmation) {
                    if ($ping_ip_table_entry->count >= 9 && $status_changed) { // Threshold met AND status is different
                        Log::info("Confirmation threshold reached. Finalizing status change.", [
                            'ip' => $ping_ip_table_entry->ip,
                            'alert_id' => $ping_ip_table_entry->id,
                            'final_status' => $current_status
                        ]);

                        // --- Alerting Logic ---
                        $associated_alerts = alerts::where('ping_ip_id', $ping_ip_table_entry->id)->get();
                        foreach ($associated_alerts as $alerts_row) {
                            // *** START: Added check for disabled_until ***
                            if ($alerts_row->disabled_until && Carbon::parse($alerts_row->disabled_until)->isFuture()) {
                                Log::info("Skipping notification (temporarily disabled).", [
                                    'ip' => $ping_ip_table_entry->ip,
                                    'email' => $alerts_row->email,
                                    'alert_rule_id' => $alerts_row->id,
                                    'disabled_until' => $alerts_row->disabled_until
                                ]);
                                continue; // Skip to the next alert rule
                            }
                            // *** END: Added check for disabled_until ***

                            $data = [
                                'alerts_row' => $alerts_row, // Contains email, etc.
                                'ping_ip_table_row' => $ping_ip_table_entry, // Contains node info like 'note'
                                'now_state' => $current_status
                            ];

                            Log::info("Dispatching status change notification.", [
                                'ip' => $ping_ip_table_entry->ip,
                                'email' => $alerts_row->email,
                                'alert_rule_id' => $alerts_row->id,
                                'new_status' => $current_status
                            ]);
                            Notification::route('mail', $alerts_row->email)->notify(new NodeChangeAlert($data));
                        }
                        // --- End Alerting Logic ---

                        // Update state after successful confirmation and notification attempt
                        $ping_ip_table_entry->last_email_status = $current_status;
                        $ping_ip_table_entry->count = 0;
                        $ping_ip_table_entry->count_direction = null; // Reset direction
                        $result_message = "Status confirmed: {$current_status}"; // Update message for DB log

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


                // Initial status setting for new nodes
                if ($previous_status === 'New') {
                   $ping_ip_table_entry->last_email_status = $current_status;
                   $ping_ip_table_entry->count = 0; // Start fresh
                   $ping_ip_table_entry->count_direction = null;
                   Log::info("Setting initial status for new node.", ['ip' => $ping_ip_table_entry->ip, 'alert_id' => $ping_ip_table_entry->id, 'status' => $current_status]);
                }


                // Save ping_ip_table changes for this specific entry
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

        Log::info("PingJob finished.", ['ip' => $this->ping_ip_table_row->ip]);
    }

    /**
     * Ping the host and return latency in ms or 0 for failure.
     * Uses OS-specific commands.
     *
     * @param string $host
     * @param int $timeout
     * @return int Milliseconds or 0 if offline/timeout.
     */
    private function pingHost(string $host, int $timeout = 1): int
    {
        // Lazily instantiate oscheck only if needed within the method
        $osChecker = new oscheck;
        $command = $osChecker->isLinux()
            ? sprintf('ping -n -w %d -c 1 %s', $timeout, escapeshellarg($host))
            : sprintf('/sbin/ping -n -t %d -c 1 %s', $timeout, escapeshellarg($host)); // Assuming macOS/BSD otherwise

        Log::debug("Executing ping command", ['command' => $command]);

        // Try pinging up to 2 times if the first fails immediately (e.g., command error)
        for ($k = 0; $k < 2; $k++) {
            $output = [];
            $exitCode = -1; // Initialize with error state
            exec($command, $output, $exitCode);

            Log::debug("Ping execution result", ['exitCode' => $exitCode, 'output' => $output]);

            // Exit codes 0 (success) or 1 (on some systems, partial success/host unreachable but command ran)
            // are considered valid attempts to parse output.
            if ($exitCode === 0 || $exitCode === 1) {
                foreach ($output as $line) {
                    // Regex to handle variations like time=X.Y ms, time=X ms
                    if (preg_match('/time[=<]([0-9.]+)\s*ms/', $line, $matches)) {
                        $latency = (int)ceil(floatval($matches[1]));
                         Log::debug("Ping latency parsed", ['ip' => $host, 'ms' => $latency]);
                        return $latency;
                    }
                }
                 // If loop finishes without finding 'time=', it means host didn't reply within timeout
                 Log::debug("Ping reply not received or time string not found in output.", ['ip' => $host]);
                 break; // Don't retry if command executed but no reply time found
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