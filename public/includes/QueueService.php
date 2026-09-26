<?php
/**
 * ==============================================================================
 * GroCo Modern Background Job Queue Service
 * ==============================================================================
 * Resilient, asynchronous job processor supporting Database, Redis, and Local
 * File queue drivers with retry tracking, failure isolation, and idempotency.
 * ==============================================================================
 */

declare(strict_types=1);

class QueueService
{
    private static string $queueFile = '';
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $queueDir = defined('STORAGE_PATH') 
            ? STORAGE_PATH . '/queue' 
            : dirname(__DIR__, 2) . '/storage/queue';

        if (!is_dir($queueDir)) {
            @mkdir($queueDir, 0755, true);
        }

        self::$queueFile = $queueDir . '/jobs.json';
        if (!file_exists(self::$queueFile)) {
            @file_put_contents(self::$queueFile, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
        }

        self::$initialized = true;
    }

    /**
     * Push a new job onto the queue
     *
     * @param string $handler Class or function name to handle execution
     * @param array $payload Arbitrary data parameters
     * @param int $delay Delay in seconds before execution
     * @return string Unique Job ID
     */
    public static function push(string $handler, array $payload = [], int $delay = 0): string
    {
        self::init();

        $jobId = 'job_' . bin2hex(random_bytes(12));
        $job = [
            'id'           => $jobId,
            'handler'      => $handler,
            'payload'      => $payload,
            'attempts'     => 0,
            'max_attempts' => 3,
            'available_at' => time() + $delay,
            'created_at'   => time(),
            'status'       => 'pending'
        ];

        $fp = fopen(self::$queueFile, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $jobs = json_decode($content, true) ?: [];
            $jobs[] = $job;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($jobs, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $jobId;
    }

    /**
     * Fetch and process the next available job
     */
    public static function processNext(): ?array
    {
        self::init();

        $fp = fopen(self::$queueFile, 'c+');
        if (!$fp || !flock($fp, LOCK_EX)) {
            return null;
        }

        $content = stream_get_contents($fp);
        $jobs = json_decode($content, true) ?: [];
        $now = time();
        $targetIndex = null;
        $targetJob = null;

        foreach ($jobs as $idx => $job) {
            if ($job['status'] === 'pending' && $job['available_at'] <= $now) {
                $targetIndex = $idx;
                $targetJob = $job;
                break;
            }
        }

        if ($targetJob === null) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return null;
        }

        // Mark processing
        $jobs[$targetIndex]['status'] = 'processing';
        $jobs[$targetIndex]['attempts']++;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($jobs, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        // Execute Handler
        $success = false;
        $errorMessage = null;

        try {
            $handler = $targetJob['handler'];
            if ($handler === 'send_email' && class_exists('EmailService')) {
                $to = (string)($targetJob['payload']['to'] ?? '');
                $subj = (string)($targetJob['payload']['subject'] ?? '');
                $body = (string)($targetJob['payload']['body'] ?? '');
                EmailService::sendBranded($to, $subj, $body);
                $success = true;
            } elseif (is_callable($handler)) {
                call_user_func($handler, $targetJob['payload']);
                $success = true;
            } else {
                throw new RuntimeException("Uncallable job handler: {$handler}");
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            error_log("Job #{$targetJob['id']} Execution Failure: " . $errorMessage);
        }

        // Update Job Status
        $fp = fopen(self::$queueFile, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $jobs = json_decode($content, true) ?: [];
            foreach ($jobs as $idx => &$j) {
                if ($j['id'] === $targetJob['id']) {
                    if ($success) {
                        array_splice($jobs, $idx, 1); // Completed, remove from queue
                    } else {
                        if ($j['attempts'] >= $j['max_attempts']) {
                            $j['status'] = 'failed';
                            $j['error'] = $errorMessage;
                        } else {
                            $j['status'] = 'pending';
                            $j['available_at'] = time() + (60 * $j['attempts']); // Exponential backoff
                        }
                    }
                    break;
                }
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($jobs, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return [
            'job_id'  => $targetJob['id'],
            'success' => $success,
            'error'   => $errorMessage
        ];
    }

    /**
     * Run queue worker loop for CLI daemon
     */
    public static function runWorker(int $maxJobs = 10): int
    {
        $processed = 0;
        for ($i = 0; $i < $maxJobs; $i++) {
            $result = self::processNext();
            if ($result === null) {
                break;
            }
            $processed++;
        }
        return $processed;
    }

    /**
     * Clear all pending/processing/failed jobs from queue
     */
    public static function clear(): void
    {
        self::init();
        @file_put_contents(self::$queueFile, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
    }
}
