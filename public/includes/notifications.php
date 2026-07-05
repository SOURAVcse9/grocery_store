<?php
/**
 * ==========================================================================
 * public/includes/notifications.php — Notifications Dispatcher & Queue Engine
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

/**
 * queue_email()
 * Queues an email for background asynchronous SMTP processing.
 */
function queue_email(string $to, string $subject, string $body): bool
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO email_queue (to_email, subject, body, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        return $stmt->execute([$to, $subject, $body]);
    } catch (PDOException $e) {
        error_log('[notifications.php] queue_email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * queue_sms()
 * Queues an SMS message for mobile gateway processing.
 */
function queue_sms(string $phone, string $message): bool
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO sms_queue (to_phone, message, status, created_at)
            VALUES (?, ?, 'pending', NOW())
        ");
        return $stmt->execute([$phone, $message]);
    } catch (PDOException $e) {
        error_log('[notifications.php] queue_sms failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * trigger_push_notification()
 * Dispatches an in-app dashboard push alert to a buyer or admin broadcast.
 */
function trigger_push_notification(?int $userId, string $title, string $message, string $type = 'general', ?string $link = null): bool
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$userId, $title, $message, $type, $link]);
    } catch (PDOException $e) {
        error_log('[notifications.php] trigger_push_notification failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * process_notification_queues()
 * Run by cron tasks to dispatch queued items with custom retries.
 */
function process_notification_queues(): array
{
    $pdo = db();
    $results = ['emails_sent' => 0, 'sms_sent' => 0, 'failed' => 0];

    try {
        // 1. Process pending email queue items
        $stmtEmails = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' LIMIT 20");
        $pendingEmails = $stmtEmails->fetchAll();

        $stmtUpdEmail = $pdo->prepare("UPDATE email_queue SET status = ?, retry_count = retry_count + 1, error_message = ? WHERE id = ?");

        foreach ($pendingEmails as $email) {
            // Simulated SMTP Delivery
            $success = true; // Assume success for mock demonstration
            
            if ($success) {
                $stmtUpdEmail->execute(['sent', null, $email['id']]);
                $results['emails_sent']++;
            } else {
                $errorMsg = 'Simulated SMTP connection timeout.';
                $newStatus = ($email['retry_count'] >= 2) ? 'failed' : 'pending';
                $stmtUpdEmail->execute([$newStatus, $errorMsg, $email['id']]);
                $results['failed']++;
            }
        }

        // 2. Process pending SMS queue items
        $stmtSMS = $pdo->query("SELECT * FROM sms_queue WHERE status = 'pending' LIMIT 30");
        $pendingSMS = $stmtSMS->fetchAll();

        $stmtUpdSMS = $pdo->prepare("UPDATE sms_queue SET status = ?, retry_count = retry_count + 1, error_message = ? WHERE id = ?");

        foreach ($pendingSMS as $sms) {
            // Simulated SMS Mobile Gateway Delivery (Twilio / BulkSMSBD)
            $success = true;

            if ($success) {
                $stmtUpdSMS->execute(['sent', null, $sms['id']]);
                $results['sms_sent']++;
            } else {
                $errorMsg = 'Gateway response failed.';
                $newStatus = ($sms['retry_count'] >= 2) ? 'failed' : 'pending';
                $stmtUpdSMS->execute([$newStatus, $errorMsg, $sms['id']]);
                $results['failed']++;
            }
        }

    } catch (PDOException $e) {
        error_log('[notifications.php] Worker execution failed: ' . $e->getMessage());
    }

    return $results;
}
